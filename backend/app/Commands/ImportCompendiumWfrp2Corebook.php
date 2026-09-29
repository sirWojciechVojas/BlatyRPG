<?php

namespace App\Commands;

use App\Services\Compendium\CompendiumWfrp2CorebookImporter;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class ImportCompendiumWfrp2Corebook extends BaseCommand
{
    protected $group = 'Compendium';
    protected $name = 'compendium:import-wfrp2-corebook';
    protected $description = 'Imports curated concepts, lore, scenario records and Bestiary profiles from the supplied WFRP2 core book.';
    protected $usage = 'compendium:import-wfrp2-corebook [corpus] [options]';
    protected $arguments = [
        'corpus' => 'Path to the generated wfrp2-corebook-pl.json corpus.',
    ];
    protected $options = [
        '--validate' => 'Validate only; do not write to the database.',
        '--universe' => 'Target RPG universe code (default: old_world).',
        '--user' => 'Existing user ID recorded as import author.',
    ];

    public function run(array $params)
    {
        $corpus = $params[0] ?? APPPATH . 'Data/Compendium/wfrp2-corebook-pl.json';
        $importer = new CompendiumWfrp2CorebookImporter();
        $validation = $importer->validate($corpus);
        CLI::write(json_encode(
            $validation,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
        if (!$validation['valid']) {
            CLI::error('Validation failed. Nothing was imported.');
            return EXIT_ERROR;
        }
        if (CLI::getOption('validate')) {
            CLI::write('Core-book corpus is valid. No database changes were made.', 'green');
            return EXIT_SUCCESS;
        }
        $result = $importer->import(
            $corpus,
            (string) (CLI::getOption('universe') ?: 'old_world'),
            CLI::getOption('user') ? (int) CLI::getOption('user') : null
        );
        CLI::write(json_encode(
            $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
        return $result['report']['errors'] ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
