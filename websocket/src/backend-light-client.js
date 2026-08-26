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
  const opacity = finiteNumber(value?.opacity ?? 1, 0, 1);
  const softness = finiteNumber(value?.softness ?? 0.5, 0, 1);
  const darknessMin = finiteNumber(value?.darknessMin ?? value?.darkness_min ?? 0, 0, 1);
  const darknessMax = finiteNumber(value?.darknessMax ?? value?.darkness_max ?? 1, 0, 1);
  const animationSpeed = finiteNumber(value?.animationSpeed ?? value?.animation_speed ?? 1, 0.1, 10);
  const animationIntensity = finiteNumber(
    value?.animationIntensity ?? value?.animation_intensity ?? 0.5,
    0,
    1,
  );
  const elevation = finiteNumber(value?.elevation ?? 0);
  const color = String(value?.color || "").toUpperCase();
  const sourceType = String(value?.sourceType ?? value?.source_type ?? "light");
  const animation = String(value?.animation || "none");
  if (
    !id ||
    !sceneId ||
    !revision ||
    x === null ||
    y === null ||
    brightRadius === null ||
    dimRadius === null ||
    intensity === null ||
    opacity === null ||
    softness === null ||
    darknessMin === null ||
    darknessMax === null ||
    darknessMin > darknessMax ||
    animationSpeed === null ||
    animationIntensity === null ||
    elevation === null ||
    brightRadius > dimRadius ||
    !["light", "darkness"].includes(sourceType) ||
    !["none", "flicker", "pulse", "vortex"].includes(animation) ||
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
    opacity,
    softness,
    gradualIllumination: value.gradualIllumination !== false,
    darknessMin,
    darknessMax,
    sourceType,
    providesVision: value.providesVision === true,
    constrainedByWalls: value.constrainedByWalls !== false,
    animation,
    animationSpeed,
    animationIntensity,
    elevation,
    enabled: value.enabled === true,
    hidden: value.hidden === true,
    revision,
  };
};

const scene = (value) => {
  const id = positiveId(value?.id);
  const revision = positiveId(value?.revision);
  const darknessLevel = finiteNumber(
    value?.darknessLevel ?? value?.darkness_level,
    0,
    1,
  );
  if (!id || !revision || darknessLevel === null) {
    throw new BackendLightError("backend_response_invalid", 502);
  }
  return {
    ...value,
    id,
    revision,
    darknessLevel,
    globalIllumination:
      value.globalIllumination === true || value.global_illumination === true,
    fogExploration:
      value.fogExploration !== false && value.fog_exploration !== false,
    isVisible: value.isVisible !== false && value.is_visible !== false,
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
    if (payload.operation === "syncScene") {
      return { operation: payload.operation, scene: scene(result?.scene) };
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
