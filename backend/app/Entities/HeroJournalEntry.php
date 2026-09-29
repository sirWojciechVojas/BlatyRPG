<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class HeroJournalEntry extends Entity
{
    protected $casts = [
        'id' => 'integer',
        'campaign_id' => 'integer',
        'character_id' => 'integer',
        'owner_user_id' => 'integer',
        'author_user_id' => 'integer',
        'session_number' => '?integer',
        'trust_level' => '?integer',
        'revision' => 'integer',
    ];
}
