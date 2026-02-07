<?php

namespace App\Exceptions;

use RuntimeException;

class NoLlmConfigurationException extends RuntimeException
{
    public function __construct(string $message = 'No LLM configurations available. Configure via API /api/v1/llm/configurations')
    {
        parent::__construct($message);
    }
}
