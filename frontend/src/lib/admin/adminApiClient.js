import { jsonApiClient } from "@/lib/api/jsonApiClient";

const normalizeUser = (user = {}) => ({
  id: Number(user.id) || null,
  username: String(user.username || user.login || ""),
  email: String(user.email || ""),
  role: ["player", "gm"].includes(String(user.role || "user").toLowerCase())
    ? "user"
    : String(user.role || "user").toLowerCase(),
  avatarUrl: user.avatarUrl || user.avatar_url || null,
  campaignCount: Number(user.campaignCount ?? user.campaign_count ?? 0),
  createdAt: user.createdAt || user.created_at || null,
});

const normalizeCampaign = (campaign = {}) => ({
  id: Number(campaign.id) || null,
  name: String(campaign.name || ""),
  systemType: String(campaign.systemType || campaign.system_type || ""),
  isActive: Boolean(campaign.isActive ?? campaign.is_active),
  gameMasterId:
    Number(campaign.gameMasterId ?? campaign.game_master_id) || null,
  gameMasterName: String(
    campaign.gameMasterName || campaign.game_master_name || "",
  ),
  memberCount: Number(campaign.memberCount ?? campaign.member_count ?? 0),
  status: String(
    campaign.status ||
      ((campaign.isActive ?? campaign.is_active) ? "active" : "paused"),
  ),
  lastActivityAt: campaign.lastActivityAt || campaign.last_activity_at || null,
});

const normalizeCharacter = (character = {}) => ({
  id: Number(character.id) || null,
  name: String(character.name || ""),
  updatedAt: character.updatedAt || character.updated_at || null,
});

const normalizeCharacterCampaign = (item = {}) => ({
  characterId: Number(item.characterId ?? item.character_id) || null,
  campaignId: Number(item.campaignId ?? item.campaign_id) || null,
  campaignName: String(item.campaignName || item.campaign_name || ""),
});

const normalizeCharacterOwner = (item = {}) => ({
  ...normalizeCharacterCampaign(item),
  userId: Number(item.userId ?? item.user_id) || null,
  username: String(item.username || ""),
});

const normalizeCharacterGameMaster = (item = {}) => ({
  campaignId: Number(item.campaignId ?? item.campaign_id) || null,
  userId: Number(item.userId ?? item.user_id) || null,
  username: String(item.username || ""),
  isCampaignOwner: Boolean(item.isCampaignOwner ?? item.is_campaign_owner),
});

const normalizeDistribution = (items) =>
  Array.isArray(items)
    ? items.map((item) => ({
        key: String(item?.key || ""),
        value: Number(item?.value || 0),
      }))
    : [];

const normalizeAnalytics = (analytics = {}) => ({
  accountRoles: normalizeDistribution(analytics.accountRoles),
  campaignStatuses: normalizeDistribution(analytics.campaignStatuses),
  membershipRoles: normalizeDistribution(analytics.membershipRoles),
  systems: normalizeDistribution(analytics.systems),
  growth: Array.isArray(analytics.growth)
    ? analytics.growth.map((point) => ({
        label: String(point?.label || ""),
        users: Number(point?.users || 0),
        campaigns: Number(point?.campaigns || 0),
      }))
    : [],
});

export const createAdminApiClient = (client = jsonApiClient) => ({
  async overview(options = {}) {
    const payload = await client.request("/admin/overview", options);
    return {
      currentUserId: Number(payload?.currentUserId || 0),
      users: Array.isArray(payload?.users)
        ? payload.users.map(normalizeUser).filter((user) => user.id)
        : [],
      campaigns: Array.isArray(payload?.campaigns)
        ? payload.campaigns.map(normalizeCampaign).filter((item) => item.id)
        : [],
      characters: Array.isArray(payload?.characters)
        ? payload.characters.map(normalizeCharacter).filter((item) => item.id)
        : [],
      characterCampaigns: Array.isArray(payload?.characterCampaigns)
        ? payload.characterCampaigns
            .map(normalizeCharacterCampaign)
            .filter((item) => item.characterId && item.campaignId)
        : [],
      characterOwners: Array.isArray(payload?.characterOwners)
        ? payload.characterOwners
            .map(normalizeCharacterOwner)
            .filter(
              (item) => item.characterId && item.campaignId && item.userId,
            )
        : [],
      characterGameMasters: Array.isArray(payload?.characterGameMasters)
        ? payload.characterGameMasters
            .map(normalizeCharacterGameMaster)
            .filter((item) => item.campaignId && item.userId)
        : [],
      metrics: {
        users: Number(payload?.metrics?.users || 0),
        admins: Number(payload?.metrics?.admins || 0),
        campaigns: Number(payload?.metrics?.campaigns || 0),
        activeCampaigns: Number(payload?.metrics?.activeCampaigns || 0),
        memberships: Number(payload?.metrics?.memberships || 0),
        activeSessions: Number(payload?.metrics?.activeSessions || 0),
      },
      analytics: normalizeAnalytics(payload?.analytics),
      activity: Array.isArray(payload?.activity)
        ? payload.activity.map((item) => ({
            type: String(item?.type || ""),
            label: String(item?.label || ""),
            occurredAt: item?.occurredAt || null,
          }))
        : [],
      system: {
        generatedAt: payload?.system?.generatedAt || null,
        environment: String(payload?.system?.environment || ""),
        phpVersion: String(payload?.system?.phpVersion || ""),
        database: String(payload?.system?.database || ""),
        activeSessions: Number(payload?.system?.activeSessions || 0),
      },
    };
  },

  async createUser(draft) {
    const payload = await client.request("/admin/users", {
      method: "POST",
      body: draft,
    });
    return normalizeUser(payload?.user);
  },

  async changeUserRole(userId, role) {
    const payload = await client.request(
      `/admin/users/${Number(userId)}/role`,
      {
        method: "PATCH",
        body: { role },
      },
    );
    return normalizeUser(payload?.user);
  },

  setCharacterCampaign(characterId, campaignId, assigned) {
    return client.request(
      `/admin/characters/${Number(characterId)}/campaigns/${Number(campaignId)}`,
      { method: assigned ? "PUT" : "DELETE" },
    );
  },

  setCharacterOwner(characterId, campaignId, userId, assigned) {
    return client.request(
      `/admin/characters/${Number(characterId)}/campaigns/${Number(campaignId)}` +
        `/owners/${Number(userId)}`,
      { method: assigned ? "PUT" : "DELETE" },
    );
  },
});

export const adminApiClient = createAdminApiClient();
