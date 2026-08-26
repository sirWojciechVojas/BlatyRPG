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

const permissionScope = (value, legacyMode) => {
  if (value === undefined || value === null) return { mode: legacyMode, userIds: [] };
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return { mode: "gm", userIds: [] };
  }
  const mode = String(value.mode || "").toLowerCase();
  if (["gm", "everyone", "inherit"].includes(mode)) return { mode, userIds: [] };
  const ids = Array.isArray(value.userIds)
    ? value.userIds.map(positiveId).filter(Boolean).slice(0, 200)
    : [];
  return mode === "users" && ids.length
    ? { mode, userIds: [...new Set(ids)] }
    : { mode: "gm", userIds: [] };
};

export const normalizeBackendToken = (value) => {
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
    movementRange: Math.max(0, coordinate(value.movementRange) ?? 6),
    movementSpent: Math.max(0, coordinate(value.movementSpent) ?? 0),
    movementPoints: Math.max(0, coordinate(value.movementPoints) ?? 0),
    movementResetMode: ["turn", "round", "manual"].includes(
      value.movementResetMode,
    )
      ? value.movementResetMode
      : "turn",
    elevation: coordinate(value.elevation) ?? 0,
    revision,
    hidden: value.hidden === true,
    locked: value.locked === true,
    visibleTo: permissionScope(value.visibleTo, "everyone"),
    controlledBy: permissionScope(value.controlledBy, "inherit"),
    editableBy: permissionScope(value.editableBy, "gm"),
    observerBy: permissionScope(value.observerBy, "inherit"),
  };
};

export const normalizeMovementRequest = (value) => {
  const id = positiveId(value?.id);
  const sceneId = positiveId(value?.sceneId);
  const tokenId = positiveId(value?.tokenId);
  const requestedByUserId = positiveId(value?.requestedByUserId);
  if (!id || !sceneId || !tokenId || !requestedByUserId) {
    throw new BackendTokenError("backend_response_invalid", 502);
  }
  return {
    ...value,
    id,
    sceneId,
    tokenId,
    requestedByUserId,
    tokenName: String(value.tokenName || "").slice(0, 150),
    requesterName: String(value.requesterName || "").slice(0, 100),
    cost: Math.max(0, coordinate(value.cost) ?? 0),
    spent: Math.max(0, coordinate(value.spent) ?? 0),
    range: Math.max(0, coordinate(value.range) ?? 0),
    status: String(value.status || "pending"),
  };
};

export class BackendTokenClient {
  constructor(config, options = {}) {
    this.baseUrl = config.backendInternalUrl;
    this.timeoutMs = config.backendTimeoutMs;
    this.fetch = options.fetch || globalThis.fetch;
  }

  async move(session, payload) {
    const result = await this.post(session, "tokens/move", {
      sceneId: payload.sceneId,
      tokenId: payload.tokenId,
      revision: payload.revision,
      x: payload.x,
      y: payload.y,
      waypoints: payload.waypoints,
    });
    return {
      token: normalizeBackendToken(result?.token),
      publishToPlayers: result?.visibility?.publishToPlayers === true,
    };
  }

  async requestMovement(session, payload) {
    const result = await this.post(session, "tokens/movement-requests", {
      sceneId: payload.sceneId,
      tokenId: payload.tokenId,
      revision: payload.revision,
      x: payload.x,
      y: payload.y,
      waypoints: payload.waypoints,
    });
    return { request: normalizeMovementRequest(result?.request) };
  }

  async resolveMovement(session, payload) {
    const result = await this.post(
      session,
      `tokens/movement-requests/${payload.movementRequestId}/resolve`,
      { decision: payload.decision },
    );
    return {
      request: normalizeMovementRequest(result?.request),
      ...(result?.token
        ? {
            token: normalizeBackendToken(result.token),
            publishToPlayers: result?.visibility?.publishToPlayers === true,
          }
        : {}),
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
    return result;
  }
}
