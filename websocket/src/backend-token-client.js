export class BackendTokenError extends Error {
  constructor(code, status = 503, details = {}) {
    super(code);
    this.name = "BackendTokenError";
    this.code = String(code || "token_unavailable");
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

const token = (value) => {
  const id = positiveId(value?.id);
  const sceneId = positiveId(value?.sceneId ?? value?.scene_id);
  const revision = positiveId(value?.revision);
  const x = coordinate(value?.x);
  const y = coordinate(value?.y);
  if (!id || !sceneId || !revision || x === null || y === null) {
    throw new BackendTokenError("backend_response_invalid", 502);
  }
  return {
    ...value,
    id,
    sceneId,
    characterId: positiveId(value.characterId ?? value.character_id),
    name: String(value.name || "").slice(0, 150),
    imageUrl: String(value.imageUrl ?? value.image_url ?? "").slice(0, 2048),
    x,
    y,
    width: coordinate(value.width) ?? 100,
    height: coordinate(value.height) ?? 100,
    rotation: coordinate(value.rotation) ?? 0,
    facing: coordinate(value.facing ?? value.rotation) ?? 0,
    elevation: coordinate(value.elevation) ?? 0,
    revision,
    hidden: value.hidden === true,
    locked: value.locked === true,
  };
};

export class BackendTokenClient {
  constructor(config, options = {}) {
    this.baseUrl = config.backendInternalUrl;
    this.timeoutMs = config.backendTimeoutMs;
    this.fetch = options.fetch || globalThis.fetch;
  }

  async move(session, payload) {
    const endpoint = `${this.baseUrl}/campaigns/${session.campaignId}/tokens/move`;
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
          sceneId: payload.sceneId,
          tokenId: payload.tokenId,
          revision: payload.revision,
          x: payload.x,
          y: payload.y,
        }),
        signal: AbortSignal.timeout(this.timeoutMs),
      });
    } catch (_error) {
      throw new BackendTokenError("token_unavailable", 503);
    }
    let result;
    try {
      const text = await response.text();
      if (Buffer.byteLength(text, "utf8") > 131072) throw new Error("response_too_large");
      result = JSON.parse(text);
    } catch (_error) {
      throw new BackendTokenError("backend_response_invalid", 502);
    }
    if (!response.ok) {
      throw new BackendTokenError(result?.code, response.status, {
        errors: result?.errors,
      });
    }
    return {
      token: token(result?.token),
      publishToPlayers: result?.visibility?.publishToPlayers === true,
    };
  }
}
