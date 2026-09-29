export class BackendWallError extends Error {
  constructor(code, status = 503, details = {}) {
    super(code);
    this.name = "BackendWallError";
    this.code = String(code || "wall_unavailable");
    this.status = Number(status) || 503;
    this.details = details && typeof details === "object" ? details : {};
  }
}

const positiveId = (value) => {
  const number = Number(value);
  return Number.isSafeInteger(number) && number > 0 ? number : null;
};

const coordinate = (value) => {
  const number = Number(value);
  return Number.isFinite(number) && Math.abs(number) <= 1000000 ? number : null;
};

const wall = (value) => {
  const id = positiveId(value?.id);
  const sceneId = positiveId(value?.sceneId ?? value?.scene_id);
  const revision = positiveId(value?.revision);
  const coordinates = ["x1", "y1", "x2", "y2"].map((key) => coordinate(value?.[key]));
  if (!id || !sceneId || !revision || coordinates.some((item) => item === null)) {
    throw new BackendWallError("backend_response_invalid", 502);
  }
  return {
    ...value,
    id,
    sceneId,
    name: String(value.name || `Wall ${id}`),
    type: String(value.type || "wall"),
    x1: coordinates[0],
    y1: coordinates[1],
    x2: coordinates[2],
    y2: coordinates[3],
    revision,
    blocksMovement: value.blocksMovement === true,
    blocksSight: value.blocksSight === true,
    blocksLight: value.blocksLight === true,
    blocksSound: value.blocksSound !== false,
    wallType: String(value.wallType || "solid"),
    doorType: String(value.doorType || "none"),
    restrictionType: String(value.restrictionType || "normal"),
    proximityThreshold: finite(value.proximityThreshold, 10),
    playerOperable: value.playerOperable !== false,
    soundConfig: object(value.soundConfig),
    animationConfig: object(value.animationConfig),
    doorState: value.doorState ?? null,
    color: value.color || null,
    enabled: value.enabled !== false,
    hidden: value.hidden === true,
  };
};

const finite = (value, fallback) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
};

const object = (value) =>
  value && typeof value === "object" && !Array.isArray(value) ? value : {};

const audioItem = (value, loop) => {
  const playbackId = String(value?.playbackId || "");
  const audio = object(value?.audio);
  if (
    !/^wall-audio-[a-f0-9]{64}$/u.test(playbackId) ||
    !positiveId(audio.id) ||
    typeof audio.url !== "string" ||
    !audio.url.startsWith("/api/")
  ) {
    throw new BackendWallError("backend_response_invalid", 502);
  }
  return {
    playbackId,
    audio: {
      id: positiveId(audio.id),
      title: String(audio.title || ""),
      sourceType: String(audio.sourceType || "upload"),
      url: audio.url,
      duration: audio.duration === null ? null : finite(audio.duration, null),
    },
    volume: Math.max(0, Math.min(1, finite(value.volume, 0))),
    loop,
    fadeInMs: Math.max(0, Math.min(10000, finite(value.fadeInMs, 0))),
    fadeOutMs: Math.max(0, Math.min(10000, finite(value.fadeOutMs, 0))),
  };
};

export class BackendWallClient {
  constructor(config, options = {}) {
    this.baseUrl = config.backendInternalUrl;
    this.timeoutMs = config.backendTimeoutMs;
    this.fetch = options.fetch || globalThis.fetch;
  }

  async change(session, payload) {
    const endpoint = `${this.baseUrl}/campaigns/${session.campaignId}/walls/change`;
    let response;
    try {
      response = await this.fetch(endpoint, {
        method: "POST",
        headers: {
          Authorization: `Realtime ${session.realtimeTicket}`,
          "Content-Type": "application/json",
          "X-Realtime-Client-Instance": session.clientInstanceId,
        },
        body: JSON.stringify({
          operation: payload.operation,
          sceneId: payload.sceneId,
          ...(payload.wallId ? { wallId: payload.wallId, revision: payload.revision } : {}),
          ...(payload.changes ? { changes: payload.changes } : {}),
        }),
        signal: AbortSignal.timeout(this.timeoutMs),
      });
    } catch (_error) {
      throw new BackendWallError("wall_unavailable", 503);
    }
    let result;
    try {
      const text = await response.text();
      if (Buffer.byteLength(text, "utf8") > 131072) throw new Error("response_too_large");
      result = JSON.parse(text);
    } catch (_error) {
      throw new BackendWallError("backend_response_invalid", 502);
    }
    if (!response.ok) {
      throw new BackendWallError(result?.code, response.status, { errors: result?.errors });
    }
    if (payload.operation === "delete") {
      if (result?.deleted !== true || positiveId(result?.id) !== payload.wallId) {
        throw new BackendWallError("backend_response_invalid", 502);
      }
      return { operation: "delete", sceneId: payload.sceneId, wallId: payload.wallId };
    }
    return {
      operation: payload.operation,
      wall: wall(result?.wall),
      ...(result?.sound ? { sound: String(result.sound) } : {}),
    };
  }

  async audioState(session, sceneId, selectedTokenId = null) {
    const result = await this.post(session, "walls/audio-state", {
      sceneId,
      ...(selectedTokenId ? { selectedTokenId } : {}),
    });
    if (!Array.isArray(result?.activeLoops)) {
      throw new BackendWallError("backend_response_invalid", 502);
    }
    return {
      sceneId: positiveId(result.sceneId),
      activeLoops: result.activeLoops.map((item) => audioItem(item, true)),
      serverTime: finite(result.serverTime, Date.now()),
    };
  }

  async audioCue(session, sceneId, wallId, cue, selectedTokenId = null) {
    const result = await this.post(session, "walls/audio-cue", {
      sceneId,
      wallId,
      cue,
      ...(selectedTokenId ? { selectedTokenId } : {}),
    });
    if (!Array.isArray(result?.items)) {
      throw new BackendWallError("backend_response_invalid", 502);
    }
    return {
      sceneId: positiveId(result.sceneId),
      items: result.items.map((item) => audioItem(item, false)),
      serverTime: finite(result.serverTime, Date.now()),
    };
  }

  async post(session, path, body) {
    const endpoint = `${this.baseUrl}/campaigns/${session.campaignId}/${path}`;
    let response;
    try {
      response = await this.fetch(endpoint, {
        method: "POST",
        headers: {
          Authorization: `Realtime ${session.realtimeTicket}`,
          "Content-Type": "application/json",
          "X-Realtime-Client-Instance": session.clientInstanceId,
        },
        body: JSON.stringify(body),
        signal: AbortSignal.timeout(this.timeoutMs),
      });
    } catch (_error) {
      throw new BackendWallError("wall_audio_unavailable", 503);
    }
    let result;
    try {
      const text = await response.text();
      if (Buffer.byteLength(text, "utf8") > 131072)
        throw new Error("response_too_large");
      result = JSON.parse(text);
    } catch (_error) {
      throw new BackendWallError("backend_response_invalid", 502);
    }
    if (!response.ok) {
      throw new BackendWallError(result?.code, response.status, {
        errors: result?.errors,
      });
    }
    return result;
  }
}
