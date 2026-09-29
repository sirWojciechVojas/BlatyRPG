export class BackendJukeboxError extends Error {
  constructor(code, status = 503) {
    super(code);
    this.name = "BackendJukeboxError";
    this.code = String(code || "jukebox_unavailable");
    this.status = Number(status) || 503;
  }
}

const plainObject = (value) => value && typeof value === "object" && !Array.isArray(value);

export class BackendJukeboxClient {
  constructor(config, options = {}) {
    this.baseUrl = config.backendInternalUrl;
    this.timeoutMs = config.backendTimeoutMs;
    this.fetch = options.fetch || globalThis.fetch;
  }

  state(session) {
    return this.request(session, "state", {});
  }

  save(session, state, settings) {
    return this.request(session, "save", { state, settings });
  }

  async request(session, action, payload) {
    const endpoint = `${this.baseUrl}/campaigns/${session.campaignId}/jukebox/${action}`;
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
      throw new BackendJukeboxError("jukebox_unavailable", 503);
    }
    let result;
    try {
      const text = await response.text();
      if (Buffer.byteLength(text, "utf8") > 262144) throw new Error("response_too_large");
      result = JSON.parse(text);
    } catch (_error) {
      throw new BackendJukeboxError("backend_response_invalid", 502);
    }
    if (!response.ok) throw new BackendJukeboxError(result?.code, response.status);
    if (!plainObject(result?.state) || !plainObject(result?.settings)) {
      throw new BackendJukeboxError("backend_response_invalid", 502);
    }
    return result;
  }
}
