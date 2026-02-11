<?php

use JCFrane\PdfTextExtractor\Contracts\ExtractionStrategy;
use JCFrane\PdfTextExtractor\PdfTextExtractor;
use JCFrane\PdfTextExtractor\Strategies\PdfParserStrategy;
use JCFrane\PdfTextExtractor\Strategies\XObjectStrategy;

it('pdf parser strategy extracts text from standard PDFs', function () {
    $strategy = new PdfParserStrategy;
    $content = file_get_contents(__DIR__.'/fixtures/standard_cv.pdf');

    $text = $strategy->extract($content);

    expect(strlen(trim($text)))->toBeGreaterThan(0);
});

it('xobject strategy extracts text from Canva PDFs', function () {
    $strategy = new XObjectStrategy;
    $content = file_get_contents(__DIR__.'/fixtures/canva_cv.pdf');

    $text = $strategy->extract($content);

    expect(strlen(trim($text)))->toBeGreaterThan(100);
    expect($text)->toContain('EDUCATION');
});

it('xobject strategy deduplicates repeated XObjects', function () {
    $strategy = new XObjectStrategy;
    $content = file_get_contents(__DIR__.'/fixtures/canva_cv.pdf');

    $text = $strategy->extract($content);

    // Count occurrences of "EDUCATION" - should appear once (deduplicated)
    $count = substr_count($text, 'EDUCATION');
    expect($count)->toBe(1);
});

it('allows adding custom strategies', function () {
    $customStrategy = new class implements ExtractionStrategy
    {
        public function extract(string $content): string
        {
            return 'custom extracted text from my strategy';
        }

        public function name(): string
        {
            return 'custom';
        }
    };

    $extractor = new PdfTextExtractor;
    $extractor->prependStrategy($customStrategy);

    $result = $extractor->extractFromString('any content');

    expect($result->isSuccessful())->toBeTrue();
    expect($result->getStrategy())->toBe('custom');
    expect($result->getText())->toContain('custom extracted text');
});

it('tries strategies in order and uses first successful one', function () {
    $failingStrategy = new class implements ExtractionStrategy
    {
        public function extract(string $content): string
        {
            throw new \Exception('I always fail');
        }

        public function name(): string
        {
            return 'failing';
        }
    };

    $successStrategy = new class implements ExtractionStrategy
    {
        public function extract(string $content): string
        {
            return 'successfully extracted text here';
        }

        public function name(): string
        {
            return 'success';
        }
    };

    $extractor = new PdfTextExtractor;
    $extractor->setStrategies([$failingStrategy, $successStrategy]);

    $result = $extractor->extractFromString('any content');

    expect($result->isSuccessful())->toBeTrue();
    expect($result->getStrategy())->toBe('success');
});
