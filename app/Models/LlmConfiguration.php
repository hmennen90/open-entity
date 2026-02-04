<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * LlmConfiguration - Stores LLM provider configurations.
 *
 * Allows the entity to switch between different LLM providers
 * and automatically fall back to alternatives if one fails.
 */
class LlmConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'driver',
        'model',
        'api_key',
        'base_url',
        'is_active',
        'is_default',
        'priority',
        'options',
        'last_error',
        'last_used_at',
        'last_error_at',
        'error_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'priority' => 'integer',
        'options' => 'array',
        'last_used_at' => 'datetime',
        'last_error_at' => 'datetime',
        'error_count' => 'integer',
    ];

    protected $hidden = [
        'api_key',
    ];

    /**
     * Encrypt API key when setting.
     */
    public function setApiKeyAttribute(?string $value): void
    {
        $this->attributes['api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt API key when getting.
     */
    public function getApiKeyAttribute(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Scope for active configurations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope ordered by priority (highest first).
     */
    public function scopeByPriority($query)
    {
        return $query->orderByDesc('priority');
    }

    /**
     * Scope for the default configuration.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Mark this configuration as successfully used.
     */
    public function markUsed(): void
    {
        $this->update([
            'last_used_at' => now(),
            'error_count' => 0, // Reset error count on success
        ]);
    }

    /**
     * Mark this configuration as having an error.
     */
    public function markError(string $error): void
    {
        $this->update([
            'last_error' => $error,
            'last_error_at' => now(),
            'error_count' => $this->error_count + 1,
        ]);
    }

    /**
     * Check if this configuration should be skipped (circuit breaker).
     * Skip if there have been too many recent errors.
     */
    public function shouldSkip(): bool
    {
        // Skip if more than 3 errors in the last 5 minutes
        if ($this->error_count >= 3 && $this->last_error_at) {
            return $this->last_error_at->diffInMinutes(now()) < 5;
        }

        return false;
    }

    /**
     * Get the display status of this configuration.
     */
    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'disabled';
        }

        if ($this->shouldSkip()) {
            return 'error';
        }

        if ($this->last_used_at && $this->last_used_at->diffInMinutes(now()) < 5) {
            return 'active';
        }

        return 'ready';
    }

    /**
     * Convert to driver config array for driver instantiation.
     * Merges database options with defaults from config file.
     */
    public function toDriverConfig(): array
    {
        // Get defaults from config file for this driver
        $defaults = config("entity.llm.drivers.{$this->driver}", []);

        $config = [
            'model' => $this->model,
            'timeout' => $defaults['timeout'] ?? 120,
        ];

        // Merge options: database options override defaults
        $defaultOptions = $defaults['options'] ?? [];
        $dbOptions = $this->options ?? [];
        $config['options'] = array_merge($defaultOptions, $dbOptions);

        if ($this->api_key) {
            $config['api_key'] = $this->api_key;
        }

        if ($this->base_url) {
            $config['base_url'] = $this->base_url;
        } elseif (isset($defaults['base_url'])) {
            // Use default base URL for drivers like Ollama
            $config['base_url'] = $defaults['base_url'];
        }

        // Include app_name and app_url for drivers that need them
        if (in_array($this->driver, ['openrouter'])) {
            $config['app_name'] = $defaults['app_name'] ?? config('app.name', 'OpenEntity');
            $config['app_url'] = $defaults['app_url'] ?? config('app.url', 'http://localhost');
        }

        return $config;
    }
}
