export const normalizePresence = (source = {}) => ({
  userId: Number(source.userId ?? source.user_id) || null,
  displayName: String(
    source.displayName ?? source.display_name ?? source.username ?? "",
  ),
  campaignRole: String(
    source.campaignRole ?? source.campaign_role ?? "player",
  ).toLowerCase(),
  online: source.online === true,
  status: String(source.status || (source.online ? "online" : "offline")),
  connectionCount: Math.max(
    0,
    Number(source.connectionCount ?? source.connection_count) || 0,
  ),
  connectedAt: source.connectedAt ?? source.connected_at ?? null,
  updatedAt: source.updatedAt ?? source.updated_at ?? null,
});
