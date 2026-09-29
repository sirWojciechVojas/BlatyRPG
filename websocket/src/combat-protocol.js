import { ProtocolError } from "./protocol-error.js";

const ACTIONS = new Set([
  "start",
  "end",
  "next",
  "previous",
  "toggle",
  "initiative",
  "resetMovement",
  "setMovement",
]);

const positiveId = (value, code) => {
  if (!Number.isSafeInteger(value) || value < 1) throw new ProtocolError(code);
  return value;
};

const finite = (value, code, minimum = -100000, maximum = 100000) => {
  if (typeof value !== "number" || !Number.isFinite(value)) {
    throw new ProtocolError(code);
  }
  if (value < minimum || value > maximum) throw new ProtocolError(code);
  return value;
};

const exactKeys = (value, allowed) => {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    throw new ProtocolError("combat_command_invalid");
  }
  for (const key of Object.keys(value)) {
    if (!allowed.includes(key)) throw new ProtocolError("unexpected_field", key);
  }
};

const normalizeIds = (value) => {
  if (value === undefined) return undefined;
  if (!Array.isArray(value) || value.length > 200) {
    throw new ProtocolError("combat_token_ids_invalid");
  }
  return [...new Set(value.map((id) => positiveId(id, "combat_token_ids_invalid")))];
};

const commandFields = (action) => ({
  start: ["action", "tokenIds", "revision"],
  end: ["action", "revision"],
  next: ["action", "revision"],
  previous: ["action", "revision"],
  toggle: ["action", "revision", "tokenId"],
  initiative: ["action", "revision", "tokenId", "initiative"],
  resetMovement: ["action", "tokenIds"],
  setMovement: [
    "action",
    "tokenId",
    "tokenRevision",
    "movementRange",
    "movementPoints",
    "movementResetMode",
  ],
})[action];

const normalizeCommand = (value) => {
  const action = String(value?.action || "");
  if (!ACTIONS.has(action)) throw new ProtocolError("combat_action_invalid");
  exactKeys(value, commandFields(action));
  const result = { action };
  if (value.revision !== undefined) {
    result.revision = positiveId(value.revision, "combat_revision_invalid");
  }
  if (value.tokenRevision !== undefined) {
    result.tokenRevision = positiveId(value.tokenRevision, "token_revision_invalid");
  }
  if (value.tokenId !== undefined) {
    result.tokenId = positiveId(value.tokenId, "token_id_invalid");
  }
  const tokenIds = normalizeIds(value.tokenIds);
  if (tokenIds) result.tokenIds = tokenIds;
  if (value.initiative !== undefined) {
    result.initiative = finite(value.initiative, "initiative_invalid");
  }
  for (const field of ["movementRange", "movementPoints"]) {
    if (value[field] !== undefined) {
      result[field] = finite(value[field], "movement_invalid", 0, 10000);
    }
  }
  if (value.movementResetMode !== undefined) {
    if (!["turn", "round", "manual"].includes(value.movementResetMode)) {
      throw new ProtocolError("movement_reset_mode_invalid");
    }
    result.movementResetMode = value.movementResetMode;
  }
  return result;
};

export const parseCombatCommandMessage = (message) => {
  if (message.type !== "combat.command") return null;
  exactKeys(message, ["v", "type", "requestId", "sceneId", "command"]);
  if (!/^[A-Za-z0-9._:-]{1,128}$/.test(String(message.requestId || ""))) {
    throw new ProtocolError("request_id_invalid");
  }
  return {
    type: message.type,
    requestId: String(message.requestId),
    sceneId: positiveId(message.sceneId, "scene_id_invalid"),
    command: normalizeCommand(message.command),
  };
};
