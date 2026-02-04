<?php

namespace App\Console\Commands;

use App\Services\Entity\EnergyService;
use Illuminate\Console\Command;

class EntityEnergyCommand extends Command
{
    protected $signature = 'entity:energy
                            {--set= : Set energy to specific value (0-100)}
                            {--reset : Reset energy system}
                            {--log : Show energy change log}';

    protected $description = 'View and manage entity energy levels';

    public function handle(EnergyService $energyService): int
    {
        if ($this->option('reset')) {
            $energyService->reset();
            $this->info('Energy system reset.');
            return self::SUCCESS;
        }

        if ($setValue = $this->option('set')) {
            $value = (float) $setValue / 100;
            $energyService->setEnergy($value, 'manual_set');
            $this->info("Energy set to {$setValue}%");
        }

        // Show current state
        $state = $energyService->getEnergyState();

        $this->newLine();
        $this->info('⚡ Entity Energy Status');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Energy Level', sprintf('%.1f%%', $state['percent'])],
                ['State', $state['state']],
                ['Hours Awake', sprintf('%.1f hours', $state['hours_awake'])],
                ['Needs Sleep', $state['needs_sleep'] ? 'Yes ⚠️' : 'No'],
                ['Description', $state['description']],
            ]
        );

        // Show visual bar
        $barWidth = 40;
        $filledWidth = (int) round($state['level'] * $barWidth);
        $emptyWidth = $barWidth - $filledWidth;

        $color = match (true) {
            $state['level'] >= 0.7 => 'green',
            $state['level'] >= 0.5 => 'blue',
            $state['level'] >= 0.3 => 'yellow',
            default => 'red',
        };

        $this->newLine();
        $this->line(sprintf(
            'Energy: [<fg=%s>%s</><fg=gray>%s</>] %d%%',
            $color,
            str_repeat('█', $filledWidth),
            str_repeat('░', $emptyWidth),
            $state['percent']
        ));

        // Show log if requested
        if ($this->option('log')) {
            $this->newLine();
            $this->info('Recent Energy Changes:');

            $log = $energyService->getEnergyLog(15);

            if (empty($log)) {
                $this->line('  No energy changes recorded yet.');
            } else {
                $rows = [];
                foreach (array_reverse($log) as $entry) {
                    $delta = $entry['delta'];
                    $deltaStr = $delta >= 0 ? "+{$delta}" : "{$delta}";
                    $rows[] = [
                        substr($entry['time'], 11, 8), // Just time part
                        $deltaStr,
                        $entry['reason'],
                        sprintf('%.1f%% → %.1f%%', $entry['from'] * 100, $entry['to'] * 100),
                    ];
                }

                $this->table(
                    ['Time', 'Delta', 'Reason', 'Change'],
                    $rows
                );
            }
        }

        return self::SUCCESS;
    }
}
