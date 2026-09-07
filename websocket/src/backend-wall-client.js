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
    doorState: value.doorState ?? null,
    color: value.color || null,
    enabled: value.enabled !== false,
    hidden: value.hidden === true,
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
    return { operation: payload.operation, wall: wall(result?.wall) };
  }
}
