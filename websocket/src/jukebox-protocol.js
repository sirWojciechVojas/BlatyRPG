import { ProtocolError } from "./protocol-error.js";

export const JUKEBOX_TYPES = Object.freeze([
  "JUKEBOX_LOAD",
  "JUKEBOX_PLAY",
  "JUKEBOX_PAUSE",
  "JUKEBOX_STOP",
  "JUKEBOX_SEEK",
  "JUKEBOX_VOLUME",
  "JUKEBOX_MUTE",
  "JUKEBOX_LOOP",
  "JUKEBOX_FADE_IN",
  "JUKEBOX_FADE_OUT",
  "JUKEBOX_DEVICE",
  "JUKEBOX_SYNC",
]);

export const JUKEBOX_CHANNELS = Object.freeze([
  "music",
  "ambient-1",
  "ambient-2",
  "sfx",
  "external-1",
  "external-2",
]);

const exactKeys = (value, allowed) => {
  for (const key of Object.keys(value)) {
    if (!allowed.includes(key)) throw new ProtocolError("unexpected_field", key);
  }
};

const requestId = (value) => {
  const normalized = String(value || "");
  if (!/^[A-Za-z0-9._:-]{1,128}$/.test(normalized)) {
    throw new ProtocolError("request_id_required");
  }
  return normalized;
};

const number = (value, minimum, maximum, code, required = true) => {
  if (value === undefined && !required) return null;
  if (typeof value !== "number" || !Number.isFinite(value) || value < minimum || value > maximum) {
    throw new ProtocolError(code);
  }
  return value;
};

const positiveId = (value, required = true) => {
  if ((value === null || value === undefined) && !required) return null;
  if (!Number.isSafeInteger(value) || value < 1) throw new ProtocolError("jukebox_track_invalid");
  return value;
};

const boolean = (value, code) => {
  if (typeof value !== "boolean") throw new ProtocolError(code);
  return value;
};

const channel = (value) => {
  const normalized = String(value || "");
  if (!JUKEBOX_CHANNELS.includes(normalized)) throw new ProtocolError("jukebox_channel_invalid");
  return normalized;
};

const sourceType = (value) => {
  if (value === undefined || value === null || value === "") return null;
  const normalized = String(value).toLowerCase();
  if (!["system", "upload", "external", "external-input"].includes(normalized)) {
    throw new ProtocolError("jukebox_source_invalid");
  }
  return normalized;
};

const optionalText = (value, maximum, code) => {
  if (value === undefined || value === null || value === "") return null;
  if (typeof value !== "string") throw new ProtocolError(code);
  const normalized = value.trim();
  if (!normalized || normalized.length > maximum || /[\u0000-\u001f\u007f]/u.test(normalized)) {
    throw new ProtocolError(code);
  }
  return normalized;
};

export const parseJukeboxMessage = (message) => {
  if (!JUKEBOX_TYPES.includes(message.type)) return null;
  if (message.type === "JUKEBOX_SYNC") {
    exactKeys(message, ["v", "type", "requestId", "clientSentAt"]);
    return {
      type: message.type,
      requestId: requestId(message.requestId),
      clientSentAt: number(message.clientSentAt, 0, 9_999_999_999_999, "jukebox_time_invalid"),
    };
  }

  const common = ["v", "type", "requestId", "channelId", "executeAt"];
  const fields = {
    JUKEBOX_LOAD: ["trackId", "position", "duration", "loop", "volume", "muted", "sourceType", "playlistId", "sourceLabel"],
    JUKEBOX_PLAY: ["trackId", "position", "duration", "loop", "volume", "muted", "sourceType", "playlistId", "sourceLabel"],
    JUKEBOX_PAUSE: ["position"],
    JUKEBOX_STOP: [],
    JUKEBOX_SEEK: ["position"],
    JUKEBOX_VOLUME: ["volume"],
    JUKEBOX_MUTE: ["muted"],
    JUKEBOX_LOOP: ["loop"],
    JUKEBOX_FADE_IN: ["position", "duration", "volume", "fadeMs"],
    JUKEBOX_FADE_OUT: ["position", "fadeMs"],
    JUKEBOX_DEVICE: ["deviceLabel", "deviceSlot", "volume", "muted"],
  }[message.type];
  exactKeys(message, common.concat(fields));
  const parsed = {
    type: message.type,
    requestId: requestId(message.requestId),
    channelId: channel(message.channelId),
    executeAt: number(message.executeAt, 0, 9_999_999_999_999, "jukebox_time_invalid", false),
  };
  if (fields.includes("trackId")) parsed.trackId = positiveId(message.trackId, true);
  if (fields.includes("position")) parsed.position = number(message.position ?? 0, 0, 86400, "jukebox_position_invalid");
  if (fields.includes("duration")) parsed.duration = number(message.duration, 0, 86400, "jukebox_duration_invalid", false);
  if (fields.includes("volume") && message.volume !== undefined) parsed.volume = number(message.volume, 0, 1, "jukebox_volume_invalid");
  if (fields.includes("fadeMs")) parsed.fadeMs = number(message.fadeMs ?? 1000, 0, 60000, "jukebox_fade_invalid");
  if (fields.includes("loop") && message.loop !== undefined) parsed.loop = boolean(message.loop, "jukebox_loop_invalid");
  if (fields.includes("muted") && message.muted !== undefined) parsed.muted = boolean(message.muted, "jukebox_mute_invalid");
  if (fields.includes("sourceType")) parsed.sourceType = sourceType(message.sourceType);
  if (fields.includes("playlistId") && message.playlistId !== undefined) parsed.playlistId = positiveId(message.playlistId, false);
  if (fields.includes("sourceLabel") && message.sourceLabel !== undefined) parsed.sourceLabel = optionalText(message.sourceLabel, 180, "jukebox_source_label_invalid");
  if (fields.includes("deviceLabel")) parsed.deviceLabel = optionalText(message.deviceLabel, 180, "jukebox_device_label_invalid");
  if (fields.includes("deviceSlot")) {
    const slot = optionalText(message.deviceSlot, 32, "jukebox_device_slot_invalid");
    if (!/^external-[12]$/.test(slot || "")) throw new ProtocolError("jukebox_device_slot_invalid");
    parsed.deviceSlot = slot;
  }
  return parsed;
};
