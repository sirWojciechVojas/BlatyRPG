<?php

namespace App\Models;

class CampaignChatMessageModel extends BaseJsonModel
{
    protected $table = 'campaign_chat_messages';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps = false;
    protected $allowedFields = [
        'campaign_id',
        'author_user_id',
        'author_name',
        'body',
        'message_type',
        'client_nonce',
        'metadata_json',
        'created_at',
    ];
    protected $jsonFields = ['metadata_json'];
}
