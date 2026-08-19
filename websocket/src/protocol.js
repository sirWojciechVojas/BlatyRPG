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
