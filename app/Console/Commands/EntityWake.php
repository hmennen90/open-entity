<?php

namespace App\Console\Commands;

use App\Services\Entity\EntityService;
use Illuminate\Console\Command;

/**
 * Wake Command - Weckt die Entität auf.
 */
class EntityWake extends Command
{
    protected $signature = 'entity:wake';

    protected $description = 'Weckt die Entität auf';

    public function __construct(
        private EntityService $entityService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $currentStatus = $this->entityService->getStatus();

        if ($currentStatus === 'awake') {
            $this->info('Entity is already awake');
            return self::SUCCESS;
        }

        $this->entityService->wake();
        $this->info('Entity is now awake');

        return self::SUCCESS;
    }
}
