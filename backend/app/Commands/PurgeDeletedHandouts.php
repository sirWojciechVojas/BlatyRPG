<?php

namespace App\Commands;

use App\Services\Handout\HandoutService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class PurgeDeletedHandouts extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'handouts:purge-deleted';
    protected $description = 'Permanently removes handout trash and unreferenced private assets older than 30 days.';

    public function run(array $params)
    {
        $result = (new HandoutService())->purgeDeleted();
        CLI::write('Purged handouts: entries=' . $result['entries'] . ', journals=' . $result['journals'] . ', assets=' . $result['assets']);
    }
}
