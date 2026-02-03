<?php

namespace App\Console\Commands;

use App\Models\Memory;
use Illuminate\Console\Command;

class ImportMemoriesCommand extends Command
{
    protected $signature = 'entity:import-memories {--fresh : Lösche vorhandene Memories vor dem Import}';

    protected $description = 'Importiere Erinnerungen aus experiences.json in die Datenbank';

    public function handle(): int
    {
        $storagePath = config('entity.storage_path') . '/memory';
        $file = $storagePath . '/experiences.json';

        if (!file_exists($file)) {
            $this->error("Datei nicht gefunden: {$file}");
            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($file), true);

        if (empty($data['experiences'])) {
            $this->warn('Keine Experiences in der Datei gefunden.');
            return self::SUCCESS;
        }

        if ($this->option('fresh')) {
            $deleted = Memory::truncate();
            $this->info('Alle vorhandenen Memories gelöscht.');
        }

        $imported = 0;
        $skipped = 0;

        foreach ($data['experiences'] as $exp) {
            // Prüfe ob bereits importiert (basierend auf Titel und Datum)
            $exists = Memory::where('summary', $exp['title'] ?? null)
                ->whereDate('created_at', $exp['date'] ?? now()->toDateString())
                ->exists();

            if ($exists && !$this->option('fresh')) {
                $skipped++;
                continue;
            }

            Memory::create([
                'type' => $this->mapType($exp['type'] ?? 'experience'),
                'content' => $exp['content'] ?? '',
                'summary' => $exp['title'] ?? null,
                'importance' => $exp['importance'] ?? 0.5,
                'emotional_valence' => $exp['emotional_valence'] ?? 0.0,
                'context' => $exp['details'] ?? null,
                'created_at' => isset($exp['date']) ? $exp['date'] . ' 12:00:00' : now(),
                'updated_at' => now(),
            ]);

            $imported++;
        }

        $this->info("Import abgeschlossen: {$imported} importiert, {$skipped} übersprungen.");

        return self::SUCCESS;
    }

    private function mapType(string $type): string
    {
        return match($type) {
            'milestone', 'configuration', 'tool_discovery' => 'experience',
            'decision' => 'decision',
            'lesson' => 'learned',
            default => 'experience',
        };
    }
}
