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

    // Current version (for update checks)
    'version' => env('ENTITY_VERSION', '1.0.2'),

    // Think loop interval in seconds
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
            // Full access to the entire project
            'allowed_paths' => [
                base_path(),
            ],
        ],
        'bash' => [
            'enabled' => true,
            // Empty array = all commands allowed
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
            // Empty array = full access to all commands
            'allowed_commands' => [],
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
    ],

];
