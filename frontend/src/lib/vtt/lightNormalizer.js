const number = (value, fallback = 0) => {
  const result = Number(value);
  return Number.isFinite(result) ? result : fallback;
};

const boolean = (value, fallback = false) => {
  if (value === undefined || value === null) return fallback;
  return value === true || value === 1 || value === "1";
};

export const normalizeLight = (source = {}) => ({
  id: number(source.id),
  sceneId: number(source.sceneId ?? source.scene_id),
  x: number(source.x),
  y: number(source.y),
  brightRadius: number(source.brightRadius ?? source.bright_radius, 200),
  dimRadius: number(source.dimRadius ?? source.dim_radius, 400),
  color: String(source.color || "#FFD27A"),
  intensity: number(source.intensity, 1),
  opacity: number(source.opacity, 1),
  softness: number(source.softness, 0.5),
  gradualIllumination: boolean(
    source.gradualIllumination ?? source.gradual_illumination,
    true,
  ),
  darknessMin: number(source.darknessMin ?? source.darkness_min, 0),
  darknessMax: number(source.darknessMax ?? source.darkness_max, 1),
  sourceType: String(source.sourceType ?? source.source_type ?? "light"),
  providesVision: boolean(source.providesVision ?? source.provides_vision),
  constrainedByWalls: boolean(
    source.constrainedByWalls ?? source.constrained_by_walls,
    true,
  ),
  animation: String(source.animation || "none"),
  animationSpeed: number(source.animationSpeed ?? source.animation_speed, 1),
  animationIntensity: number(
    source.animationIntensity ?? source.animation_intensity,
    0.5,
  ),
  elevation: number(source.elevation),
  enabled: boolean(source.enabled, true),
  hidden: boolean(source.hidden),
  revision: number(source.revision, 1),
  capabilities: { canManage: source.capabilities?.canManage === true },
});

export const lightWritePayload = (changes = {}, includeRevision = false) => {
  const allowed = [
    "x",
    "y",
    "brightRadius",
    "dimRadius",
    "color",
    "intensity",
    "opacity",
    "softness",
    "gradualIllumination",
    "darknessMin",
    "darknessMax",
    "sourceType",
    "providesVision",
    "constrainedByWalls",
    "animation",
    "animationSpeed",
    "animationIntensity",
    "elevation",
    "enabled",
    "hidden",
  ];
  const payload = {};
  allowed.forEach((key) => {
    if (changes[key] !== undefined) payload[key] = changes[key];
  });
  if (includeRevision) payload.revision = number(changes.revision);
  return payload;
};
