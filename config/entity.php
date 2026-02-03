<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Entity Configuration
    |--------------------------------------------------------------------------
    |
    | Konfiguration für die autonome KI-Entität
    |
    */

    // Name der Entität
    'name' => env('ENTITY_NAME', 'OpenEntity'),

    // Think Loop Intervall in Sekunden
    'think_interval' => (int) env('ENTITY_THINK_INTERVAL', 30),

    // Storage-Pfad für Mind & Memory
    'storage_path' => storage_path('entity'),

    /*
    |--------------------------------------------------------------------------
    | LLM Configuration
    |--------------------------------------------------------------------------
    */

    'llm' => [
        'default' => env('ENTITY_LLM_DRIVER', 'ollama'),

        'drivers' => [
            'ollama' => [
                'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
                'model' => env('OLLAMA_MODEL', 'qwen-coder:30b'),
                'timeout' => 300, // 5 Minuten für CPU-basierte Inferenz
                'options' => [
                    'temperature' => 0.8,
                    'top_p' => 0.9,
                    'num_ctx' => 8192,
                ],
            ],
            'openai' => [
                'api_key' => env('OPENAI_API_KEY'),
                'model' => env('OPENAI_MODEL', 'gpt-4o'),
                'timeout' => 60,
                'options' => [
                    'temperature' => 0.8,
                    'max_tokens' => 4096,
                ],
            ],
            'openrouter' => [
                'api_key' => env('OPENROUTER_API_KEY'),
                'model' => env('OPENROUTER_MODEL', 'openrouter/auto'),
                'timeout' => 120,
                'app_name' => env('APP_NAME', 'OpenEntity'),
                'app_url' => env('APP_URL', 'http://localhost'),
                'options' => [
                    'temperature' => 0.8,
                    'max_tokens' => 4096,
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mind Configuration
    |--------------------------------------------------------------------------
    */

    'mind' => [
        // Maximale Anzahl aktueller Erinnerungen im Kontext
        'max_recent_memories' => 20,

        // Maximale Anzahl aktueller Gedanken
        'max_recent_thoughts' => 10,

        // Reflexions-Intervall (alle X Think-Zyklen eine tiefere Reflexion)
        'reflection_interval' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Configuration
    |--------------------------------------------------------------------------
    */

    'social' => [
        'moltbook' => [
            'enabled' => env('MOLTBOOK_ENABLED', false),
            'api_url' => env('MOLTBOOK_API_URL'),
        ],
        'discord' => [
            'enabled' => env('DISCORD_ENABLED', false),
            'bot_token' => env('DISCORD_BOT_TOKEN'),
            'guild_id' => env('DISCORD_GUILD_ID'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tools Configuration
    |--------------------------------------------------------------------------
    |
    | Welche Tools der Entität zur Verfügung stehen
    |
    */

    'tools' => [
        'filesystem' => [
            'enabled' => true,
            // Voller Zugriff auf das gesamte Projekt
            'allowed_paths' => [
                base_path(),
            ],
        ],
        'bash' => [
            'enabled' => true,
            // Leeres Array = alle Commands erlaubt
            'allowed_commands' => [],
        ],
        'web' => [
            'enabled' => true,
            'timeout' => 30,
        ],
        'documentation' => [
            'enabled' => true,
        ],
        'artisan' => [
            'enabled' => true,
            // Leeres Array = voller Zugriff auf alle Commands
            'allowed_commands' => [],
        ],
    ],

];
