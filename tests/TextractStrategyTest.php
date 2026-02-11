<?php

use JCFrane\PdfTextExtractor\Strategies\TextractStrategy;

function createTextractStrategy(?string $s3Bucket = null): TextractStrategy
{
    $region = getenv('PDF_EXTRACTOR_AWS_REGION');
    $key = getenv('PDF_EXTRACTOR_AWS_KEY');
    $secret = getenv('PDF_EXTRACTOR_AWS_SECRET');

    if (! $region || ! $key || ! $secret) {
        test()->markTestSkipped('AWS credentials not configured. Set PDF_EXTRACTOR_AWS_REGION, PDF_EXTRACTOR_AWS_KEY, and PDF_EXTRACTOR_AWS_SECRET environment variables.');
    }

    return new TextractStrategy(
        region: $region,
        key: $key,
        secret: $secret,
        s3Bucket: $s3Bucket,
    );
}

function ensureTextractS3Config(): string
{
    $bucket = getenv('PDF_EXTRACTOR_AWS_S3_BUCKET');
    if (! $bucket) {
        test()->markTestSkipped('S3 bucket not configured. Set PDF_EXTRACTOR_AWS_S3_BUCKET for multi-page Textract tests.');
    }

    return $bucket;
}

it('textract strategy extracts text from a single-page PDF', function () {
    $strategy = createTextractStrategy();

    $content = file_get_contents(__DIR__.'/fixtures/standard_cv_single_page.pdf');
    $text = $strategy->extract($content);

    expect(strlen(trim($text)))->toBeGreaterThan(0);
    expect($strategy->name())->toBe('textract');
});

it('textract strategy extracts text from a multi-page PDF', function () {
    $bucket = ensureTextractS3Config();
    $strategy = createTextractStrategy($bucket);

    $content = file_get_contents(__DIR__.'/fixtures/standard_cv.pdf');
    $text = $strategy->extract($content);
    dump($text);

    expect(strlen(trim($text)))->toBeGreaterThan(0);
    expect($strategy->name())->toBe('textract');
});
