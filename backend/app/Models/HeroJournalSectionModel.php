<?php

namespace App\Models;

use CodeIgniter\Model;

class HeroJournalSectionModel extends Model
{
    protected $table = 'hero_journal_sections';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'entry_id', 'section_key', 'content', 'visibility', 'sort_order',
    ];
}
