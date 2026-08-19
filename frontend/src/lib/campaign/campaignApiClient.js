import { jsonApiClient } from "@/lib/api/jsonApiClient";

const arrayFrom = (payload) => {
  for (const value of [
    payload?.campaigns,
    payload?.items,
    payload?.data?.campaigns,
    payload?.data?.items,
    payload?.data,
  ]) {
    if (Array.isArray(value)) return value;
  }
  return [];
};

const normalizeCapabilities = (value = {}) => {
  const canManage = value.canManage === true || value.can_manage === true;
  return {
    canManage,
    canViewHidden:
      value.canViewHidden === true || value.can_view_hidden === true,
    canOpenShop:
      value.canOpenShop === true ||
      value.can_open_shop === true ||
      (value.canOpenShop === undefined &&
        value.can_open_shop === undefined &&
        canManage),
  };
};

export const normalizeCampaign = (campaign = {}) => ({
  id: Number(campaign.id) || campaign.id || null,
  name: String(campaign.name || ""),
  description: String(campaign.description || ""),
  systemType: String(campaign.systemType || campaign.system_type || ""),
  isActive: Boolean(campaign.isActive ?? campaign.is_active ?? true),
  membershipRole: String(
    campaign.membershipRole ||
      campaign.membership_role ||
      campaign.accessRole ||
      campaign.access_role ||
      "player",
  ).toLowerCase(),
  capabilities: normalizeCapabilities(campaign.capabilities),
});

export const createCampaignApiClient = (client = jsonApiClient) => ({
  async list(options = {}) {
    const payload = await client.request("/campaigns", options);
    return {
      campaigns: arrayFrom(payload)
        .map(normalizeCampaign)
        .filter((x) => x.id),
      capabilities: {
        canCreate: Boolean(
          payload?.capabilities?.canCreate ??
          payload?.capabilities?.can_create ??
          payload?.data?.capabilities?.canCreate ??
          payload?.data?.capabilities?.can_create,
        ),
      },
    };
  },

  async create(draft) {
    const payload = await client.request("/campaigns", {
      method: "POST",
      body: {
        name: String(draft.name || "").trim(),
        description: String(draft.description || "").trim(),
        systemType: String(draft.systemType || "").trim(),
      },
    });
    const campaign =
      payload?.campaign || payload?.data?.campaign || payload?.data || payload;
    return normalizeCampaign(campaign);
  },
});

export const campaignApiClient = createCampaignApiClient();
