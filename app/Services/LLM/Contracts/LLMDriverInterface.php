<?php

namespace App\Services\LLM\Contracts;

/**
 * Interface für LLM-Treiber.
 */
interface LLMDriverInterface
{
    /**
     * Generiere eine Antwort basierend auf einem Prompt.
     */
    public function generate(string $prompt, array $options = []): string;

    /**
     * Generiere eine Antwort mit Chat-History.
     */
    public function chat(array $messages, array $options = []): string;

    /**
     * Prüfe ob der Treiber verfügbar ist.
     */
    public function isAvailable(): bool;

    /**
     * Hole den Namen des verwendeten Modells.
     */
    public function getModelName(): string;
}
