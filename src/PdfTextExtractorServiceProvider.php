<?php

namespace JCFrane\PdfTextExtractor;

use Illuminate\Support\ServiceProvider;
use JCFrane\PdfTextExtractor\Strategies\TesseractStrategy;
use JCFrane\PdfTextExtractor\Strategies\TextractStrategy;

class PdfTextExtractorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/pdf-text-extractor.php', 'pdf-text-extractor'
        );

        $this->app->singleton(PdfTextExtractor::class, function ($app) {
            $config = $app['config']['pdf-text-extractor'];

            $extractor = new PdfTextExtractor;
            $extractor->setMinTextLength($config['min_text_length'] ?? 20);

            $strategies = [];

            foreach ($config['strategies'] ?? [] as $strategyClass) {
                $strategies[] = $this->resolveStrategy($strategyClass, $config);
            }

            $extractor->setStrategies($strategies);

            return $extractor;
        });

        $this->app->alias(PdfTextExtractor::class, 'pdf-text-extractor');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/pdf-text-extractor.php' => config_path('pdf-text-extractor.php'),
        ], 'pdf-text-extractor-config');
    }

    protected function resolveStrategy(string $strategyClass, array $config): object
    {
        if ($strategyClass === TextractStrategy::class) {
            return new TextractStrategy(
                region: $config['textract']['region'] ?? 'us-east-1',
                key: $config['textract']['key'] ?? '',
                secret: $config['textract']['secret'] ?? '',
                version: $config['textract']['version'] ?? 'latest',
                s3Bucket: $config['textract']['s3_bucket'] ?? null,
                s3Prefix: $config['textract']['s3_prefix'] ?? 'pdf-text-extractor',
                asyncPollIntervalMs: (int) ($config['textract']['async_poll_interval_ms'] ?? 1000),
                asyncMaxAttempts: (int) ($config['textract']['async_max_attempts'] ?? 60),
                deleteUploadedDocument: (bool) ($config['textract']['async_delete_uploaded'] ?? true),
            );
        }

        if ($strategyClass === TesseractStrategy::class) {
            return new TesseractStrategy(
                tesseractBinary: $config['tesseract']['binary'] ?? 'tesseract',
                ghostscriptBinary: $config['tesseract']['ghostscript_binary'] ?? 'gs',
                language: $config['tesseract']['language'] ?? 'eng',
                dpi: $config['tesseract']['dpi'] ?? 300,
            );
        }

        return new $strategyClass;
    }
}
