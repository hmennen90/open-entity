<?php

namespace App\Console\Commands;

use App\Services\Entity\EntityService;
use Illuminate\Console\Command;

/**
 * Sleep Command - Legt die Entität schlafen.
 */
class EntitySleep extends Command
{
    protected $signature = 'entity:sleep';

    protected $description = 'Legt die Entität schlafen';

    public function __construct(
        private EntityService $entityService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $currentStatus = $this->entityService->getStatus();

        if ($currentStatus === 'sleeping') {
            $this->info('Entity is already sleeping');
            return self::SUCCESS;
        }

        $this->entityService->sleep();
        $this->info('Entity is now sleeping');

        return self::SUCCESS;
    }
}
