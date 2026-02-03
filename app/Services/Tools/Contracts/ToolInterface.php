<?php

namespace App\Services\Tools\Contracts;

/**
 * Interface für alle Tools die der Entität zur Verfügung stehen.
 */
interface ToolInterface
{
    /**
     * Eindeutiger Name des Tools.
     */
    public function name(): string;

    /**
     * Beschreibung was das Tool tut (für LLM-Kontext).
     */
    public function description(): string;

    /**
     * Parameter-Schema für das Tool (für LLM Function Calling).
     */
    public function parameters(): array;

    /**
     * Führe das Tool aus.
     *
     * @param array $params Die Parameter für die Ausführung
     * @return array ['success' => bool, 'result' => mixed, 'error' => string|null]
     */
    public function execute(array $params): array;

    /**
     * Validiere die Parameter vor der Ausführung.
     *
     * @param array $params
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validate(array $params): array;
}
