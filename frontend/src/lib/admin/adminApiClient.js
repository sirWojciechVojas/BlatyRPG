import { jsonApiClient } from "@/lib/api/jsonApiClient";

const normalizeUser = (user = {}) => ({
  id: Number(user.id) || null,
  username: String(user.username || user.login || ""),
  email: String(user.email || ""),
  role:
    String(user.role || "player").toLowerCase() === "user"
      ? "player"
      : String(user.role || "player").toLowerCase(),
  avatarUrl: user.avatarUrl || user.avatar_url || null,
  campaignCount: Number(user.campaignCount ?? user.campaign_count ?? 0),
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
      metrics: {
        users: Number(payload?.metrics?.users || 0),
        admins: Number(payload?.metrics?.admins || 0),
        campaigns: Number(payload?.metrics?.campaigns || 0),
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
});

export const adminApiClient = createAdminApiClient();
