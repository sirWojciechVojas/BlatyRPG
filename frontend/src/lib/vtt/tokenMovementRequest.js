const point = (value = {}) => ({
  x: Number(value.x) || 0,
  y: Number(value.y) || 0,
});

export const normalizeMovementRequest = (value = {}) => ({
  ...value,
  id: Number(value.id) || 0,
  campaignId: Number(value.campaignId) || 0,
  sceneId: Number(value.sceneId) || 0,
  tokenId: Number(value.tokenId) || 0,
  requestedByUserId: Number(value.requestedByUserId) || 0,
  tokenName: String(value.tokenName || ""),
  requesterName: String(value.requesterName || ""),
  origin: point(value.origin),
  target: point(value.target),
  waypoints: Array.isArray(value.waypoints) ? value.waypoints.map(point) : [],
  cost: Math.max(0, Number(value.cost) || 0),
  spent: Math.max(0, Number(value.spent) || 0),
  range: Math.max(0, Number(value.range) || 0),
  status: String(value.status || "pending"),
});
