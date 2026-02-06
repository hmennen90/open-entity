<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Entity Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the autonomous AI entity
    |
    */

    // Name of the entity
    'name' => env('ENTITY_NAME', 'OpenEntity'),

    // Current version (read from VERSION file, updated by semantic-release)
    'version' => env('ENTITY_VERSION', trim(file_get_contents(base_path('VERSION')))),

    // Language for thoughts, memories, and system prompts (de/en)
    'language' => env('ENTITY_LANGUAGE', 'de'),

    // Think loop configuration
    'think' => [
        // Interval when idle (no recent conversation)
        'idle_interval' => (int) env('ENTITY_THINK_IDLE_INTERVAL', 5),

        // Interval during active conversation
        'active_interval' => (int) env('ENTITY_THINK_ACTIVE_INTERVAL', 60),

        // Seconds of inactivity before considered "idle"
        'activity_timeout' => (int) env('ENTITY_ACTIVITY_TIMEOUT', 120),
    ],

    // Sleep configuration
    'sleep' => [
        // Maximum sleep duration in hours (0 = unlimited)
        'max_duration_hours' => (float) env('ENTITY_SLEEP_MAX_HOURS', 8),

        // Wake up when energy reaches this level (0-1, 0 = disabled)
        'wake_on_energy' => (float) env('ENTITY_WAKE_ON_ENERGY', 1.0),

        // Wake up when receiving a chat message
        'wake_on_message' => env('ENTITY_WAKE_ON_MESSAGE', true),
    ],

    // Legacy support - deprecated, use think.idle_interval instead
    'think_interval' => (int) env('ENTITY_THINK_INTERVAL', 30),

    // Storage path for Mind & Memory
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
                'timeout' => (int) env('OLLAMA_TIMEOUT', 600), // 10 minutes for CPU-based inference
                'options' => [
                    'temperature' => 0.8,
                    'top_p' => 0.9,
                    'num_ctx' => (int) env('OLLAMA_NUM_CTX', 4096),
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
                    'max_tokens' => env('OPENROUTER_MAX_TOKENS', 4096),
                ],
            ],
            'nvidia' => [
                'api_key' => env('NVIDIA_API_KEY'),
                'model' => env('NVIDIA_MODEL', 'moonshotai/kimi-k2.5'),
                'timeout' => 300, // 5 minutes for "thinking" models like kimi-k2.5
                'options' => [
                    'temperature' => 1.0,
                    'top_p' => 1.0,
                    'max_tokens' => env('NVIDIA_MAX_TOKENS', 4096),
                ],
            ]
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mind Configuration
    |--------------------------------------------------------------------------
    */

    'mind' => [
        // Maximum number of recent memories in context
        'max_recent_memories' => 20,

        // Maximum number of recent thoughts
        'max_recent_thoughts' => 10,

        // Reflection interval (deeper reflection every X think cycles)
        'reflection_interval' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Memory Configuration
    |--------------------------------------------------------------------------
    |
    | Human-like memory system with 4 layers:
    | - Core Identity (always loaded)
    | - Semantic Memory (learned knowledge, index-based search)
    | - Episodic Memory (experiences, vector-based search)
    | - Working Memory (current context, in-memory)
    |
    */

    'memory' => [
        // Embedding configuration for semantic search
        'embedding' => [
            'driver' => env('MEMORY_EMBEDDING_DRIVER', 'ollama'),

            'drivers' => [
                'ollama' => [
                    'base_url' => env('EMBEDDING_OLLAMA_BASE_URL', env('OLLAMA_BASE_URL', 'http://localhost:11434')),
                    'model' => env('EMBEDDING_OLLAMA_MODEL', 'nomic-embed-text'),
                    'timeout' => (int) env('OLLAMA_EMBEDDING_TIMEOUT', 120),
                    'dimensions' => 768,
                ],
                'openai' => [
                    'api_key' => env('OPENAI_API_KEY'),
                    'model' => env('EMBEDDING_OPENAI_MODEL', 'text-embedding-3-small'),
                    'timeout' => 30,
                    'dimensions' => 1536,
                ],
                'openrouter' => [
                    'api_key' => env('OPENROUTER_API_KEY'),
                    'model' => env('EMBEDDING_OPENROUTER_MODEL', 'openai/text-embedding-3-small'),
                    'timeout' => 30,
                    'dimensions' => 1536,
                ],
            ],
        ],

        // Layer-specific settings
        'layers' => [
            'working' => [
                'max_items' => 20,
                'ttl_minutes' => 60,
            ],
            'episodic' => [
                'max_in_context' => 10,
                'similarity_threshold' => 0.7,
            ],
            'semantic' => [
                'max_in_context' => 5,
            ],
        ],

        // Consolidation settings (like sleep for memory)
        'consolidation' => [
            'enabled' => env('MEMORY_CONSOLIDATION_ENABLED', true),
            'schedule' => '0 3 * * *',  // 3 AM daily
            'archive_after_days' => 30,
        ],

        // Token budget allocation for context building
        'context_budget' => [
            'total' => 4000,
            'core_identity' => 500,
            'working_memory' => 1000,
            'episodic' => 1500,
            'semantic' => 1000,
        ],
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
    | Which tools are available to the entity
    |
    */

    'tools' => [
        'filesystem' => [
            'enabled' => true,
            // Restricted to entity storage by default - extend via ENTITY_FILESYSTEM_PATHS env
            'allowed_paths' => array_filter([
                storage_path('entity'),
                env('ENTITY_FILESYSTEM_EXTRA_PATH'),
            ]),
        ],
        'bash' => [
            'enabled' => (bool) env('ENTITY_BASH_ENABLED', true),
            // null = default whitelist in BashTool, empty array [] from env would allow all
            'allowed_commands' => null,
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
            // null = use default whitelist defined in ArtisanTool
            'allowed_commands' => null,
        ],
        'search' => [
            'enabled' => true,
            'timeout' => 15,
        ],
        'personality' => [
            'enabled' => true,
        ],
        'update_check' => [
            'enabled' => true,
            'timeout' => 10,
            'repository' => env('ENTITY_UPDATE_REPOSITORY', 'hmennen90/open-entity'),
        ],
        'user_preferences' => [
            'enabled' => true,
        ],
        'goal' => [
            'enabled' => true,
            'similarity_threshold' => 60, // 0-100, goals with higher similarity are considered duplicates
        ],
    ],

];
