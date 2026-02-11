# PDF Text Extractor (Laravel)

Laravel-first PDF text extraction with fallback strategies for:
- standard PDFs
- Canva/XObject-based PDFs
- scanned PDFs (via OCR)

## Installation

```bash
composer require jcfrane/pdf-text-extractor
```

Optional OCR dependencies:

```bash
# AWS Textract support
composer require aws/aws-sdk-php

# Tesseract support (system packages)
# Ubuntu/Debian:
apt-get install tesseract-ocr ghostscript
# macOS:
brew install tesseract ghostscript
```

## Laravel Setup

The package uses Laravel auto-discovery.  
If you want to customize settings, publish config:

```bash
php artisan vendor:publish --tag=pdf-text-extractor-config
```

This creates:
- `config/pdf-text-extractor.php`

## Quick Start (Laravel)

### Dependency Injection

```php
use JCFrane\PdfTextExtractor\PdfTextExtractor;

class ParseResumeAction
{
    public function __invoke(PdfTextExtractor $extractor, string $path): string
    {
        $result = $extractor->extract($path);

        if (! $result->isSuccessful()) {
            return '';
        }

        return $result->getText();
    }
}
```

### Facade

A facade is already included and auto-aliased as `PdfTextExtractor`.

```php
use JCFrane\PdfTextExtractor\Facades\PdfTextExtractor;

$result = PdfTextExtractor::extract(storage_path('app/resumes/candidate.pdf'));

if ($result->isSuccessful()) {
    $text = $result->getText();
    $strategyUsed = $result->getStrategy(); // pdf_parser, xobject, textract, tesseract
}
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=pdf-text-extractor-config
```

This creates `config/pdf-text-extractor.php` with the following options:

### Minimum Text Length

```php
'min_text_length' => env('PDF_EXTRACTOR_MIN_TEXT_LENGTH', 20),
```

The minimum number of characters an extraction must produce to be considered successful. If a strategy returns fewer characters than this threshold, the next strategy in the list will be tried. Increase this if short garbage output is being accepted; decrease it if your PDFs legitimately contain very little text.

### Strategies

```php
'strategies' => [
    JCFrane\PdfTextExtractor\Strategies\PdfParserStrategy::class,
    JCFrane\PdfTextExtractor\Strategies\XObjectStrategy::class,
    // JCFrane\PdfTextExtractor\Strategies\TextractStrategy::class,
    // JCFrane\PdfTextExtractor\Strategies\TesseractStrategy::class,
],
```

An ordered list of extraction strategies. Each strategy is attempted in sequence until one produces text meeting the `min_text_length` threshold. You can reorder, add, or remove strategies to suit your needs.

| Strategy | Best for | Requirements |
|---|---|---|
| `PdfParserStrategy` | Standard text-based PDFs | None (included) |
| `XObjectStrategy` | Canva / XObject-based PDFs | None (included) |
| `TextractStrategy` | Scanned PDFs (cloud OCR) | `aws/aws-sdk-php`, AWS credentials |
| `TesseractStrategy` | Scanned PDFs (local OCR) | `tesseract-ocr`, `ghostscript` binaries |

**Example: enable all strategies**

```php
'strategies' => [
    JCFrane\PdfTextExtractor\Strategies\PdfParserStrategy::class,
    JCFrane\PdfTextExtractor\Strategies\XObjectStrategy::class,
    JCFrane\PdfTextExtractor\Strategies\TextractStrategy::class,
    JCFrane\PdfTextExtractor\Strategies\TesseractStrategy::class,
],
```

### AWS Textract

Only required if `TextractStrategy` is in your strategies list. Requires `composer require aws/aws-sdk-php`.

```php
'textract' => [
    'region'  => env('PDF_EXTRACTOR_AWS_REGION', 'us-east-1'),
    'key'     => env('PDF_EXTRACTOR_AWS_KEY'),
    'secret'  => env('PDF_EXTRACTOR_AWS_SECRET'),
    'version' => env('PDF_EXTRACTOR_AWS_VERSION', 'latest'),

    // Required for multi-page PDFs (async API uploads the PDF to S3)
    's3_bucket' => env('PDF_EXTRACTOR_AWS_S3_BUCKET'),
    's3_prefix' => env('PDF_EXTRACTOR_AWS_S3_PREFIX', 'pdf-text-extractor'),

    // Async job polling
    'async_poll_interval_ms'  => (int) env('PDF_EXTRACTOR_AWS_ASYNC_POLL_INTERVAL_MS', 1000),
    'async_max_attempts'      => (int) env('PDF_EXTRACTOR_AWS_ASYNC_MAX_ATTEMPTS', 20),
    'async_delete_uploaded'   => (bool) env('PDF_EXTRACTOR_AWS_ASYNC_DELETE_UPLOADED', true),
],
```

