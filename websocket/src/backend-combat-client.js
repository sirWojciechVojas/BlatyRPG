export class BackendCombatError extends Error {
  constructor(code, status = 503, details = {}) {
    super(code);
    this.name = "BackendCombatError";
    this.code = String(code || "combat_unavailable");
    this.status = Number(status) || 503;
    this.details = details && typeof details === "object" ? details : {};
  }
}

export class BackendCombatClient {
  constructor(config, options = {}) {
    this.baseUrl = config.backendInternalUrl;
    this.timeoutMs = config.backendTimeoutMs;
    this.fetch = options.fetch || globalThis.fetch;
  }

  async command(session, payload) {
    const endpoint = `${this.baseUrl}/campaigns/${session.campaignId}/combat/commands`;
    let response;
    try {
      response = await this.fetch(endpoint, {
        method: "POST",
        headers: {
          Authorization: `Realtime ${session.realtimeTicket}`,
          "Content-Type": "application/json",
          "X-Realtime-Client-Instance": session.clientInstanceId,
        },
        body: JSON.stringify({ sceneId: payload.sceneId, ...payload.command }),
        signal: AbortSignal.timeout(this.timeoutMs),
      });
    } catch (_error) {
      throw new BackendCombatError("combat_unavailable", 503);
    }
    let result;
    try {
      const text = await response.text();
      if (Buffer.byteLength(text, "utf8") > 262144) throw new Error("response_too_large");
      result = JSON.parse(text);
    } catch (_error) {
      throw new BackendCombatError("backend_response_invalid", 502);
    }
    if (!response.ok) {
      throw new BackendCombatError(result?.code, response.status, {
        errors: result?.errors,
      });
    }
    if (!result?.combat || typeof result.combat !== "object") {
      throw new BackendCombatError("backend_response_invalid", 502);
    }
    return {
      action: String(result.action || payload.command.action),
      movementChanged: result.movementChanged === true,
      publishToPlayers: result.publishToPlayers === true,
    };
  }
}
