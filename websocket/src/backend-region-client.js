import { BackendWallError } from "./backend-wall-client.js";

const positiveId = (value) => {
  const number = Number(value);
  return Number.isSafeInteger(number) && number > 0 ? number : null;
};

const region = (value) => {
  const id = positiveId(value?.id);
  const sceneId = positiveId(value?.sceneId ?? value?.scene_id);
  const revision = positiveId(value?.revision);
  if (!id || !sceneId || !revision || !Array.isArray(value?.polygons)) {
    throw new BackendWallError("backend_response_invalid", 502);
  }
  return { ...value, id, sceneId, revision };
};

export class BackendRegionClient {
  constructor(config, options = {}) {
    this.baseUrl = config.backendInternalUrl;
    this.timeoutMs = config.backendTimeoutMs;
    this.fetch = options.fetch || globalThis.fetch;
  }

  async change(session, payload) {
    let response;
    try {
      response = await this.fetch(
        `${this.baseUrl}/campaigns/${session.campaignId}/regions/change`,
        {
          method: "POST",
          headers: {
            Authorization: `Realtime ${session.realtimeTicket}`,
            "Content-Type": "application/json",
            "X-Realtime-Client-Instance": session.clientInstanceId,
          },
          body: JSON.stringify({
            operation: payload.operation,
            sceneId: payload.sceneId,
            ...(payload.regionId
              ? { regionId: payload.regionId, revision: payload.revision }
              : {}),
            ...(payload.changes ? { changes: payload.changes } : {}),
          }),
          signal: AbortSignal.timeout(this.timeoutMs),
        },
      );
    } catch (_error) {
      throw new BackendWallError("region_unavailable", 503);
    }
    let result;
    try {
      const text = await response.text();
      if (Buffer.byteLength(text, "utf8") > 524288) throw new Error("response_too_large");
      result = JSON.parse(text);
    } catch (_error) {
      throw new BackendWallError("backend_response_invalid", 502);
    }
    if (!response.ok) {
      throw new BackendWallError(result?.code, response.status, { errors: result?.errors });
    }
    if (payload.operation === "delete") {
      if (result?.deleted !== true || positiveId(result?.id) !== payload.regionId) {
        throw new BackendWallError("backend_response_invalid", 502);
      }
      return { operation: "delete", sceneId: payload.sceneId, regionId: payload.regionId };
    }
    return { operation: payload.operation, region: region(result?.region) };
  }
}
