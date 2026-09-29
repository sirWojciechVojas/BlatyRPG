import { ProtocolError } from "./protocol-error.js";

export const parseFogMessage = (message) => {
  if (message.type !== "fog.sync") return null;
  const allowed = ["v", "type", "requestId", "sceneId", "userId", "revision"];
  for (const key of Object.keys(message))
    if (!allowed.includes(key)) throw new ProtocolError("unexpected_field");
  const positive = (value, code) => {
    if (!Number.isSafeInteger(value) || value < 1)
      throw new ProtocolError(code);
    return value;
  };
  const requestId = String(message.requestId || "");
  if (!/^[A-Za-z0-9._:-]{1,128}$/.test(requestId))
    throw new ProtocolError("request_id_required");
  return {
    type: message.type,
    requestId,
    sceneId: positive(message.sceneId, "scene_id_invalid"),
    userId: positive(message.userId, "fog_user_id_invalid"),
    revision: positive(message.revision, "fog_revision_invalid"),
  };
};
