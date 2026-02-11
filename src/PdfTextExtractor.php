<?php

namespace JCFrane\PdfTextExtractor;

use JCFrane\PdfTextExtractor\Contracts\ExtractionStrategy;

class PdfTextExtractor
{
    /**
     * Minimum text length to consider extraction successful
     */
    protected int $minTextLength = 20;

    /**
     * Ordered list of extraction strategies to try
     *
     * @var ExtractionStrategy[]
     */
    protected array $strategies = [];

    /**
     * Extract text from a PDF file path
     */
    public function extract(string $filePath): ExtractionResult
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$filePath}");
        }

        return $this->extractFromString($content);
    }

    /**
     * Extract text from PDF binary content
     */
    public function extractFromString(string $content): ExtractionResult
    {
        if (empty($content)) {
            throw new \InvalidArgumentException('PDF content cannot be empty');
        }

        foreach ($this->strategies as $strategy) {
            try {
                $text = $strategy->extract($content);
                $trimmedLength = strlen(trim($text));

                if ($trimmedLength >= $this->minTextLength) {
                    return new ExtractionResult(
                        text: $this->sanitizeUtf8($text),
                        strategy: $strategy->name(),
                        successful: true,
                    );
                }
            } catch (\Exception $e) {
                // Continue to next strategy
                continue;
            }
        }

        return new ExtractionResult(
            text: '',
            strategy: null,
            successful: false,
        );
    }

    /**
     * Set the minimum text length threshold
     */
    public function setMinTextLength(int $length): static
    {
        $this->minTextLength = $length;

        return $this;
    }

    /**
     * Add a custom extraction strategy
     */
    public function addStrategy(ExtractionStrategy $strategy): static
    {
        $this->strategies[] = $strategy;

        return $this;
    }

    /**
     * Prepend a custom extraction strategy (runs first)
     */
    public function prependStrategy(ExtractionStrategy $strategy): static
    {
        array_unshift($this->strategies, $strategy);

        return $this;
    }

    /**
     * Set custom strategies, replacing the defaults
     */
    public function setStrategies(array $strategies): static
    {
        $this->strategies = $strategies;

        return $this;
    }

    /**
     * Sanitize text to valid UTF-8
     */
    protected function sanitizeUtf8(string $text): string
    {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        return iconv('UTF-8', 'UTF-8//IGNORE', $text);
    }
}
