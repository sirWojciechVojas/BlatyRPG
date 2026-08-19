const objectOrEmpty = (value) =>
  value && typeof value === "object" && !Array.isArray(value) ? value : {};

const idOf = (value) => Number(value) || value || null;
const text = (value) => String(value ?? "");

export const arrayFromPayload = (payload, names = []) => {
  for (const name of names) {
    const direct = payload?.[name];
    if (Array.isArray(direct)) return direct;
    const nested = payload?.data?.[name];
    if (Array.isArray(nested)) return nested;
  }
  if (Array.isArray(payload?.data)) return payload.data;
  return [];
};

export const normalizeCampaignCapabilities = (value = {}) => {
  const canManage = Boolean(value.canManage ?? value.can_manage);
  return {
    canAccess: Boolean(value.canAccess ?? value.can_access ?? canManage),
    canManage,
    canViewHidden: Boolean(value.canViewHidden ?? value.can_view_hidden),
    canOpenShop: Boolean(value.canOpenShop ?? value.can_open_shop ?? canManage),
  };
};

export const normalizeCampaign = (campaign = {}) => ({
  id: idOf(campaign.id),
  name: text(campaign.name),
  description: text(campaign.description),
  bannerUrl: campaign.bannerUrl ?? campaign.banner_url ?? null,
  systemType: text(campaign.systemType ?? campaign.system_type),
  status: text(
    campaign.status ||
      ((campaign.isActive ?? campaign.is_active ?? true) ? "active" : "paused"),
  ).toLowerCase(),
  isActive: Boolean(campaign.isActive ?? campaign.is_active ?? true),
  gameMasterId: idOf(campaign.gameMasterId ?? campaign.game_master_id),
  gameMaster: objectOrEmpty(campaign.gameMaster ?? campaign.game_master),
  settings: objectOrEmpty(campaign.settings ?? campaign.settings_json),
  membershipRole: text(
    campaign.membershipRole ??
      campaign.membership_role ??
      campaign.accessRole ??
      campaign.access_role ??
      "player",
  ).toLowerCase(),
  capabilities: normalizeCampaignCapabilities(campaign.capabilities),
  createdAt: campaign.createdAt ?? campaign.created_at ?? null,
  updatedAt: campaign.updatedAt ?? campaign.updated_at ?? null,
  lastActivityAt: campaign.lastActivityAt ?? campaign.last_activity_at ?? null,
});

export const normalizeCampaignMember = (source = {}) => {
  const user = objectOrEmpty(source.user);
  return {
    id: idOf(source.id),
    campaignId: idOf(source.campaignId ?? source.campaign_id),
    userId: idOf(source.userId ?? source.user_id ?? user.id),
    username: text(source.username ?? user.username),
    email: text(source.email ?? user.email),
    avatarUrl:
      source.avatarUrl ??
      source.avatar_url ??
      user.avatarUrl ??
      user.avatar_url ??
      null,
    role: text(source.role || "player").toLowerCase(),
    isActive: Boolean(source.isActive ?? source.is_active ?? true),
    isOnline: Boolean(source.isOnline ?? source.is_online ?? false),
    joinedAt: source.joinedAt ?? source.joined_at ?? null,
    leftAt: source.leftAt ?? source.left_at ?? null,
  };
};

const normalizeUser = (source = {}) => ({
  id: idOf(source.id),
  username: text(source.username),
  email: text(source.email),
  avatarUrl: source.avatarUrl ?? source.avatar_url ?? null,
});

export const normalizeInvitation = (source = {}) => ({
  id: idOf(source.id),
  campaignId: idOf(source.campaignId ?? source.campaign_id),
  campaignName: text(source.campaignName ?? source.campaign_name),
  invitee: normalizeUser(source.invitee),
  invitedBy: normalizeUser(source.invitedBy ?? source.invited_by),
  role: text(source.role || "player").toLowerCase(),
  status: text(source.status || "pending").toLowerCase(),
  message: source.message ?? null,
  expiresAt: source.expiresAt ?? source.expires_at ?? null,
  respondedAt: source.respondedAt ?? source.responded_at ?? null,
  createdAt: source.createdAt ?? source.created_at ?? null,
});

export const normalizeCampaignCharacter = (source = {}) => ({
  id: idOf(source.id),
  campaignId: idOf(source.campaignId ?? source.campaign_id),
  ownerUserId: idOf(
    source.ownerUserId ?? source.owner_user_id ?? source.user_id,
  ),
  name: text(source.name),
  avatarUrl: text(source.avatarUrl ?? source.avatar_url ?? source.avatar),
  visibility: text(
    source.visibility ?? source.visibility_level ?? "none",
  ).toLowerCase(),
  capabilities: {
    canEdit: Boolean(
      source.capabilities?.canEdit ?? source.capabilities?.can_edit,
    ),
    canDelete: Boolean(
      source.capabilities?.canDelete ?? source.capabilities?.can_delete,
    ),
  },
  updatedAt: source.updatedAt ?? source.updated_at ?? null,
});

export const normalizeResourcePermission = (source = {}) => ({
  id: idOf(source.id),
  campaignId: idOf(source.campaignId ?? source.campaign_id),
  resourceType: text(source.resourceType ?? source.resource_type).toLowerCase(),
  resourceId: idOf(source.resourceId ?? source.resource_id),
  user: normalizeUser(source.user),
  accessLevel: text(
    source.accessLevel ?? source.access_level ?? "none",
  ).toLowerCase(),
  grantedByUserId: idOf(source.grantedByUserId ?? source.granted_by_user_id),
  updatedAt: source.updatedAt ?? source.updated_at ?? null,
});
