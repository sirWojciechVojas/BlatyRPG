<?php

namespace App\Services\Authorization;

use App\Services\Campaign\CampaignException;
use CodeIgniter\Database\BaseConnection;

/** Resolves a resource only when its backing row belongs to the campaign. */
final class ResourceScopeService
{
    private $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
    }

    public function resolve(string $type, int $resourceId, int $campaignId): array
    {
        if ($resourceId < 1 || $campaignId < 1) {
            throw new CampaignException('resource_not_found', 'Resource was not found.', 404);
        }
        $table = ResourceType::table($type);
        if ($table === null || !$this->db->tableExists($table)) {
            throw new CampaignException(
                'resource_type_unavailable',
                'This resource type is not available in the current installation.',
                409
            );
        }
        $query = $this->db->table($table)->where('id', $resourceId);
        if ($type === ResourceType::CAMPAIGN) {
            $query->where('id', $campaignId);
        } elseif ($this->db->fieldExists('campaign_id', $table)) {
            $query->where('campaign_id', $campaignId);
        } else {
            throw new CampaignException(
                'resource_scope_missing',
                'Resource has no campaign scope.',
                409
            );
        }
        if ($this->db->fieldExists('deleted_at', $table)) {
            $query->where('deleted_at', null);
        }
        $row = $query->get()->getRowArray();
        if (!$row) {
            throw new CampaignException('resource_not_found', 'Resource was not found.', 404);
        }
        return $row;
    }
}
