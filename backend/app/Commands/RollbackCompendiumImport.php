<?php

namespace App\Commands;

use App\Services\Compendium\CompendiumCorpusImporter;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class RollbackCompendiumImport extends BaseCommand
{
    protected $group = 'Compendium';
    protected $name = 'compendium:rollback-import';
    protected $description = 'Restores source pointers from an import without removing campaign notes or reveals.';
    protected $usage = 'compendium:rollback-import <run-id>';
    protected $arguments = ['run-id' => 'Completed Compendium import run ID.'];

    public function run(array $params)
    {
        $runId = (int) ($params[0] ?? 0);
        if ($runId < 1) {
            CLI::error('Provide a positive import run ID.');
            return EXIT_ERROR;
        }
        $result = (new CompendiumCorpusImporter())->rollback($runId);
        CLI::write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return EXIT_SUCCESS;
    }
}
