<?php

namespace App\Models;

use CodeIgniter\Model;

class CampaignInvitationModel extends Model
{
    protected $table = 'campaign_invitations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'campaign_id', 'invitee_user_id', 'invited_by_user_id', 'role',
        'status', 'pending_key', 'message', 'expires_at', 'responded_at',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
