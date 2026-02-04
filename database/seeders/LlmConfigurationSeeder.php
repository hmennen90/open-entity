<?php

namespace Database\Seeders;

use App\Models\LlmConfiguration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class LlmConfigurationSeeder extends Seeder
{
    /**
     * Seed the default LLM configurations.
     *
     * Creates a working Ollama configuration and prepares slots for other providers.
     */
    public function run(): void
    {
        // Nur seeden wenn keine Konfigurationen existieren
        if (LlmConfiguration::count() > 0) {
            $this->command->info('LLM configurations already exist, skipping...');
            return;
        }

        $this->command->info('Seeding LLM configurations...');

        // Ollama-Modell ermitteln
        $ollamaModel = $this->detectOllamaModel();

        // Standard Ollama Konfiguration (lokal im Docker)
        LlmConfiguration::create([
            'name' => 'Ollama (Lokal)',
            'driver' => 'ollama',
            'model' => $ollamaModel,
            'base_url' => env('OLLAMA_BASE_URL', 'http://ollama:11434'),
            'is_active' => true,
            'is_default' => true,
            'priority' => 100,
            'options' => [
                'temperature' => 0.8,
                'top_p' => 0.9,
                'num_ctx' => 8192,
            ],
        ]);

        $this->command->info("  ✓ Ollama (Lokal) - Default: {$ollamaModel}");

        $this->command->info('LLM configurations seeded successfully!');
    }

    /**
     * Detect the best available Ollama model.
     *
     * Priority:
     * 1. OLLAMA_MODEL environment variable
     * 2. Query Ollama API for installed models, pick best qwen2.5 variant
     * 3. Fallback to default model
     */
    private function detectOllamaModel(): string
    {
        // 1. Explizit gesetzte Umgebungsvariable
        if (!empty(env('OLLAMA_MODEL'))) {
            return env('OLLAMA_MODEL');
        }

        // 2. Ollama API abfragen
        $ollamaUrl = env('OLLAMA_BASE_URL', 'http://ollama:11434');

        try {
            $response = Http::timeout(10)->get("{$ollamaUrl}/api/tags");

            if ($response->successful()) {
                $models = $response->json('models', []);

                // Beste qwen2.5 Variante finden (nach Größe sortiert)
                $preferredModels = [
                    'qwen2.5:72b-instruct-q5_K_M',
                    'qwen2.5:72b-instruct-q4_K_M',
                    'qwen2.5:32b-instruct-q5_K_M',
                    'qwen2.5:32b-instruct-q4_K_M',
                    'qwen2.5:14b-instruct-q5_K_M',
                    'qwen2.5:14b-instruct-q4_K_M',
                    'qwen2.5:7b-instruct-q5_K_M',
                    'qwen2.5:7b-instruct-q4_K_M',
                ];

                $installedNames = array_column($models, 'name');

                foreach ($preferredModels as $preferred) {
                    if (in_array($preferred, $installedNames)) {
                        $this->command->info("  → Detected installed model: {$preferred}");
                        return $preferred;
                    }
                }

                // Falls kein bevorzugtes Modell, erstes verfügbares nutzen
                if (!empty($models)) {
                    $firstModel = $models[0]['name'];
                    $this->command->info("  → Using first available model: {$firstModel}");
                    return $firstModel;
                }
            }
        } catch (\Exception $e) {
            $this->command->warn("  → Could not query Ollama: {$e->getMessage()}");
        }

        // 3. Fallback
        $fallback = 'qwen2.5:14b-instruct-q5_K_M';
        $this->command->info("  → Using fallback model: {$fallback}");
        return $fallback;
    }
}
