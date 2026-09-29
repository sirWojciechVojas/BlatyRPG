const number = (value, fallback = 0) => {
  const result = Number(value);
  return Number.isFinite(result) ? result : fallback;
};

const boolean = (value, fallback = false) => {
  if (value === undefined || value === null) return fallback;
  return value === true || value === 1 || value === "1";
};

const polygons = (value) =>
  (Array.isArray(value) ? value : [])
    .map((polygon) =>
      (Array.isArray(polygon) ? polygon : [])
        .map((point) => ({ x: number(point?.x), y: number(point?.y) }))
        .filter(
          (point) => Number.isFinite(point.x) && Number.isFinite(point.y),
        ),
    )
    .filter((polygon) => polygon.length >= 3);

export const normalizeRegion = (source = {}) => ({
  id: number(source.id),
  sceneId: number(source.sceneId ?? source.scene_id),
  name: String(source.name || "Region"),
  polygons: polygons(source.polygons ?? source.polygons_json),
  darknessMode: String(
    source.darknessMode ?? source.darkness_mode ?? "override",
  ),
  darknessValue: number(source.darknessValue ?? source.darkness_value),
  disableGlobalIllumination: boolean(
    source.disableGlobalIllumination ?? source.disable_global_illumination,
  ),
  color: String(source.color || "#8B5CF6"),
  enabled: boolean(source.enabled, true),
  hidden: boolean(source.hidden),
  revision: number(source.revision, 1),
  capabilities: { canManage: source.capabilities?.canManage === true },
});

export const regionWritePayload = (changes = {}, includeRevision = false) => {
  const allowed = [
    "name",
    "polygons",
    "darknessMode",
    "darknessValue",
    "disableGlobalIllumination",
    "color",
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
