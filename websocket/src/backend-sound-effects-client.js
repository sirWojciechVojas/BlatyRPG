export class BackendSoundEffectsError extends Error {
  constructor(code, status = 503) {
    super(code);
    this.name = "BackendSoundEffectsError";
    this.code = String(code || "sound_effects_unavailable");
    this.status = Number(status) || 503;
  }
}

export class BackendSoundEffectsClient {
  constructor(config, options = {}) {
    this.baseUrl = config.backendInternalUrl;
    this.timeoutMs = config.backendTimeoutMs;
    this.fetch = options.fetch || globalThis.fetch;
  }

  state(session) {
    return this.request(session, "state", {});
  }

  command(session, payload) {
    return this.request(session, "command", payload);
  }

  async request(session, action, payload) {
    const endpoint = `${this.baseUrl}/campaigns/${session.campaignId}/sound-effects/${action}`;
    let response;
    try {
      response = await this.fetch(endpoint, {
        method: "POST",
        headers: {
          Authorization: `Realtime ${session.realtimeTicket}`,
          "Content-Type": "application/json",
          "X-Realtime-Client-Instance": session.clientInstanceId,
        },
        body: JSON.stringify(payload),
        signal: AbortSignal.timeout(this.timeoutMs),
      });
    } catch (_error) {
      throw new BackendSoundEffectsError("sound_effects_unavailable", 503);
    }
    let result;
    try {
      const text = await response.text();
      if (Buffer.byteLength(text, "utf8") > 262144) {
        throw new Error("response_too_large");
      }
      result = JSON.parse(text);
    } catch (_error) {
      throw new BackendSoundEffectsError("backend_response_invalid", 502);
    }
    if (!response.ok) {
      throw new BackendSoundEffectsError(result?.code, response.status);
    }
    if (!result || typeof result !== "object" || Array.isArray(result)) {
      throw new BackendSoundEffectsError("backend_response_invalid", 502);
    }
    return result;
  }
}
