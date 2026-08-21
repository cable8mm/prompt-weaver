<?php

namespace Cable8mm\PromptWeaver\Contracts;

interface PromptInterface
{
    /**
     * Build the prompt string and store it internally.
     */
    public function build(): void;

    /**
     * Return the generated prompt string, or null if build() has not been called.
     */
    public function prompt(): ?string;
}
