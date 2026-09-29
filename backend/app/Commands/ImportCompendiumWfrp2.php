<?php

namespace App\Commands;

use App\Services\Compendium\CompendiumCorpusImporter;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class ImportCompendiumWfrp2 extends BaseCommand
{
    protected $group = 'Compendium';
    protected $name = 'compendium:import-wfrp2';
    protected $description = 'Validates or imports the complete Polish WFRP knowledge corpus.';
    protected $usage = 'compendium:import-wfrp2 [package] [options]';
    protected $arguments = [
        'package' => 'Path to BlatyRPG-Kompendium-PL.zip.',
    ];
    protected $options = [
        '--publish' => 'Persist validated source revisions (otherwise validation only).',
        '--architecture' => 'Path to BlatyRPG-Kompendium-WFRP2-Architektura.zip.',
        '--universe' => 'Target RPG universe code (default: old_world).',
        '--user' => 'Existing user ID recorded as import author.',
        '--batch' => 'Articles per transaction (default: 50, maximum: 500).',
        '--resume' => 'Resume an interrupted import run ID.',
    ];

    public function run(array $params)
    {
        $package = $params[0] ?? '/var/www/compendium/BlatyRPG-Kompendium-PL.zip';
        $architecture = CLI::getOption('architecture') ?: '/var/www/compendium/BlatyRPG-Kompendium-WFRP2-Architektura.zip';
        $importer = new CompendiumCorpusImporter();
        CLI::write('Compendium package: ' . $package);
        CLI::write('Validation reads JSONL as a stream and does not modify the corpus.');
        $validation = $importer->validatePackage($package);
        CLI::write(json_encode($validation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (!$validation['valid']) {
            CLI::error('Validation failed. Nothing was imported.');
            return EXIT_ERROR;
        }
        if (!CLI::getOption('publish')) {
            CLI::write('Validation complete. Re-run with --publish to persist the corpus.', 'green');
            return EXIT_SUCCESS;
        }
        $result = $importer->import(
            $package,
            is_file((string) $architecture) ? (string) $architecture : null,
            (string) (CLI::getOption('universe') ?: 'old_world'),
            CLI::getOption('user') ? (int) CLI::getOption('user') : null,
            max(1, min(500, (int) (CLI::getOption('batch') ?: 50))),
            CLI::getOption('resume') ? (int) CLI::getOption('resume') : null
        );
        CLI::write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $result['report']['errors'] ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
