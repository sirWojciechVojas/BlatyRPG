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
