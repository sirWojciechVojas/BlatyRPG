<?php

namespace App\Models;

use CodeIgniter\Model;

class HeroJournalRelationModel extends Model
{
    protected $table = 'hero_journal_relations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'source_entry_id', 'target_entry_id', 'relation_type',
        'created_by_user_id', 'created_at',
    ];
}
