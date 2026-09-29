export const REALTIME_VERSION = 1;

const TOKEN_CHANGE_FIELDS = Object.freeze([
  "characterId",
  "name",
  "imageUrl",
  "width",
  "height",
  "rotation",
  "facing",
  "rotationHandleEnabled",
  "facingHandleEnabled",
  "showInfoUnselected",
  "resourceBarPosition",
  "movementRange",
  "movementSpent",
  "movementResetMode",
  "elevation",
  "disposition",
  "hidden",
  "locked",
  "visibleTo",
  "controlledBy",
  "editableBy",
  "observerBy",
  "statuses",
  "resources",
  "vision",
]);

export const authMessage = ({
  ticket,
  clientInstanceId,
  lastSequence,
  requestId,
}) => {
  const message = {
    v: REALTIME_VERSION,
    type: "auth",
    ticket: String(ticket || ""),
    clientInstanceId: String(clientInstanceId || ""),
  };
  if (Number(lastSequence) > 0) message.lastSequence = Number(lastSequence);
  if (requestId) message.requestId = String(requestId);
  return message;
};

export const syncRequestMessage = (lastSequence, requestId, sceneId = null) => {
  const message = { v: REALTIME_VERSION, type: "sync.request" };
  if (requestId) message.requestId = String(requestId);
  if (Number(lastSequence) > 0) message.lastSequence = Number(lastSequence);
  if (Number.isSafeInteger(Number(sceneId)) && Number(sceneId) > 0) {
    message.sceneId = Number(sceneId);
  }
  return message;
};

export const leaveMessage = (requestId) => ({
  v: REALTIME_VERSION,
  type: "campaign.leave",
  ...(requestId ? { requestId: String(requestId) } : {}),
});

export const tokenMoveMessage = (payload) => ({
  v: REALTIME_VERSION,
  type: "token.move",
  requestId: String(payload.requestId),
  sceneId: Number(payload.sceneId),
  tokenId: Number(payload.tokenId),
  revision: Number(payload.revision),
  x: Number(payload.x),
  y: Number(payload.y),
  waypoints: Array.isArray(payload.waypoints)
    ? payload.waypoints.slice(0, 20).map((point) => ({
        x: Number(point.x),
        y: Number(point.y),
      }))
    : [],
});

export const tokenMoveGroupMessage = (payload) => ({
  v: REALTIME_VERSION,
  type: "token.move.group",
  requestId: String(payload.requestId),
  sceneId: Number(payload.sceneId),
  moves: (payload.moves || []).slice(0, 50).map((move) => ({
    tokenId: Number(move.tokenId),
    revision: Number(move.revision),
    x: Number(move.x),
    y: Number(move.y),
    waypoints: (move.waypoints || []).slice(0, 20).map((point) => ({
      x: Number(point.x),
      y: Number(point.y),
    })),
  })),
});

export const tokenChangeMessage = (payload) => ({
  v: REALTIME_VERSION,
  type: "token.change",
  requestId: String(payload.requestId),
  sceneId: Number(payload.sceneId),
  tokenId: Number(payload.tokenId),
  revision: Number(payload.revision),
  changes: Object.fromEntries(
    TOKEN_CHANGE_FIELDS.filter((field) =>
      Object.hasOwn(payload.changes || {}, field),
    ).map((field) => [field, payload.changes[field]]),
  ),
});

export const tokenMovementRequestMessage = (payload) => ({
  ...tokenMoveMessage(payload),
  type: "token.movement.request",
});

export const tokenMovementResolveMessage = (payload) => ({
  v: REALTIME_VERSION,
  type: "token.movement.resolve",
  requestId: String(payload.requestId),
  movementRequestId: Number(payload.movementRequestId),
  decision: String(payload.decision),
});

export const combatCommandMessage = (payload) => ({
  v: REALTIME_VERSION,
  type: "combat.command",
  requestId: String(payload.requestId),
  sceneId: Number(payload.sceneId),
  command: { ...payload.command },
});

export const sceneElementChangeMessage = (resource, payload) => ({
  v: REALTIME_VERSION,
  type: `${resource}.change`,
  requestId: String(payload.requestId),
  operation: String(payload.operation),
  sceneId: Number(payload.sceneId),
  ...(payload[`${resource}Id`]
    ? {
        [`${resource}Id`]: Number(payload[`${resource}Id`]),
        revision: Number(payload.revision),
      }
    : {}),
  ...(payload.changes ? { changes: { ...payload.changes } } : {}),
});

export const wallChangeMessage = (payload) =>
  sceneElementChangeMessage("wall", payload);

export const fogSyncMessage = (payload) => ({
  v: REALTIME_VERSION,
  type: "fog.sync",
  requestId: String(payload.requestId),
  sceneId: Number(payload.sceneId),
  userId: Number(payload.userId),
  revision: Number(payload.revision),
});

export const chatSendMessage = ({ requestId, clientNonce, body }) => ({
  v: REALTIME_VERSION,
  type: "chat.send",
  requestId: String(requestId),
  clientNonce: String(clientNonce),
  body: String(body),
});

export const chatSyncMessage = ({
  requestId,
  afterRevision,
  beforeRevision,
  limit = 20,
}) => ({
  v: REALTIME_VERSION,
  type: "chat.sync",
  requestId: String(requestId),
  ...(Number(afterRevision) > 0
    ? { afterRevision: Number(afterRevision) }
    : {}),
  ...(Number(beforeRevision) > 0
    ? { beforeRevision: Number(beforeRevision) }
    : {}),
  limit: Number(limit),
});

export const handoutNotifyMessage = ({ requestId, batchId }) => ({
  v: REALTIME_VERSION,
  type: "handout.notify",
  requestId: String(requestId),
  batchId: String(batchId),
});

export const parseServerEvent = (raw) => {
  let value;
  try {
    value = JSON.parse(
      typeof raw === "string" ? raw : String(raw?.data ?? raw),
    );
  } catch (_error) {
    return null;
  }
  if (
    !value ||
    typeof value !== "object" ||
    value.v !== REALTIME_VERSION ||
    typeof value.type !== "string"
  ) {
    return null;
  }
  return {
    ...value,
    campaignId: Number(value.campaignId ?? value.campaign_id) || null,
    sequence: Number(value.sequence) || 0,
    payload:
      value.payload && typeof value.payload === "object" ? value.payload : {},
  };
};

export const presenceItems = (payload = {}) => {
  for (const candidate of [
    payload.presence,
    payload.users,
    payload.items,
    payload.members,
  ]) {
    if (Array.isArray(candidate)) return candidate;
  }
  return [];
};
