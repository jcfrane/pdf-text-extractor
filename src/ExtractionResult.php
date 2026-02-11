<?php

namespace JCFrane\PdfTextExtractor;

class ExtractionResult
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $strategy,
        public readonly bool $successful,
    ) {}

    /**
     * Get the extracted text
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Check if extraction was successful
     */
    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    /**
     * Get the strategy name that successfully extracted text
     */
    public function getStrategy(): ?string
    {
        return $this->strategy;
    }

    /**
     * Get the length of the extracted text (trimmed)
     */
    public function getTextLength(): int
    {
        return strlen(trim($this->text));
    }
}
