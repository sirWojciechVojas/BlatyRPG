import { ProtocolError } from "./protocol-error.js";

const exact = (value, allowed) => {
  for (const key of Object.keys(value)) {
    if (!allowed.includes(key)) throw new ProtocolError("unexpected_field", key);
  }
};

const positive = (value, code) => {
  if (!Number.isSafeInteger(value) || value < 1) throw new ProtocolError(code);
  return value;
};

const angle = (value, field) => {
  if (typeof value !== "number" || !Number.isFinite(value)) {
    throw new ProtocolError(`token_${field}_invalid`);
  }
  return value;
};

const CHANGE_FIELDS = Object.freeze([
  "characterId", "name", "imageUrl", "width", "height", "rotation",
  "facing", "rotationHandleEnabled", "facingHandleEnabled",
  "rotationFollowsFacing",
  "showInfoUnselected", "resourceBarPosition", "movementRange",
  "movementSpent", "movementResetMode", "elevation", "disposition",
  "hidden", "locked", "visibleTo", "controlledBy", "editableBy",
  "observerBy", "statuses", "resources", "vision",
]);

const safeChangeValue = (value, depth = 0) => {
  if (depth > 8) throw new ProtocolError("token_changes_invalid");
  if (value === null || typeof value === "string" || typeof value === "boolean") {
    return value;
  }
  if (typeof value === "number") {
    if (!Number.isFinite(value)) throw new ProtocolError("token_changes_invalid");
    return value;
  }
  if (Array.isArray(value)) {
    if (value.length > 200) throw new ProtocolError("token_changes_invalid");
    return value.map((item) => safeChangeValue(item, depth + 1));
  }
  if (!value || typeof value !== "object") {
    throw new ProtocolError("token_changes_invalid");
  }
  const entries = Object.entries(value);
  if (entries.length > 200) throw new ProtocolError("token_changes_invalid");
  return Object.fromEntries(
    entries.map(([key, item]) => [key, safeChangeValue(item, depth + 1)]),
  );
};

export const parseTokenChangeMessage = (message) => {
  if (message.type !== "token.change") return null;
  exact(message, [
    "v", "type", "requestId", "sceneId", "tokenId", "revision", "changes",
  ]);
  if (!message.changes || typeof message.changes !== "object"
      || Array.isArray(message.changes)) {
    throw new ProtocolError("token_changes_invalid");
  }
  exact(message.changes, CHANGE_FIELDS);
  const changes = Object.fromEntries(
    CHANGE_FIELDS
      .filter((field) => Object.hasOwn(message.changes, field))
      .map((field) => [
        field,
        ["rotation", "facing"].includes(field)
          ? angle(message.changes[field], field)
          : safeChangeValue(message.changes[field]),
      ]),
  );
  if (!Object.keys(changes).length) throw new ProtocolError("token_changes_invalid");
  const requestId = String(message.requestId || "");
  if (!/^[A-Za-z0-9._:-]{1,128}$/.test(requestId)) {
    throw new ProtocolError("request_id_invalid");
  }
  return {
    type: message.type,
    requestId,
    sceneId: positive(message.sceneId, "scene_id_invalid"),
    tokenId: positive(message.tokenId, "token_id_invalid"),
    revision: positive(message.revision, "token_revision_invalid"),
    changes,
  };
};
