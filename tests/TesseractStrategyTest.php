<?php

use JCFrane\PdfTextExtractor\Strategies\TesseractStrategy;

function createTesseractStrategy(): TesseractStrategy
{
    return new TesseractStrategy(
        tesseractBinary: getenv('PDF_EXTRACTOR_TESSERACT_BINARY') ?: 'tesseract',
        ghostscriptBinary: getenv('PDF_EXTRACTOR_GHOSTSCRIPT_BINARY') ?: 'gs',
        language: getenv('PDF_EXTRACTOR_TESSERACT_LANGUAGE') ?: 'eng',
        dpi: (int) (getenv('PDF_EXTRACTOR_TESSERACT_DPI') ?: 300),
    );
}

function ensureTesseractDependencies(): void
{
    $tesseractPath = trim((string) shell_exec('command -v tesseract 2>/dev/null'));
    if ($tesseractPath === '') {
        test()->markTestSkipped("Tesseract ('tesseract') not found in PATH.");
    }

    $ghostscriptPath = trim((string) shell_exec('command -v gs 2>/dev/null'));
    if ($ghostscriptPath === '') {
        test()->markTestSkipped("Ghostscript ('gs') not found in PATH.");
    }
}

it('tesseract strategy extracts text from a single-page PDF', function () {
    ensureTesseractDependencies();

    $strategy = createTesseractStrategy();

    $content = file_get_contents(__DIR__.'/fixtures/standard_cv_single_page.pdf');
    $text = $strategy->extract($content);

    expect(strlen(trim($text)))->toBeGreaterThan(0)
        ->and($strategy->name())->toBe('tesseract');
});

it('tesseract strategy extracts text from a multi-page PDF', function () {
    ensureTesseractDependencies();

    $strategy = createTesseractStrategy();

    $content = file_get_contents(__DIR__.'/fixtures/standard_cv.pdf');
    $text = $strategy->extract($content);

    expect(strlen(trim($text)))->toBeGreaterThan(0)
        ->and($strategy->name())->toBe('tesseract');
});
