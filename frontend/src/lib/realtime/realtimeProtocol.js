export const REALTIME_VERSION = 1;

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

export const syncRequestMessage = (lastSequence, requestId) => {
  const message = { v: REALTIME_VERSION, type: "sync.request" };
  if (requestId) message.requestId = String(requestId);
  if (Number(lastSequence) > 0) message.lastSequence = Number(lastSequence);
  return message;
};

export const leaveMessage = (requestId) => ({
  v: REALTIME_VERSION,
  type: "campaign.leave",
  ...(requestId ? { requestId: String(requestId) } : {}),
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
