<?php

namespace App\Commands;

use App\Services\MapBuilder\MapBuilderService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class RunMapAiWorker extends BaseCommand
{
    protected $group = 'Map builder';
    protected $name = 'maps:ai-worker';
    protected $description = 'Processes queued map-builder AI layout and image generation jobs.';
    protected $usage = 'maps:ai-worker [--once]';

    public function run(array $params)
    {
        $once = CLI::getOption('once') !== null;
        $service = new MapBuilderService();
        do {
            try {
                $processed = $service->processNextAiJob();
            } catch (\Throwable $exception) {
                CLI::error('Map AI worker error: ' . $exception->getMessage());
                $processed = false;
            }
            if ($once) return EXIT_SUCCESS;
            if (!$processed) sleep(2);
        } while (true);
    }
}
