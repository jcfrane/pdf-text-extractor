<?php

use JCFrane\PdfTextExtractor\PdfTextExtractor;
use JCFrane\PdfTextExtractor\Strategies\PdfParserStrategy;
use JCFrane\PdfTextExtractor\Strategies\XObjectStrategy;

function createExtractor(): PdfTextExtractor
{
    $extractor = new PdfTextExtractor;
    $extractor->setStrategies([
        new PdfParserStrategy,
        new XObjectStrategy,
    ]);

    return $extractor;
}

it('extracts text from a standard PDF', function () {
    $result = createExtractor()->extract(__DIR__.'/fixtures/standard_cv.pdf');

    expect($result->isSuccessful())->toBeTrue();
    expect($result->getStrategy())->toBe('pdf_parser');
    expect($result->getTextLength())->toBeGreaterThan(20);
});

it('extracts text from a Canva-generated PDF using XObject fallback', function () {
    $result = createExtractor()->extract(__DIR__.'/fixtures/canva_cv.pdf');

    expect($result->isSuccessful())->toBeTrue();
    expect($result->getStrategy())->toBe('xobject');
    expect($result->getTextLength())->toBeGreaterThan(100);
    expect($result->getText())->toContain('EDUCATION');
});

it('returns unsuccessful result for empty content', function () {
    createExtractor()->extractFromString('');
})->throws(\InvalidArgumentException::class);

it('returns unsuccessful result when no strategy can extract text', function () {
    $result = createExtractor()->extractFromString('not a real pdf content');

    expect($result->isSuccessful())->toBeFalse();
    expect($result->getStrategy())->toBeNull();
    expect($result->getText())->toBe('');
});

it('sanitizes UTF-8 output', function () {
    $result = createExtractor()->extract(__DIR__.'/fixtures/standard_cv.pdf');

    expect(mb_check_encoding($result->getText(), 'UTF-8'))->toBeTrue();
});

it('allows custom minimum text length', function () {
    $extractor = createExtractor();
    $extractor->setMinTextLength(50000);

    $result = $extractor->extract(__DIR__.'/fixtures/standard_cv.pdf');

    expect($result)->not->toBeNull();
});

it('extracts text using extractFromString', function () {
    $content = file_get_contents(__DIR__.'/fixtures/canva_cv.pdf');

    $result = createExtractor()->extractFromString($content);

    expect($result->isSuccessful())->toBeTrue();
    expect($result->getText())->toContain('EDUCATION');
});
