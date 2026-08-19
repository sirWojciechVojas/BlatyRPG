<?php

namespace App\Services\Campaign;

use App\Models\CampaignModel;

class CampaignSettingsService
{
    private $campaigns;
    private $guard;
    private $validator;
    private $catalogSelection;

    public function __construct(
        ?CampaignModel $campaigns = null,
        ?CampaignGuardService $guard = null,
        ?CampaignSettingsValidator $validator = null,
        ?CampaignCatalogSelectionService $catalogSelection = null
    ) {
        $this->campaigns = $campaigns ?: new CampaignModel();
        $this->guard = $guard ?: new CampaignGuardService();
        $this->validator = $validator ?: new CampaignSettingsValidator();
        $this->catalogSelection = $catalogSelection
            ?: new CampaignCatalogSelectionService();
    }

    public function show(array $auth, int $campaignId): array
    {
        $context = $this->guard->context($auth, $campaignId);
        return ['campaign' => CampaignPresenter::present($context['campaign'], $context)];
    }

    public function update(array $auth, int $campaignId, array $payload): array
    {
        $context = $this->guard->requireManage($auth, $campaignId);
        $validated = $this->validator->validate($payload);
        if (!$validated['valid']) {
            throw new CampaignException(
                'validation_failed',
                'Campaign settings are invalid.',
                422,
                $validated['errors']
            );
        }
        $data = $this->catalogSelection->forUpdate($validated['data']);
        if (!$this->campaigns->update($campaignId, $data)) {
            throw new CampaignException(
                'validation_failed',
                'Campaign could not be updated.',
                422,
                $this->campaigns->errors()
            );
        }
        $context['campaign'] = $this->campaigns->find($campaignId);
        return ['campaign' => CampaignPresenter::present($context['campaign'], $context)];
    }

    public function enter(array $auth, int $campaignId): array
    {
        $context = $this->guard->context($auth, $campaignId);
        $this->guard->touchActivity($campaignId);
        $context['campaign']['last_activity_at'] = date('Y-m-d H:i:s');
        return ['campaign' => CampaignPresenter::present($context['campaign'], $context)];
    }
}
