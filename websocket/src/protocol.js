import { randomUUID } from "node:crypto";
import { WebSocket } from "ws";

export const PROTOCOL_VERSION = 1;

export const CLOSE_CODES = Object.freeze({
  AUTH: 4001,
  RATE_LIMIT: 4008,
  REPLACED: 4010,
});

export class ProtocolError extends Error {
  constructor(code, message = code) {
    super(message);
    this.name = "ProtocolError";
    this.code = code;
  }
}

const plainObject = (value) =>
  value !== null && typeof value === "object" && !Array.isArray(value);

const requestId = (value) => {
  if (value === undefined) return null;
  const normalized = String(value);
  if (!/^[A-Za-z0-9._:-]{1,128}$/.test(normalized)) {
    throw new ProtocolError("request_id_invalid");
  }
  return normalized;
};

const sequence = (value) => {
  if (value === undefined || value === null) return null;
  if (!Number.isSafeInteger(value) || value < 0) {
    throw new ProtocolError("last_sequence_invalid");
  }
  return value;
};

const positiveRevision = (value, code) => {
  if (value === undefined || value === null) return null;
  if (!Number.isSafeInteger(value) || value < 1) throw new ProtocolError(code);
  return value;
};

const requiredRequestId = (value) => {
  const normalized = requestId(value);
  if (!normalized) throw new ProtocolError("request_id_required");
  return normalized;
};

const chatBody = (value) => {
  if (typeof value !== "string") throw new ProtocolError("chat_body_invalid");
  const normalized = value.replace(/\r\n?/g, "\n").trim();
  if (!normalized || Array.from(normalized).length > 2000) {
    throw new ProtocolError("chat_body_invalid");
  }
  if (/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/u.test(normalized)) {
    throw new ProtocolError("chat_body_invalid");
  }
  return normalized;
};

const chatNonce = (value) => {
  const normalized = String(value || "").toLowerCase();
  if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/.test(normalized)) {
    throw new ProtocolError("chat_nonce_invalid");
  }
  return normalized;
};

const exactKeys = (value, allowed) => {
  for (const key of Object.keys(value)) {
    if (!allowed.includes(key)) throw new ProtocolError("unexpected_field", key);
  }
};

export const parseMessage = (data, isBinary = false) => {
  if (isBinary) throw new ProtocolError("binary_messages_forbidden");
  let parsed;
  try {
    parsed = JSON.parse(Buffer.isBuffer(data) ? data.toString("utf8") : String(data));
  } catch {
    throw new ProtocolError("invalid_json");
  }
  if (!plainObject(parsed)) throw new ProtocolError("message_object_required");
  if (parsed.v !== PROTOCOL_VERSION) throw new ProtocolError("protocol_version_unsupported");
  if (typeof parsed.type !== "string") throw new ProtocolError("message_type_required");
  return parsed;
};

export const parseAuthMessage = (message) => {
  exactKeys(message, [
    "v",
    "type",
    "ticket",
    "clientInstanceId",
    "lastSequence",
    "requestId",
  ]);
  if (message.type !== "auth") throw new ProtocolError("auth_first_message_required");
  if (typeof message.ticket !== "string" || !message.ticket) {
    throw new ProtocolError("ticket_required");
  }
  if (typeof message.clientInstanceId !== "string") {
    throw new ProtocolError("client_instance_required");
  }
  return {
    ticket: message.ticket,
    clientInstanceId: message.clientInstanceId,
    lastSequence: sequence(message.lastSequence),
    requestId: requestId(message.requestId),
  };
};

export const parseAuthenticatedMessage = (message) => {
  if (message.type === "chat.send") {
    exactKeys(message, ["v", "type", "requestId", "clientNonce", "body"]);
    return {
      type: message.type,
      requestId: requiredRequestId(message.requestId),
      clientNonce: chatNonce(message.clientNonce),
      body: chatBody(message.body),
    };
  }
  if (message.type === "chat.sync") {
    exactKeys(message, [
      "v",
      "type",
      "requestId",
      "afterRevision",
      "beforeRevision",
      "limit",
    ]);
    const afterRevision = positiveRevision(message.afterRevision, "chat_revision_invalid");
    const beforeRevision = positiveRevision(message.beforeRevision, "chat_revision_invalid");
    if (afterRevision !== null && beforeRevision !== null) {
      throw new ProtocolError("chat_cursor_ambiguous");
    }
    const limit = message.limit === undefined ? null : message.limit;
    if (limit !== null && (!Number.isSafeInteger(limit) || limit < 1 || limit > 25)) {
      throw new ProtocolError("chat_limit_invalid");
    }
    return {
      type: message.type,
      requestId: requiredRequestId(message.requestId),
      afterRevision,
      beforeRevision,
      limit,
    };
  }
  if (message.type === "sync.request") {
    exactKeys(message, ["v", "type", "lastSequence", "requestId"]);
    return {
      type: message.type,
      lastSequence: sequence(message.lastSequence),
      requestId: requestId(message.requestId),
    };
  }
  if (message.type === "campaign.leave") {
    exactKeys(message, ["v", "type", "requestId"]);
    return { type: message.type, requestId: requestId(message.requestId) };
  }
  throw new ProtocolError("message_type_unsupported");
};

export const createServerEvent = ({
  type,
  campaignId = null,
  sequence: eventSequence = null,
  actorUserId = null,
  payload = {},
  now = Date.now,
  eventId = randomUUID,
}) =>
  Object.freeze({
    v: PROTOCOL_VERSION,
    eventId: eventId(),
    type,
    campaignId,
    sequence: eventSequence,
    actorUserId,
    occurredAt: new Date(now()).toISOString(),
    payload,
  });

export const sendEvent = (ws, event) => {
  if (ws.readyState !== WebSocket.OPEN) return false;
  ws.send(JSON.stringify(event));
  return true;
};
