<?php

namespace App\Models;

use CodeIgniter\Model;

class HeroJournalNpcEncounterModel extends Model
{
    protected $table = 'hero_journal_npc_encounters';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'entry_id', 'session_number', 'occurred_on', 'summary', 'sort_order',
    ];
}
