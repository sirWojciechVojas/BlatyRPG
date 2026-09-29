<?php

namespace App\Models;

use CodeIgniter\Model;

class HeroJournalEntryModel extends Model
{
    protected $table = 'hero_journal_entries';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $allowedFields = [
        'campaign_id', 'character_id', 'owner_user_id', 'author_user_id',
        'entry_type', 'title', 'status', 'summary', 'session_number',
        'occurred_on', 'visibility', 'trust_level', 'revision', 'archived_at',
    ];
}
