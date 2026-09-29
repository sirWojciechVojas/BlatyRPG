const number = (value, fallback = 0) => {
  const result = Number(value);
  return Number.isFinite(result) ? result : fallback;
};

const boolean = (value, fallback = false) => {
  if (value === undefined || value === null) return fallback;
  return value === true || value === 1 || value === "1";
};

const sourceType = (source) => {
  const value = String(source.sourceType ?? source.source_type ?? "omni");
  return value === "light" ? "omni" : value;
};

export const normalizeLight = (source = {}) => ({
  id: number(source.id),
  sceneId: number(source.sceneId ?? source.scene_id),
  x: number(source.x),
  y: number(source.y),
  name: String(source.name || "Light"),
  lumens: number(source.lumens, number(source.intensity, 1) * 800),
  direction: number(source.direction),
  angle: number(source.angle, 90),
  areaWidth: number(source.areaWidth ?? source.area_width, 400),
  areaHeight: number(source.areaHeight ?? source.area_height, 400),
  brightRadius: number(source.brightRadius ?? source.bright_radius, 200),
  dimRadius: number(source.dimRadius ?? source.dim_radius, 400),
  color: String(source.color || "#FFD27A"),
  intensity: number(source.intensity, 1),
  opacity: number(source.opacity, 1),
  softness: number(source.softness, 0.5),
  clarity: number(source.clarity, 0),
  gradualIllumination: boolean(
    source.gradualIllumination ?? source.gradual_illumination,
    true,
  ),
  darknessMin: number(source.darknessMin ?? source.darkness_min, 0),
  darknessMax: number(source.darknessMax ?? source.darkness_max, 1),
  sourceType: sourceType(source),
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
  animationReverse: boolean(
    source.animationReverse ?? source.animation_reverse,
  ),
  brightness: number(source.brightness, 1),
  saturation: number(source.saturation, 1),
  contrast: number(source.contrast, 1),
  edgeSoftness: number(source.edgeSoftness ?? source.edge_softness, 0.5),
  transitionRatio: number(
    source.transitionRatio ?? source.transition_ratio,
    0.5,
  ),
  assetUrl: String(source.assetUrl ?? source.asset_url ?? ""),
  elevation: number(source.elevation),
  enabled: boolean(source.enabled, true),
  hidden: boolean(source.hidden),
  revision: number(source.revision, 1),
  capabilities: { canManage: source.capabilities?.canManage === true },
});

export const lightWritePayload = (changes = {}, includeRevision = false) => {
  const allowed = [
    "name",
    "lumens",
    "direction",
    "angle",
    "areaWidth",
    "areaHeight",
    "x",
    "y",
    "brightRadius",
    "dimRadius",
    "color",
    "intensity",
    "opacity",
    "softness",
    "clarity",
    "gradualIllumination",
    "darknessMin",
    "darknessMax",
    "sourceType",
    "providesVision",
    "constrainedByWalls",
    "animation",
    "animationSpeed",
    "animationIntensity",
    "animationReverse",
    "brightness",
    "saturation",
    "contrast",
    "edgeSoftness",
    "transitionRatio",
    "assetUrl",
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
