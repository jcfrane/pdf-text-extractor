<?php

namespace JCFrane\PdfTextExtractor\Strategies;

use Aws\S3\S3Client;
use Aws\Textract\TextractClient;
use JCFrane\PdfTextExtractor\Contracts\ExtractionStrategy;

/**
 * Extract text using AWS Textract OCR
 *
 * Uses AWS Textract's DetectDocumentText API to perform OCR on PDF documents.
 * This is useful for scanned PDFs or image-based PDFs where text is not
 * selectable but visually readable.
 *
 * Single-page PDFs use Textract's synchronous API.
 * Multi-page PDFs use Textract's asynchronous API via S3.
 *
 * Requires the `aws/aws-sdk-php` package to be installed.
 *
 * @see https://docs.aws.amazon.com/textract/latest/dg/API_DetectDocumentText.html
 */
class TextractStrategy implements ExtractionStrategy
{
    protected TextractClient $client;

    protected ?S3Client $s3Client = null;

    protected ?string $s3Bucket;

    protected string $s3Prefix;

    protected int $asyncPollIntervalMs;

    protected int $asyncMaxAttempts;

    protected bool $deleteUploadedDocument;

    /**
     * Maximum file size Textract accepts per request (10MB)
     */
    protected const MAX_FILE_SIZE = 10 * 1024 * 1024;

    public function __construct(
        string $region,
        string $key,
        string $secret,
        string $version = 'latest',
        ?string $s3Bucket = null,
        string $s3Prefix = 'pdf-text-extractor',
        int $asyncPollIntervalMs = 1000,
        int $asyncMaxAttempts = 20,
        bool $deleteUploadedDocument = true,
    ) {
        if (! class_exists(TextractClient::class)) {
            throw new \RuntimeException(
                'AWS SDK is required for Textract strategy. Install it with: composer require aws/aws-sdk-php'
            );
        }

        $this->client = new TextractClient([
            'region' => $region,
            'version' => $version,
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ]);

        $this->s3Client = new S3Client([
            'region' => $region,
            'version' => $version,
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ]);

        $this->s3Bucket = $s3Bucket ? trim($s3Bucket) : null;
        $this->s3Prefix = trim($s3Prefix, '/');
        $this->asyncPollIntervalMs = max(100, $asyncPollIntervalMs);
        $this->asyncMaxAttempts = max(1, $asyncMaxAttempts);
        $this->deleteUploadedDocument = $deleteUploadedDocument;
    }

    /**
     * Create from an existing TextractClient instance
     */
    public static function fromClient(TextractClient $client): static
    {
        $instance = (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $instance->client = $client;
        $instance->s3Client = null;
        $instance->s3Bucket = null;
        $instance->s3Prefix = 'pdf-text-extractor';
        $instance->asyncPollIntervalMs = 1000;
        $instance->asyncMaxAttempts = 60;
        $instance->deleteUploadedDocument = true;

        return $instance;
    }

    public function extract(string $content): string
    {
        if ($this->isMultiPagePdf($content)) {
            return $this->detectTextAsync($content);
        }

        return $this->detectText($content);
    }

    public function name(): string
    {
        return 'textract';
    }

    /**
     * Send a single document to Textract's synchronous API
     */
    protected function detectText(string $content): string
    {
        if (strlen($content) > self::MAX_FILE_SIZE) {
            throw new \RuntimeException('Document exceeds Textract 10MB limit');
        }

        $result = $this->client->detectDocumentText([
            'Document' => [
                'Bytes' => $content,
            ],
        ]);

        return collect($result['Blocks'])
            ->filter(fn ($block) => $block['BlockType'] === 'LINE')
            ->pluck('Text')
            ->implode("\n");
    }

    /**
     * Use Textract async API for multi-page PDFs via S3 object reference.
     */
    protected function detectTextAsync(string $content): string
    {
        if ($this->s3Bucket === null || $this->s3Bucket === '') {
            throw new \RuntimeException(
                'Multi-page Textract requires an S3 bucket. Set PDF_EXTRACTOR_AWS_S3_BUCKET (config: textract.s3_bucket).'
            );
        }
        if ($this->s3Client === null) {
            throw new \RuntimeException('S3 client is not configured for Textract async processing.');
        }

        $key = $this->buildS3ObjectKey();

        try {
            $this->s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key' => $key,
                'Body' => $content,
                'ContentType' => 'application/pdf',
            ]);

            $startResult = $this->client->startDocumentTextDetection([
                'DocumentLocation' => [
                    'S3Object' => [
                        'Bucket' => $this->s3Bucket,
                        'Name' => $key,
                    ],
                ],
            ]);

            $jobId = $startResult['JobId'] ?? null;
            if (! is_string($jobId) || $jobId === '') {
                throw new \RuntimeException('Textract did not return a valid JobId for async text detection.');
            }

            $this->waitForJobCompletion($jobId);

            $lines = [];
            $nextToken = null;

            do {
                $params = ['JobId' => $jobId];
                if ($nextToken !== null) {
                    $params['NextToken'] = $nextToken;
                }

                $result = $this->client->getDocumentTextDetection($params);
                $status = $result['JobStatus'] ?? null;
                if (! in_array($status, ['SUCCEEDED', 'PARTIAL_SUCCESS'], true)) {
                    throw new \RuntimeException('Textract async job did not complete successfully: '.($status ?? 'UNKNOWN'));
                }

                foreach ($result['Blocks'] ?? [] as $block) {
                    if (($block['BlockType'] ?? null) === 'LINE' && isset($block['Text'])) {
                        $lines[] = $block['Text'];
                    }
                }

                $nextToken = $result['NextToken'] ?? null;
            } while ($nextToken !== null);

            return implode("\n", $lines);
        } finally {
            if ($this->deleteUploadedDocument) {
                $this->deleteUploadedObject($key);
            }
        }
    }

    protected function waitForJobCompletion(string $jobId): void
    {
        for ($attempt = 0; $attempt < $this->asyncMaxAttempts; $attempt++) {
            $result = $this->client->getDocumentTextDetection(['JobId' => $jobId]);
            $status = $result['JobStatus'] ?? null;

            if ($status === 'SUCCEEDED' || $status === 'PARTIAL_SUCCESS') {
                return;
            }

            if ($status === 'FAILED') {
                throw new \RuntimeException('Textract async job failed.');
            }

            usleep($this->asyncPollIntervalMs * 1000);
        }

        throw new \RuntimeException(
            "Textract async job timed out after {$this->asyncMaxAttempts} attempts."
        );
    }

    /**
     * Delete temporary S3 object, suppressing cleanup failures.
     */
    protected function deleteUploadedObject(string $key): void
    {
        if ($this->s3Client === null || $this->s3Bucket === null || $this->s3Bucket === '') {
            return;
        }

        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->s3Bucket,
                'Key' => $key,
            ]);
        } catch (\Throwable) {
            // Ignore cleanup errors.
        }
    }

    protected function buildS3ObjectKey(): string
    {
        $fileName = sprintf(
            'textract-%s.pdf',
            bin2hex(random_bytes(12))
        );

        if ($this->s3Prefix === '') {
            return $fileName;
        }

        return $this->s3Prefix.'/'.$fileName;
    }

    /**
     * Check if the content is a multi-page PDF
     */
    protected function isMultiPagePdf(string $content): bool
    {
        // Quick check: is it a PDF at all?
        if (substr($content, 0, 5) !== '%PDF-') {
            return false;
        }

        // Count /Type /Page entries, excluding /Type /Pages (page tree node)
        return preg_match_all('/\/Type\s*\/Page(?!s)/', $content) > 1;
    }
}
