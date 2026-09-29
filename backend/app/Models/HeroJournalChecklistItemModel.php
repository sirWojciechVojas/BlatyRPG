<?php

namespace App\Models;

use CodeIgniter\Model;

class HeroJournalChecklistItemModel extends Model
{
    protected $table = 'hero_journal_checklist_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'entry_id', 'label', 'is_completed', 'sort_order', 'completed_at',
    ];
}
