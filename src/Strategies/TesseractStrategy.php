<?php

namespace JCFrane\PdfTextExtractor\Strategies;

use JCFrane\PdfTextExtractor\Contracts\ExtractionStrategy;

/**
 * Extract text using Tesseract OCR (via command line)
 *
 * Converts PDF pages to images using Ghostscript, then runs Tesseract OCR
 * on each page image. This is a fully open-source OCR solution that works
 * offline without any cloud dependencies.
 *
 * System requirements:
 * - `tesseract` CLI tool (https://github.com/tesseract-ocr/tesseract)
 * - `ghostscript` (gs) CLI tool for PDF-to-image conversion
 *
 * Install on Ubuntu/Debian:
 *   apt-get install tesseract-ocr ghostscript
 *
 * Install on macOS:
 *   brew install tesseract ghostscript
 */
class TesseractStrategy implements ExtractionStrategy
{
    protected string $tesseractBinary;

    protected string $ghostscriptBinary;

    protected string $language;

    protected int $dpi;

    public function __construct(
        string $tesseractBinary = 'tesseract',
        string $ghostscriptBinary = 'gs',
        string $language = 'eng',
        int $dpi = 300,
    ) {
        $this->tesseractBinary = $tesseractBinary;
        $this->ghostscriptBinary = $ghostscriptBinary;
        $this->language = $language;
        $this->dpi = $dpi;
    }

    public function extract(string $content): string
    {
        $this->assertDependencies();

        $tempDir = sys_get_temp_dir().'/pdf-text-extractor-'.uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $pdfPath = $tempDir.'/input.pdf';
            file_put_contents($pdfPath, $content);

            // Convert PDF pages to PNG images using Ghostscript
            $this->convertPdfToImages($pdfPath, $tempDir);

            // OCR each page image
            $text = $this->ocrImages($tempDir);

            return $text;
        } finally {
            $this->cleanup($tempDir);
        }
    }

    public function name(): string
    {
        return 'tesseract';
    }

    /**
     * Convert PDF to page images using Ghostscript
     */
    protected function convertPdfToImages(string $pdfPath, string $outputDir): void
    {
        $outputPattern = $outputDir.'/page-%03d.png';

        $command = sprintf(
            '%s -dNOPAUSE -dBATCH -sDEVICE=png16m -r%d -sOutputFile=%s %s 2>&1',
            escapeshellcmd($this->ghostscriptBinary),
            $this->dpi,
            escapeshellarg($outputPattern),
            escapeshellarg($pdfPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException(
                'Ghostscript PDF-to-image conversion failed: '.implode("\n", $output)
            );
        }
    }

    /**
     * Run Tesseract OCR on each page image
     */
    protected function ocrImages(string $imageDir): string
    {
        $images = glob($imageDir.'/page-*.png');
        sort($images);

        if (empty($images)) {
            throw new \RuntimeException('No page images generated from PDF');
        }

        $text = '';

        foreach ($images as $imagePath) {
            $outputBase = $imagePath.'.out';

            $command = sprintf(
                '%s %s %s -l %s 2>&1',
                escapeshellcmd($this->tesseractBinary),
                escapeshellarg($imagePath),
                escapeshellarg($outputBase),
                escapeshellarg($this->language)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \RuntimeException(
                    'Tesseract OCR failed: '.implode("\n", $output)
                );
            }

            $outputFile = $outputBase.'.txt';
            if (file_exists($outputFile)) {
                $text .= file_get_contents($outputFile)."\n";
            }
        }

        return $text;
    }

    /**
     * Check that required CLI tools are installed
     */
    protected function assertDependencies(): void
    {
        $tesseractCheck = shell_exec(sprintf('which %s 2>/dev/null', escapeshellarg($this->tesseractBinary)));
        if (empty(trim($tesseractCheck ?? ''))) {
            throw new \RuntimeException(
                "Tesseract OCR is not installed. Install it with: apt-get install tesseract-ocr (Ubuntu) or brew install tesseract (macOS)"
            );
        }

        $gsCheck = shell_exec(sprintf('which %s 2>/dev/null', escapeshellarg($this->ghostscriptBinary)));
        if (empty(trim($gsCheck ?? ''))) {
            throw new \RuntimeException(
                "Ghostscript is not installed. Install it with: apt-get install ghostscript (Ubuntu) or brew install ghostscript (macOS)"
            );
        }
    }

    /**
     * Clean up temporary files
     */
    protected function cleanup(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = glob($dir.'/*');
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
}
