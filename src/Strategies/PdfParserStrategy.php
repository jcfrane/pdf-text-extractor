<?php

namespace JCFrane\PdfTextExtractor\Strategies;

use JCFrane\PdfTextExtractor\Contracts\ExtractionStrategy;
use Smalot\PdfParser\Parser;

/**
 * Standard text extraction using Smalot PdfParser
 *
 * This is the primary extraction strategy that works for most PDFs
 * with standard text content (text operators like BT/ET, Tj/TJ).
 */
class PdfParserStrategy implements ExtractionStrategy
{
    public function extract(string $content): string
    {
        $parser = new Parser;
        $pdf = $parser->parseContent($content);

        return $pdf->getText();
    }

    public function name(): string
    {
        return 'pdf_parser';
    }
}
