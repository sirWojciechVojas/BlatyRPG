<?php

namespace App\Commands;

use App\Services\Compendium\CompendiumWfrp2CatalogImporter;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class SyncCompendiumWfrp2Catalog extends BaseCommand
{
    protected $group = 'Compendium';
    protected $name = 'compendium:sync-wfrp2-catalog';
    protected $description = 'Mirrors current WFRP2 professions, skills, talents and items into the Compendium.';
    protected $usage = 'compendium:sync-wfrp2-catalog [universe-code] [--user id]';

    public function run(array $params)
    {
        $result = (new CompendiumWfrp2CatalogImporter())->sync(
            (string) ($params[0] ?? 'old_world'),
            CLI::getOption('user') ? (int) CLI::getOption('user') : null
        );
        CLI::write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $result['report']['errors'] ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
