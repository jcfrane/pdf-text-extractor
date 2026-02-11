<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum Text Length
    |--------------------------------------------------------------------------
    |
    | The minimum number of characters an extraction must produce to be
    | considered successful. If a strategy returns fewer characters than
    | this threshold, the next strategy in the list will be tried.
    |
    */

    'min_text_length' => env('PDF_EXTRACTOR_MIN_TEXT_LENGTH', 20),

    /*
    |--------------------------------------------------------------------------
    | Extraction Strategies
    |--------------------------------------------------------------------------
    |
    | The ordered list of extraction strategies to try. Each strategy will be
    | attempted in order until one produces text that meets the minimum length.
    |
    | Available built-in strategies:
    |   - JCFrane\PdfTextExtractor\Strategies\PdfParserStrategy
    |   - JCFrane\PdfTextExtractor\Strategies\XObjectStrategy
    |   - JCFrane\PdfTextExtractor\Strategies\TextractStrategy
    |   - JCFrane\PdfTextExtractor\Strategies\TesseractStrategy
    |
    */

    'strategies' => [
        JCFrane\PdfTextExtractor\Strategies\PdfParserStrategy::class,
        JCFrane\PdfTextExtractor\Strategies\XObjectStrategy::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | AWS Textract Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the AWS Textract OCR strategy. These credentials are
    | only required if you include TextractStrategy in your strategies list.
    |
    | Requires the `aws/aws-sdk-php` package: composer require aws/aws-sdk-php
    |
    */

    'textract' => [
        'region' => env('PDF_EXTRACTOR_AWS_REGION', 'us-east-1'),
        'key' => env('PDF_EXTRACTOR_AWS_KEY'),
        'secret' => env('PDF_EXTRACTOR_AWS_SECRET'),
        'version' => env('PDF_EXTRACTOR_AWS_VERSION', 'latest'),

        // Required for multi-page PDFs (Textract async API)
        's3_bucket' => env('PDF_EXTRACTOR_AWS_S3_BUCKET'),
        's3_prefix' => env('PDF_EXTRACTOR_AWS_S3_PREFIX', 'pdf-text-extractor'),

        // Async job polling settings for multi-page PDFs
        'async_poll_interval_ms' => (int) env('PDF_EXTRACTOR_AWS_ASYNC_POLL_INTERVAL_MS', 1000),
        'async_max_attempts' => (int) env('PDF_EXTRACTOR_AWS_ASYNC_MAX_ATTEMPTS', 20),
        'async_delete_uploaded' => (bool) env('PDF_EXTRACTOR_AWS_ASYNC_DELETE_UPLOADED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tesseract OCR Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Tesseract OCR strategy. These settings are only
    | required if you include TesseractStrategy in your strategies list.
    |
    | Requires `tesseract` and `ghostscript` to be installed on the system.
    |
    */

    'tesseract' => [
        'binary' => env('PDF_EXTRACTOR_TESSERACT_BINARY', 'tesseract'),
        'ghostscript_binary' => env('PDF_EXTRACTOR_GHOSTSCRIPT_BINARY', 'gs'),
        'language' => env('PDF_EXTRACTOR_TESSERACT_LANGUAGE', 'eng'),
        'dpi' => (int) env('PDF_EXTRACTOR_TESSERACT_DPI', 300),
    ],

];
