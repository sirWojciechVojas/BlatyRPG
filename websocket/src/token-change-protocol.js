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

export const parseTokenChangeMessage = (message) => {
  if (message.type !== "token.change") return null;
  exact(message, [
    "v", "type", "requestId", "sceneId", "tokenId", "revision", "changes",
  ]);
  if (!message.changes || typeof message.changes !== "object"
      || Array.isArray(message.changes)) {
    throw new ProtocolError("token_changes_invalid");
  }
  exact(message.changes, ["rotation", "facing"]);
  const changes = Object.fromEntries(
    ["rotation", "facing"]
      .filter((field) => Object.hasOwn(message.changes, field))
      .map((field) => [field, angle(message.changes[field], field)]),
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
