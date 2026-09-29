import { ProtocolError } from "./protocol-error.js";

export const SOUND_EFFECT_TYPES = Object.freeze([
  "SOUND_EFFECT_PLAY",
  "SOUND_EFFECT_STOP",
  "SOUND_EFFECT_STOP_ALL",
  "SOUND_EFFECT_SETTINGS",
  "SOUND_EFFECT_SYNC",
]);

const exactKeys = (value, allowed) => {
  for (const key of Object.keys(value)) {
    if (!allowed.includes(key))
      throw new ProtocolError("unexpected_field", key);
  }
};

const identifier = (value, code, minimum = 1) => {
  const text = String(value || "");
  if (
    text.length < minimum ||
    text.length > 128 ||
    !/^[A-Za-z0-9._:-]+$/u.test(text)
  ) {
    throw new ProtocolError(code);
  }
  return text;
};

const positiveId = (value, code) => {
  if (!Number.isSafeInteger(value) || value < 1) throw new ProtocolError(code);
  return value;
};

export const parseSoundEffectMessage = (message) => {
  if (!SOUND_EFFECT_TYPES.includes(message.type)) return null;
  const common = ["v", "type", "requestId"];
  const fields = {
    SOUND_EFFECT_PLAY: ["playbackId", "slotId", "executeAt"],
    SOUND_EFFECT_STOP: ["playbackId"],
    SOUND_EFFECT_STOP_ALL: ["fadeOutMs"],
    SOUND_EFFECT_SETTINGS: ["revision"],
    SOUND_EFFECT_SYNC: [],
  }[message.type];
  exactKeys(message, common.concat(fields));
  const result = {
    type: message.type,
    requestId: identifier(message.requestId, "request_id_required"),
  };
  if (message.type === "SOUND_EFFECT_PLAY") {
    result.playbackId = identifier(
      message.playbackId,
      "sound_effect_playback_id_invalid",
      8,
    );
    result.slotId = positiveId(message.slotId, "sound_effect_slot_invalid");
    if (message.executeAt !== undefined) {
      if (!Number.isSafeInteger(message.executeAt) || message.executeAt < 0) {
        throw new ProtocolError("sound_effect_time_invalid");
      }
      result.executeAt = message.executeAt;
    }
  } else if (message.type === "SOUND_EFFECT_STOP") {
    result.playbackId = identifier(
      message.playbackId,
      "sound_effect_playback_id_invalid",
      8,
    );
  } else if (message.type === "SOUND_EFFECT_STOP_ALL") {
    const fade = message.fadeOutMs ?? 0;
    if (!Number.isFinite(fade) || fade < 0 || fade > 60000) {
      throw new ProtocolError("sound_effect_fade_invalid");
    }
    result.fadeOutMs = Math.round(fade);
  } else if (message.type === "SOUND_EFFECT_SETTINGS") {
    result.revision = positiveId(
      message.revision,
      "sound_effect_revision_invalid",
    );
  }
  return result;
};
