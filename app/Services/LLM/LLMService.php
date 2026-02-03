<?php

namespace App\Services\LLM;

use App\Services\LLM\Contracts\LLMDriverInterface;
use Illuminate\Support\Facades\Log;

/**
 * LLM Service - Abstrahiert die LLM-Interaktion.
 *
 * Bietet eine einheitliche Schnittstelle unabhängig vom verwendeten Backend.
 */
class LLMService
{
    public function __construct(
        private LLMDriverInterface $driver
    ) {}

    /**
     * Generiere eine Antwort basierend auf einem Prompt.
     */
    public function generate(string $prompt, array $options = []): string
    {
        Log::channel('entity')->debug('LLM generate request', [
            'model' => $this->driver->getModelName(),
            'prompt_length' => strlen($prompt),
        ]);

        $startTime = microtime(true);

        $response = $this->driver->generate($prompt, $options);

        $duration = round((microtime(true) - $startTime) * 1000);

        Log::channel('entity')->debug('LLM generate response', [
            'model' => $this->driver->getModelName(),
            'response_length' => strlen($response),
            'duration_ms' => $duration,
        ]);

        return $response;
    }

    /**
     * Generiere eine Antwort mit Chat-History.
     */
    public function chat(array $messages, array $options = []): string
    {
        Log::channel('entity')->debug('LLM chat request', [
            'model' => $this->driver->getModelName(),
            'message_count' => count($messages),
        ]);

        $startTime = microtime(true);

        $response = $this->driver->chat($messages, $options);

        $duration = round((microtime(true) - $startTime) * 1000);

        Log::channel('entity')->debug('LLM chat response', [
            'model' => $this->driver->getModelName(),
            'response_length' => strlen($response),
            'duration_ms' => $duration,
        ]);

        return $response;
    }

    /**
     * Prüfe ob das LLM verfügbar ist.
     */
    public function isAvailable(): bool
    {
        return $this->driver->isAvailable();
    }

    /**
     * Hole den Namen des verwendeten Modells.
     */
    public function getModelName(): string
    {
        return $this->driver->getModelName();
    }

    /**
     * Hole den aktuellen Driver.
     */
    public function getDriver(): LLMDriverInterface
    {
        return $this->driver;
    }

    /**
     * Generiere mit System-Prompt.
     */
    public function generateWithSystem(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        return $this->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], $options);
    }

    /**
     * Summarisiere einen Text.
     */
    public function summarize(string $text, int $maxLength = 200): string
    {
        $prompt = <<<PROMPT
Fasse den folgenden Text in maximal {$maxLength} Zeichen zusammen.
Behalte die wichtigsten Informationen bei.

Text:
{$text}

Zusammenfassung:
PROMPT;

        return $this->generate($prompt);
    }

    /**
     * Analysiere Sentiment eines Texts.
     */
    public function analyzeSentiment(string $text): float
    {
        $prompt = <<<PROMPT
Analysiere das Sentiment des folgenden Texts auf einer Skala von -1.0 (sehr negativ) bis 1.0 (sehr positiv).
Antworte NUR mit einer Zahl.

Text:
{$text}

Sentiment:
PROMPT;

        $response = $this->generate($prompt);

        // Versuche eine Zahl zu extrahieren
        preg_match('/-?\d+\.?\d*/', $response, $matches);

        if (!empty($matches)) {
            return max(-1.0, min(1.0, (float) $matches[0]));
        }

        return 0.0;
    }
}
