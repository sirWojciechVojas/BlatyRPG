export class BackendLightError extends Error {
  constructor(code, status = 503, details = {}) {
    super(code);
    this.name = "BackendLightError";
    this.code = String(code || "light_unavailable");
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

const light = (value) => {
  const id = positiveId(value?.id);
  const sceneId = positiveId(value?.sceneId ?? value?.scene_id);
  const revision = positiveId(value?.revision);
  const x = finiteNumber(value?.x);
  const y = finiteNumber(value?.y);
  const brightRadius = finiteNumber(
    value?.brightRadius ?? value?.bright_radius,
    0,
    100000,
  );
  const dimRadius = finiteNumber(
    value?.dimRadius ?? value?.dim_radius,
    0,
    100000,
  );
  const intensity = finiteNumber(value?.intensity, 0, 1);
  const color = String(value?.color || "").toUpperCase();
  if (
    !id ||
    !sceneId ||
    !revision ||
    x === null ||
    y === null ||
    brightRadius === null ||
    dimRadius === null ||
    intensity === null ||
    brightRadius > dimRadius ||
    !/^#[0-9A-F]{6}(?:[0-9A-F]{2})?$/.test(color)
  ) {
    throw new BackendLightError("backend_response_invalid", 502);
  }
  return {
    ...value,
    id,
    sceneId,
    x,
    y,
    brightRadius,
    dimRadius,
    color,
    intensity,
    enabled: value.enabled === true,
    hidden: value.hidden === true,
    revision,
  };
};

export class BackendLightClient {
  constructor(config, options = {}) {
    this.baseUrl = config.backendInternalUrl;
    this.timeoutMs = config.backendTimeoutMs;
    this.fetch = options.fetch || globalThis.fetch;
  }

  async change(session, payload) {
    const endpoint = `${this.baseUrl}/campaigns/${session.campaignId}/lights/change`;
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
          ...(payload.lightId
            ? { lightId: payload.lightId, revision: payload.revision }
            : {}),
          ...(payload.changes ? { changes: payload.changes } : {}),
        }),
        signal: AbortSignal.timeout(this.timeoutMs),
      });
    } catch (_error) {
      throw new BackendLightError("light_unavailable", 503);
    }
    let result;
    try {
      const text = await response.text();
      if (Buffer.byteLength(text, "utf8") > 131072)
        throw new Error("response_too_large");
      result = JSON.parse(text);
    } catch (_error) {
      throw new BackendLightError("backend_response_invalid", 502);
    }
    if (!response.ok) {
      throw new BackendLightError(result?.code, response.status, {
        errors: result?.errors,
      });
    }
    if (payload.operation === "delete") {
      if (
        result?.deleted !== true ||
        positiveId(result?.id) !== payload.lightId
      ) {
        throw new BackendLightError("backend_response_invalid", 502);
      }
      return {
        operation: "delete",
        sceneId: payload.sceneId,
        lightId: payload.lightId,
      };
    }
    return { operation: payload.operation, light: light(result?.light) };
  }
}
