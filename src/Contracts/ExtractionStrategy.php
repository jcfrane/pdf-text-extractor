<?php

namespace JCFrane\PdfTextExtractor\Contracts;

interface ExtractionStrategy
{
    /**
     * Extract text from PDF binary content
     *
     * @param  string  $content  Raw PDF file contents
     * @return string Extracted text
     *
     * @throws \Exception If extraction fails
     */
    public function extract(string $content): string;

    /**
     * Get the human-readable name of this strategy
     */
    public function name(): string;
}
