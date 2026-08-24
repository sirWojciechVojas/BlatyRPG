export class BackendTileError extends Error {
  constructor(code, status = 503, details = {}) {
    super(code);
    this.name = "BackendTileError";
    this.code = String(code || "tile_unavailable");
    this.status = Number(status) || 503;
    this.details = details && typeof details === "object" ? details : {};
  }
}

const positiveId = (value) => {
  const number = Number(value);
  return Number.isSafeInteger(number) && number > 0 ? number : null;
};

const finiteNumber = (value, minimum = -1000000, maximum = 1000000) => {
  const number = Number(value);
  return Number.isFinite(number) && number >= minimum && number <= maximum
    ? number
    : null;
};

const tile = (value) => {
  const id = positiveId(value?.id);
  const sceneId = positiveId(value?.sceneId ?? value?.scene_id);
  const revision = positiveId(value?.revision);
  const assetUrl = String(value?.assetUrl ?? value?.asset_url ?? "");
  const mediaType = String(value?.mediaType ?? value?.media_type ?? "");
  const layer = String(value?.layer || "");
  const numbers = {
    x: finiteNumber(value?.x),
    y: finiteNumber(value?.y),
    width: finiteNumber(value?.width, 8, 50000),
    height: finiteNumber(value?.height, 8, 50000),
    rotation: finiteNumber(value?.rotation),
    opacity: finiteNumber(value?.opacity, 0, 1),
  };
  if (
    !id ||
    !sceneId ||
    !revision ||
    !assetUrl ||
    !["image", "video"].includes(mediaType) ||
    !["background", "foreground"].includes(layer) ||
    Object.values(numbers).some((item) => item === null)
  ) {
    throw new BackendTileError("backend_response_invalid", 502);
  }
  return {
    ...value,
    id,
    sceneId,
    name: String(value.name || "Tile"),
    assetUrl,
    mediaType,
    layer,
    ...numbers,
    sortOrder: Number(value.sortOrder ?? value.sort_order) || 0,
    hidden: value.hidden === true,
    locked: value.locked === true,
    autoplay: value.autoplay === true,
    loop: value.loop === true,
    muted: value.muted === true,
    revision,
  };
};

export class BackendTileClient {
  constructor(config, options = {}) {
    this.baseUrl = config.backendInternalUrl;
    this.timeoutMs = config.backendTimeoutMs;
    this.fetch = options.fetch || globalThis.fetch;
  }

  async change(session, payload) {
    const endpoint = `${this.baseUrl}/campaigns/${session.campaignId}/tiles/change`;
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
          ...(payload.tileId
            ? { tileId: payload.tileId, revision: payload.revision }
            : {}),
          ...(payload.changes ? { changes: payload.changes } : {}),
        }),
        signal: AbortSignal.timeout(this.timeoutMs),
      });
    } catch (_error) {
      throw new BackendTileError("tile_unavailable", 503);
    }
    let result;
    try {
      const text = await response.text();
      if (Buffer.byteLength(text, "utf8") > 131072)
        throw new Error("response_too_large");
      result = JSON.parse(text);
    } catch (_error) {
      throw new BackendTileError("backend_response_invalid", 502);
    }
    if (!response.ok) {
      throw new BackendTileError(result?.code, response.status, {
        errors: result?.errors,
      });
    }
    if (payload.operation === "delete") {
      if (
        result?.deleted !== true ||
        positiveId(result?.id) !== payload.tileId
      ) {
        throw new BackendTileError("backend_response_invalid", 502);
      }
      return {
        operation: "delete",
        sceneId: payload.sceneId,
        tileId: payload.tileId,
        hidden: result.hidden === true,
      };
    }
    return { operation: payload.operation, tile: tile(result?.tile) };
  }
}
