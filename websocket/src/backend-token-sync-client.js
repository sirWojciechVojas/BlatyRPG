import {
  BackendTokenError,
  normalizeBackendToken,
} from "./backend-token-client.js";

const normalizedTokenResult = (value) => ({
  token: normalizeBackendToken(value?.token),
  publishToPlayers: value?.publishToPlayers === true,
  changedFields: Array.isArray(value?.changedFields)
    ? value.changedFields.map(String).slice(0, 64)
    : [],
});

export class BackendTokenSyncClient {
  constructor(config, options = {}) {
    this.baseUrl = config.backendInternalUrl;
    this.timeoutMs = config.backendTimeoutMs;
    this.fetch = options.fetch || globalThis.fetch;
  }

  async command(session, payload) {
    const endpoint = `${this.baseUrl}/campaigns/${session.campaignId}/token-sync/command`;
    let response;
    try {
      response = await this.fetch(endpoint, {
        method: "POST",
        headers: {
          Authorization: `Realtime ${session.realtimeTicket}`,
          "Content-Type": "application/json",
          "X-Realtime-Client-Instance": session.clientInstanceId,
        },
        body: JSON.stringify({ action: payload.action, data: payload.data }),
        signal: AbortSignal.timeout(this.timeoutMs),
      });
    } catch (_error) {
      throw new BackendTokenError("token_sync_unavailable", 503);
    }
    let result;
    try {
      const text = await response.text();
      if (Buffer.byteLength(text, "utf8") > 524288) {
        throw new Error("response_too_large");
      }
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
      synchronizedTokens: Array.isArray(result?.synchronizedTokens)
        ? result.synchronizedTokens.map(normalizedTokenResult)
        : [],
      links: Array.isArray(result?.links) ? result.links : [],
      link: result?.link || null,
      deleted: result?.deleted === true,
      id: Number(result?.id) || null,
    };
  }
}
