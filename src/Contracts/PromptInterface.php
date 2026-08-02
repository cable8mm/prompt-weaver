<?php

namespace Cable8mm\PromptWeaver\Contracts;

use Cable8mm\NanoAI\Client;

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

    /**
     * Send the prompt to an AI model via the NanoAI client and store the response.
     *
     * @return mixed The parsed AI response (array for JSON responses, string for raw text)
     */
    public function execute(Client $client): mixed;

    /**
     * Return the AI response stored by execute(), or null if execute() has not been called.
     */
    public function response(): mixed;
}
