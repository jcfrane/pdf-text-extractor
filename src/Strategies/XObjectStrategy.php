<?php

namespace JCFrane\PdfTextExtractor\Strategies;

use JCFrane\PdfTextExtractor\Contracts\ExtractionStrategy;
use Smalot\PdfParser\Parser;

/**
 * Extract text from PDF Form XObjects
 *
 * Some PDF generators (notably Canva) render text as vector paths inside
 * Form XObjects rather than using standard text operators on the page.
 * Standard getText() returns empty for these PDFs, but the text is
 * accessible through getXObjects() on each page.
 *
 * This strategy iterates over each page's XObjects and extracts text
 * from them, which successfully handles Canva-generated PDFs and
 * similar tools that use Form XObjects for text rendering.
 */
class XObjectStrategy implements ExtractionStrategy
{
    public function extract(string $content): string
    {
        $parser = new Parser;
        $pdf = $parser->parseContent($content);
        $text = '';

        foreach ($pdf->getPages() as $page) {
            $seen = [];

            foreach ($page->getXObjects() as $xObject) {
                $xObjectText = $xObject->getText();

                // Deduplicate — some PDFs reference the same XObject multiple times
                $hash = md5($xObjectText);
                if (isset($seen[$hash])) {
                    continue;
                }
                $seen[$hash] = true;

                $text .= $xObjectText."\n";
            }
        }

        return $text;
    }

    public function name(): string
    {
        return 'xobject';
    }
}
