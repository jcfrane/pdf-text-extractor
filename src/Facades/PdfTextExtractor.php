<?php

namespace JCFrane\PdfTextExtractor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \JCFrane\PdfTextExtractor\ExtractionResult extract(string $filePath)
 * @method static \JCFrane\PdfTextExtractor\ExtractionResult extractFromString(string $content)
 * @method static \JCFrane\PdfTextExtractor\PdfTextExtractor setMinTextLength(int $length)
 * @method static \JCFrane\PdfTextExtractor\PdfTextExtractor addStrategy(\JCFrane\PdfTextExtractor\Contracts\ExtractionStrategy $strategy)
 * @method static \JCFrane\PdfTextExtractor\PdfTextExtractor prependStrategy(\JCFrane\PdfTextExtractor\Contracts\ExtractionStrategy $strategy)
 * @method static \JCFrane\PdfTextExtractor\PdfTextExtractor setStrategies(array $strategies)
 *
 * @see \JCFrane\PdfTextExtractor\PdfTextExtractor
 */
class PdfTextExtractor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'pdf-text-extractor';
    }
}
