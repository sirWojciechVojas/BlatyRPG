const GRID_TYPES = new Set(["gridless", "square", "hex_pointy", "hex_flat"]);

const read = (source, snake, camel) =>
  Object.prototype.hasOwnProperty.call(source, snake)
    ? source[snake]
    : source[camel];

const numberOr = (value, fallback) => {
  const result = Number(value);
  return Number.isFinite(result) ? result : fallback;
};

const idOrNull = (value) => {
  if (value === null || value === undefined || value === "") return null;
  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : String(value);
};

export const normalizeCapabilities = (value = {}) => ({
  canManage: value.canManage === true || value.can_manage === true,
  canViewHidden: value.canViewHidden === true || value.can_view_hidden === true,
});

export const normalizeScene = (source) => {
  if (!source || typeof source !== "object") return null;
  const gridType = String(read(source, "grid_type", "gridType") || "square");
  return {
    id: idOrNull(source.id),
    campaignId: idOrNull(read(source, "campaign_id", "campaignId")),
    name: String(source.name || ""),
    description: String(source.description || ""),
    backgroundUrl: String(
      read(source, "background_url", "backgroundUrl") || "",
    ),
    width: numberOr(source.width, 1920),
    height: numberOr(source.height, 1080),
    padding: numberOr(source.padding, 0),
    gridType: GRID_TYPES.has(gridType) ? gridType : "square",
    gridSize: numberOr(read(source, "grid_size", "gridSize"), 100),
    gridDistance: numberOr(read(source, "grid_distance", "gridDistance"), 5),
    gridUnit: String(read(source, "grid_unit", "gridUnit") || "m"),
    gridOffsetX: numberOr(read(source, "grid_offset_x", "gridOffsetX"), 0),
    gridOffsetY: numberOr(read(source, "grid_offset_y", "gridOffsetY"), 0),
    gridColor: String(read(source, "grid_color", "gridColor") || "#000000"),
    gridOpacity: numberOr(read(source, "grid_opacity", "gridOpacity"), 0.35),
    backgroundColor: String(
      read(source, "background_color", "backgroundColor") || "#20242b",
    ),
    isVisible: read(source, "is_visible", "isVisible") !== false,
    sortOrder: numberOr(read(source, "sort_order", "sortOrder"), 0),
    revision: numberOr(source.revision, 0),
    createdAt: read(source, "created_at", "createdAt") || null,
    updatedAt: read(source, "updated_at", "updatedAt") || null,
  };
};

export const normalizeSceneCollection = (payload = {}) => ({
  items: Array.isArray(payload.items)
    ? payload.items.map(normalizeScene).filter(Boolean)
    : [],
  activeSceneId: idOrNull(payload.activeSceneId ?? payload.active_scene_id),
  capabilities: normalizeCapabilities(payload.capabilities),
});

const WRITE_FIELDS = [
  ["name", "name"],
  ["description", "description"],
  ["backgroundUrl", "background_url"],
  ["width", "width"],
  ["height", "height"],
  ["padding", "padding"],
  ["gridType", "grid_type"],
  ["gridSize", "grid_size"],
  ["gridDistance", "grid_distance"],
  ["gridUnit", "grid_unit"],
  ["gridOffsetX", "grid_offset_x"],
  ["gridOffsetY", "grid_offset_y"],
  ["gridColor", "grid_color"],
  ["gridOpacity", "grid_opacity"],
  ["backgroundColor", "background_color"],
  ["isVisible", "is_visible"],
  ["sortOrder", "sort_order"],
];

export const toSceneWritePayload = (source = {}, includeRevision = false) => {
  const payload = {};
  for (const [camel, snake] of WRITE_FIELDS) {
    if (Object.prototype.hasOwnProperty.call(source, camel)) {
      payload[snake] = source[camel];
    }
  }
  if (includeRevision) payload.revision = Number(source.revision);
  return payload;
};
