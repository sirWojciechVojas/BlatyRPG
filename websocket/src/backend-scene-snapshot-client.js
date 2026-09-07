export class BackendSceneSnapshotError extends Error {
  constructor(code, status = 503, details = {}) {
    super(code);
    this.name = "BackendSceneSnapshotError";
    this.code = String(code || "snapshot_unavailable");
    this.status = Number(status) || 503;
    this.details = details && typeof details === "object" ? details : {};
  }
}

const positiveId = (value) => {
  const number = Number(value);
  return Number.isSafeInteger(number) && number > 0 ? number : null;
};

const plainObject = (value) =>
  value !== null && typeof value === "object" && !Array.isArray(value);

const collection = (value) =>
  plainObject(value) && Array.isArray(value.items) && plainObject(value.capabilities);

const validSnapshot = (value, campaignId, sceneId) =>
  plainObject(value) &&
  plainObject(value.scene) &&
  positiveId(value.scene.id) === sceneId &&
  positiveId(value.scene.campaignId ?? value.scene.campaign_id) === campaignId &&
  collection(value.scenes) &&
  plainObject(value.capabilities) &&
  collection(value.tokens) &&
  collection(value.walls) &&
  collection(value.lights) &&
  collection(value.tiles) &&
  plainObject(value.combat) &&
  plainObject(value.combat.capabilities) &&
  plainObject(value.fog) &&
  collection(value.movementRequests);

export class BackendSceneSnapshotClient {
  constructor(config, options = {}) {
    this.baseUrl = config.backendInternalUrl;
    this.timeoutMs = config.backendTimeoutMs;
    this.fetch = options.fetch || globalThis.fetch;
  }

  async get(session, sceneId) {
    const id = positiveId(sceneId);
    if (!id) throw new BackendSceneSnapshotError("scene_id_invalid", 400);
    const endpoint =
      `${this.baseUrl}/campaigns/${session.campaignId}/scenes/${id}/snapshot`;
    let response;
    try {
      response = await this.fetch(endpoint, {
        method: "POST",
        headers: {
          Authorization: `Realtime ${session.realtimeTicket}`,
          "X-Realtime-Client-Instance": session.clientInstanceId,
        },
        signal: AbortSignal.timeout(this.timeoutMs),
      });
    } catch (_error) {
      throw new BackendSceneSnapshotError("snapshot_unavailable", 503);
    }

    let result;
    try {
      const text = await response.text();
      if (Buffer.byteLength(text, "utf8") > 1048576) {
        throw new Error("response_too_large");
      }
      result = JSON.parse(text);
    } catch (_error) {
      throw new BackendSceneSnapshotError("backend_response_invalid", 502);
    }
    if (!response.ok) {
      throw new BackendSceneSnapshotError(result?.code, response.status, {
        errors: result?.errors,
      });
    }
    if (!validSnapshot(result?.snapshot, session.campaignId, id)) {
      throw new BackendSceneSnapshotError("backend_response_invalid", 502);
    }
    return result.snapshot;
  }
}
