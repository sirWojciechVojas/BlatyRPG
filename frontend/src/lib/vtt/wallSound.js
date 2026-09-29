export const WALL_SOUND_VERSION = 2;
export const WALL_SOUND_TRIGGERS = Object.freeze([
  "open",
  "close",
  "lock",
  "lockedAttempt",
  "proximityLoop",
]);
export const WALL_SOUND_GEOMETRIES = Object.freeze(["points", "offsetLine"]);
export const WALL_SOUND_ZONE_MIN = 1;
export const WALL_SOUND_ZONE_MAX = 12;

const clamp = (value, minimum, maximum) =>
  Math.min(maximum, Math.max(minimum, Number(value) || 0));

const positiveId = (value) => {
  const id = Number(value);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
};

const uniqueId = (prefix = "wall-sound") =>
  (typeof crypto !== "undefined" && crypto.randomUUID?.()) ||
  `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;

const safeLegacyUrl = (value) => {
  const url = String(value || "").trim();
  return /^\/(?!\/)/u.test(url) || /^https?:\/\//iu.test(url) ? url : "";
};

export const createWallSoundRule = (overrides = {}) => ({
  id: String(overrides.id || uniqueId()),
  enabled: overrides.enabled !== false,
  trigger: WALL_SOUND_TRIGGERS.includes(overrides.trigger)
    ? overrides.trigger
    : "proximityLoop",
  trackId: positiveId(overrides.trackId),
  ...(safeLegacyUrl(overrides.legacyUrl)
    ? { legacyUrl: safeLegacyUrl(overrides.legacyUrl) }
    : {}),
  volume: clamp(overrides.volume ?? 1, 0, 1),
  fadeInMs: Math.round(clamp(overrides.fadeInMs ?? 250, 0, 10000)),
  fadeOutMs: Math.round(clamp(overrides.fadeOutMs ?? 350, 0, 10000)),
  range: clamp(overrides.range ?? 10, 0.1, 100000),
  zoneCount: Math.round(
    clamp(overrides.zoneCount ?? 3, WALL_SOUND_ZONE_MIN, WALL_SOUND_ZONE_MAX),
  ),
  geometry: normalizeWallSoundGeometry(overrides.geometry),
});

export const normalizeWallSoundGeometry = (source = {}) => {
  const mode = WALL_SOUND_GEOMETRIES.includes(source?.mode)
    ? source.mode
    : "points";
  const points = Array.isArray(source?.points)
    ? source.points.slice(0, 16).map((value) => clamp(value, 0, 1))
    : [0.5];
  return {
    mode,
    points: [...new Set((points.length ? points : [0.5]).map(Number))].sort(
      (left, right) => left - right,
    ),
    offset: clamp(source?.offset ?? 0, -100000, 100000),
  };
};

const legacyRules = (source) =>
  ["open", "close", "lock", "lockedAttempt"]
    .filter((trigger) => safeLegacyUrl(source?.[trigger]))
    .map((trigger) =>
      createWallSoundRule({
        id: `legacy-${trigger}`,
        trigger,
        legacyUrl: source[trigger],
        volume: source.volume ?? 1,
      }),
    );

export const normalizeWallSoundConfig = (source = {}) => {
  const rules =
    Number(source?.version) === WALL_SOUND_VERSION &&
    Array.isArray(source.rules)
      ? source.rules.slice(0, 16).map(createWallSoundRule)
      : legacyRules(source);
  return { version: WALL_SOUND_VERSION, rules };
};

export const sceneUnitsPerPixel = (scene = {}) => {
  const gridSize = Math.max(0.0001, Number(scene.gridSize) || 100);
  const gridDistance = Math.max(0.0001, Number(scene.gridDistance) || 5);
  return gridDistance / gridSize;
};

export const sceneDistanceToPixels = (distance, scene) =>
  Math.max(0, Number(distance) || 0) / sceneUnitsPerPixel(scene);

export const scenePixelsToDistance = (pixels, scene) =>
  Math.max(0, Number(pixels) || 0) * sceneUnitsPerPixel(scene);

export const pointAlongWall = (wall, position = 0.5) => {
  const t = clamp(position, 0, 1);
  return {
    x: Number(wall?.x1) + (Number(wall?.x2) - Number(wall?.x1)) * t,
    y: Number(wall?.y1) + (Number(wall?.y2) - Number(wall?.y1)) * t,
  };
};

export const wallSoundOffsetSegment = (wall, geometry, scene) => {
  const dx = Number(wall?.x2) - Number(wall?.x1);
  const dy = Number(wall?.y2) - Number(wall?.y1);
  const length = Math.max(0.0001, Math.hypot(dx, dy));
  const offset = sceneDistanceToPixels(geometry?.offset, scene);
  const nx = -dy / length;
  const ny = dx / length;
  return {
    x1: Number(wall?.x1) + nx * offset,
    y1: Number(wall?.y1) + ny * offset,
    x2: Number(wall?.x2) + nx * offset,
    y2: Number(wall?.y2) + ny * offset,
    nx,
    ny,
  };
};

export const wallSoundEmitters = (wall, rule, scene) => {
  const geometry = normalizeWallSoundGeometry(rule?.geometry);
  if (geometry.mode === "offsetLine") {
    return [
      { type: "segment", ...wallSoundOffsetSegment(wall, geometry, scene) },
    ];
  }
  return geometry.points.map((position) => ({
    type: "point",
    position,
    ...pointAlongWall(wall, position),
  }));
};

export const distanceToSegment = (point, segment) => {
  const dx = Number(segment.x2) - Number(segment.x1);
  const dy = Number(segment.y2) - Number(segment.y1);
  const squared = dx * dx + dy * dy;
  if (squared <= 0.000001) {
    return Math.hypot(
      Number(point.x) - Number(segment.x1),
      Number(point.y) - Number(segment.y1),
    );
  }
  const t = clamp(
    ((Number(point.x) - Number(segment.x1)) * dx +
      (Number(point.y) - Number(segment.y1)) * dy) /
      squared,
    0,
    1,
  );
  return Math.hypot(
    Number(point.x) - (Number(segment.x1) + dx * t),
    Number(point.y) - (Number(segment.y1) + dy * t),
  );
};

export const wallSoundDistance = (wall, rule, listener, scene) => {
  const distances = wallSoundEmitters(wall, rule, scene).map((emitter) =>
    emitter.type === "point"
      ? Math.hypot(
          Number(listener?.x) - emitter.x,
          Number(listener?.y) - emitter.y,
        )
      : distanceToSegment(listener, emitter),
  );
  return scenePixelsToDistance(Math.min(...distances), scene);
};

const smoothstep = (value) => {
  const t = clamp(value, 0, 1);
  return t * t * (3 - 2 * t);
};

export const wallSoundZones = (rule) => {
  const normalized = createWallSoundRule(rule);
  const width = normalized.range / normalized.zoneCount;
  return Array.from({ length: normalized.zoneCount }, (_unused, index) => ({
    index,
    inner: width * index,
    outer: width * (index + 1),
    innerGain: normalized.volume * (1 - index / normalized.zoneCount),
    outerGain: normalized.volume * (1 - (index + 1) / normalized.zoneCount),
  }));
};

export const wallSoundGainAtDistance = (rule, distance) => {
  const zones = wallSoundZones(rule);
  const value = Math.max(0, Number(distance) || 0);
  const zone = zones.find((candidate) => value <= candidate.outer);
  if (!zone) return 0;
  const progress =
    (value - zone.inner) / Math.max(0.0001, zone.outer - zone.inner);
  return Math.max(
    0,
    zone.innerGain + (zone.outerGain - zone.innerGain) * smoothstep(progress),
  );
};

export const wallSoundGain = (wall, rule, listeners, scene) => {
  if (wall?.enabled === false || rule?.enabled === false) return 0;
  const points = Array.isArray(listeners) ? listeners : [listeners];
  if (!points.filter(Boolean).length) return 0;
  return Math.max(
    ...points
      .filter(Boolean)
      .map((listener) =>
        wallSoundGainAtDistance(
          rule,
          wallSoundDistance(wall, rule, listener, scene),
        ),
      ),
  );
};

export const wallSoundListenerPoint = (token = {}) => ({
  x: Number(token.x) + Math.max(0, Number(token.width) || 0) / 2,
  y: Number(token.y) + Math.max(0, Number(token.height) || 0) / 2,
});

export const wallSoundTrackPlayable = (track) =>
  Boolean(
    positiveId(track?.id) &&
    track?.url &&
    String(track?.sourceType || "").toLowerCase() !== "external" &&
    String(track?.provider || "").toLowerCase() !== "youtube",
  );

export const projectPointToWall = (wall, point) => {
  const dx = Number(wall?.x2) - Number(wall?.x1);
  const dy = Number(wall?.y2) - Number(wall?.y1);
  const squared = dx * dx + dy * dy;
  if (squared <= 0.000001) return 0.5;
  return clamp(
    ((Number(point?.x) - Number(wall?.x1)) * dx +
      (Number(point?.y) - Number(wall?.y1)) * dy) /
      squared,
    0,
    1,
  );
};
