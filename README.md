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

Default strategy order:
1. `PdfParserStrategy`
2. `XObjectStrategy`
3. `TextractStrategy` (optional)
4. `TesseractStrategy` (optional)

Key config values in `config/pdf-text-extractor.php`:
- `min_text_length`
- `strategies`
- `textract.*`
- `tesseract.*`

## AWS Textract in Laravel

Textract behavior:
- single-page PDF: `DetectDocumentText` (sync)
- multi-page PDF: `StartDocumentTextDetection` + `GetDocumentTextDetection` (async, via S3)

Set env values:

```env
PDF_EXTRACTOR_AWS_REGION=eu-west-2
PDF_EXTRACTOR_AWS_KEY=your_key
PDF_EXTRACTOR_AWS_SECRET=your_secret
PDF_EXTRACTOR_AWS_VERSION=latest

# Required for multi-page Textract
PDF_EXTRACTOR_AWS_S3_BUCKET=your_bucket
PDF_EXTRACTOR_AWS_S3_PREFIX=pdf-text-extractor

# Optional async tuning
PDF_EXTRACTOR_AWS_ASYNC_POLL_INTERVAL_MS=1000
PDF_EXTRACTOR_AWS_ASYNC_MAX_ATTEMPTS=60
PDF_EXTRACTOR_AWS_ASYNC_DELETE_UPLOADED=true
```

Required IAM actions:
- `textract:DetectDocumentText`
- `textract:StartDocumentTextDetection`
- `textract:GetDocumentTextDetection`
- `s3:PutObject`
- `s3:GetObject`
- `s3:DeleteObject`
- `s3:ListBucket`

Example policy (replace `YOUR_BUCKET_NAME` and prefix if needed):

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

## Result Object

`extract()` and `extractFromString()` return an `ExtractionResult`:
- `getText()`
- `isSuccessful()`
- `getStrategy()`
- `getTextLength()`

## License

MIT
