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
  const rawDarkness = read(source, "darkness_level", "darknessLevel");
  const rawGlobalLight = read(source, "global_light_level", "globalLightLevel");
  const legacyGlobal =
    read(source, "global_illumination", "globalIllumination") === true ||
    read(source, "global_illumination", "globalIllumination") === 1 ||
    read(source, "global_illumination", "globalIllumination") === "1";
  const storedDarkness = numberOr(rawDarkness, 0.2);
  const hasExplicitGlobalLight =
    rawGlobalLight !== undefined &&
    rawGlobalLight !== null &&
    rawGlobalLight !== "";
  const globalLightLevel = hasExplicitGlobalLight
    ? numberOr(rawGlobalLight, 0.8)
    : 1 - storedDarkness * (legacyGlobal ? 0.18 : 1);
  const legacyDarkness = hasExplicitGlobalLight
    ? numberOr(rawDarkness, 1 - globalLightLevel)
    : 1 - globalLightLevel;
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
    globalLightLevel: Math.min(1, Math.max(0, globalLightLevel)),
    darknessLevel: Math.min(1, Math.max(0, legacyDarkness)),
    globalIllumination: legacyGlobal,
    globalIlluminationThreshold: numberOr(
      read(
        source,
        "global_illumination_threshold",
        "globalIlluminationThreshold",
      ),
      1,
    ),
    fogExploration:
      read(source, "fog_exploration", "fogExploration") !== false &&
      read(source, "fog_exploration", "fogExploration") !== 0 &&
      read(source, "fog_exploration", "fogExploration") !== "0",
    fogEnabled:
      read(source, "fog_enabled", "fogEnabled") === true ||
      read(source, "fog_enabled", "fogEnabled") === 1 ||
      read(source, "fog_enabled", "fogEnabled") === "1",
    dynamicVision:
      read(source, "dynamic_vision", "dynamicVision") !== false &&
      read(source, "dynamic_vision", "dynamicVision") !== 0 &&
      read(source, "dynamic_vision", "dynamicVision") !== "0",
    explorationMemory:
      read(source, "exploration_memory", "explorationMemory") !== false &&
      read(source, "exploration_memory", "explorationMemory") !== 0 &&
      read(source, "exploration_memory", "explorationMemory") !== "0",
    fogUnexploredColor: String(
      read(source, "fog_unexplored_color", "fogUnexploredColor") || "#05070B",
    ),
    fogExploredColor: String(
      read(source, "fog_explored_color", "fogExploredColor") || "#202733",
    ),
    fogExplorationImage: String(
      read(source, "fog_exploration_image", "fogExplorationImage") || "",
    ),
    fogExplorationMode: String(
      read(source, "fog_exploration_mode", "fogExplorationMode") ||
        (read(source, "exploration_memory", "explorationMemory") === false
          ? "none"
          : "individual"),
    ),
    darknessTransition: {
      from: numberOr(
        read(source, "darkness_transition_from", "darknessTransitionFrom"),
        legacyDarkness,
      ),
      to: numberOr(
        read(source, "darkness_transition_to", "darknessTransitionTo"),
        legacyDarkness,
      ),
      startedAt:
        read(
          source,
          "darkness_transition_started_at",
          "darknessTransitionStartedAt",
        ) || null,
      duration: numberOr(
        read(
          source,
          "darkness_transition_duration",
          "darknessTransitionDuration",
        ),
        0,
      ),
    },
    fogUnexploredOpacity: numberOr(
      read(source, "fog_unexplored_opacity", "fogUnexploredOpacity"),
      1,
    ),
    fogExploredOpacity: numberOr(
      read(source, "fog_explored_opacity", "fogExploredOpacity"),
      0.62,
    ),
    fogEdgeSoftness: (() => {
      const softness = numberOr(
        read(source, "fog_edge_softness", "fogEdgeSoftness"),
        32,
      );
      return Math.min(200, Math.max(0, softness));
    })(),
    fogUpdateDuringDrag:
      read(source, "fog_update_during_drag", "fogUpdateDuringDrag") !== false &&
      read(source, "fog_update_during_drag", "fogUpdateDuringDrag") !== 0 &&
      read(source, "fog_update_during_drag", "fogUpdateDuringDrag") !== "0",
    isVisible:
      read(source, "is_visible", "isVisible") !== false &&
      read(source, "is_visible", "isVisible") !== 0 &&
      read(source, "is_visible", "isVisible") !== "0",
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
  ["globalLightLevel", "global_light_level"],
  ["darknessLevel", "darkness_level"],
  ["globalIllumination", "global_illumination"],
  ["globalIlluminationThreshold", "global_illumination_threshold"],
  ["fogExploration", "fog_exploration"],
  ["fogEnabled", "fog_enabled"],
  ["dynamicVision", "dynamic_vision"],
  ["explorationMemory", "exploration_memory"],
  ["fogUnexploredColor", "fog_unexplored_color"],
  ["fogExploredColor", "fog_explored_color"],
  ["fogExplorationImage", "fog_exploration_image"],
  ["fogExplorationMode", "fog_exploration_mode"],
  ["fogUnexploredOpacity", "fog_unexplored_opacity"],
  ["fogExploredOpacity", "fog_explored_opacity"],
  ["fogEdgeSoftness", "fog_edge_softness"],
  ["fogUpdateDuringDrag", "fog_update_during_drag"],
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