| Key | Env Variable | Default | Description |
|---|---|---|---|
| `region` | `PDF_EXTRACTOR_AWS_REGION` | `us-east-1` | AWS region for Textract and S3 |
| `key` | `PDF_EXTRACTOR_AWS_KEY` | — | AWS access key ID |
| `secret` | `PDF_EXTRACTOR_AWS_SECRET` | — | AWS secret access key |
| `version` | `PDF_EXTRACTOR_AWS_VERSION` | `latest` | AWS SDK version |
| `s3_bucket` | `PDF_EXTRACTOR_AWS_S3_BUCKET` | — | S3 bucket for multi-page PDF processing |
| `s3_prefix` | `PDF_EXTRACTOR_AWS_S3_PREFIX` | `pdf-text-extractor` | Key prefix for uploaded PDFs in S3 |
| `async_poll_interval_ms` | `PDF_EXTRACTOR_AWS_ASYNC_POLL_INTERVAL_MS` | `1000` | Milliseconds between polling attempts for async jobs |
| `async_max_attempts` | `PDF_EXTRACTOR_AWS_ASYNC_MAX_ATTEMPTS` | `20` | Maximum number of polling attempts before giving up |
| `async_delete_uploaded` | `PDF_EXTRACTOR_AWS_ASYNC_DELETE_UPLOADED` | `true` | Delete the uploaded PDF from S3 after processing |

**How Textract works:**

- **Single-page PDFs** use the synchronous `DetectDocumentText` API — no S3 required.
- **Multi-page PDFs** use the async flow: the PDF is uploaded to S3, `StartDocumentTextDetection` is called, and the result is polled via `GetDocumentTextDetection`.

Add these env values to your `.env`:

```env
PDF_EXTRACTOR_AWS_REGION=eu-west-2
PDF_EXTRACTOR_AWS_KEY=your_key
PDF_EXTRACTOR_AWS_SECRET=your_secret

# Required for multi-page PDFs
PDF_EXTRACTOR_AWS_S3_BUCKET=your_bucket
```

**Required IAM permissions:**

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "TextractApis",
      "Effect": "Allow",
      "Action": [
        "textract:DetectDocumentText",
        "textract:StartDocumentTextDetection",
        "textract:GetDocumentTextDetection"
      ],
      "Resource": "*"
    },
    {
      "Sid": "TextractStagingObjectAccess",
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject"
      ],
      "Resource": "arn:aws:s3:::YOUR_BUCKET_NAME/pdf-text-extractor/*"
    },
    {
      "Sid": "TextractStagingBucketList",
      "Effect": "Allow",
      "Action": [
        "s3:ListBucket"
      ],
      "Resource": "arn:aws:s3:::YOUR_BUCKET_NAME"
    }
  ]
}
```

### Tesseract OCR

Only required if `TesseractStrategy` is in your strategies list. Requires `tesseract` and `ghostscript` installed on the system.

```php
'tesseract' => [
    'binary'              => env('PDF_EXTRACTOR_TESSERACT_BINARY', 'tesseract'),
    'ghostscript_binary'  => env('PDF_EXTRACTOR_GHOSTSCRIPT_BINARY', 'gs'),
    'language'            => env('PDF_EXTRACTOR_TESSERACT_LANGUAGE', 'eng'),
    'dpi'                 => (int) env('PDF_EXTRACTOR_TESSERACT_DPI', 300),
],
```

| Key | Env Variable | Default | Description |
|---|---|---|---|
| `binary` | `PDF_EXTRACTOR_TESSERACT_BINARY` | `tesseract` | Path to the Tesseract binary |
| `ghostscript_binary` | `PDF_EXTRACTOR_GHOSTSCRIPT_BINARY` | `gs` | Path to the Ghostscript binary |
| `language` | `PDF_EXTRACTOR_TESSERACT_LANGUAGE` | `eng` | Tesseract language code (e.g. `eng`, `fra`, `deu`) |
| `dpi` | `PDF_EXTRACTOR_TESSERACT_DPI` | `300` | DPI used when converting PDF pages to images |

### Environment Variables Reference

All env variables at a glance:

```env
# General
PDF_EXTRACTOR_MIN_TEXT_LENGTH=20

# AWS Textract
PDF_EXTRACTOR_AWS_REGION=us-east-1
PDF_EXTRACTOR_AWS_KEY=
PDF_EXTRACTOR_AWS_SECRET=
PDF_EXTRACTOR_AWS_VERSION=latest
PDF_EXTRACTOR_AWS_S3_BUCKET=
PDF_EXTRACTOR_AWS_S3_PREFIX=pdf-text-extractor
PDF_EXTRACTOR_AWS_ASYNC_POLL_INTERVAL_MS=1000
PDF_EXTRACTOR_AWS_ASYNC_MAX_ATTEMPTS=20
PDF_EXTRACTOR_AWS_ASYNC_DELETE_UPLOADED=true

# Tesseract
PDF_EXTRACTOR_TESSERACT_BINARY=tesseract
PDF_EXTRACTOR_GHOSTSCRIPT_BINARY=gs
PDF_EXTRACTOR_TESSERACT_LANGUAGE=eng
PDF_EXTRACTOR_TESSERACT_DPI=300
```

## Result Object

`extract()` and `extractFromString()` return an `ExtractionResult`:
- `getText()`
- `isSuccessful()`
- `getStrategy()`
- `getTextLength()`

## License

MIT
