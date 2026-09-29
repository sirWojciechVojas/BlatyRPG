import { GRID_TYPES } from "@/lib/vtt/grid";

export const SCENE_DRAFT_FIELDS = Object.freeze([
  "name",
  "description",
  "backgroundUrl",
  "width",
  "height",
  "padding",
  "backgroundColor",
  "gridType",
  "gridSize",
  "gridDistance",
  "gridUnit",
  "gridOffsetX",
  "gridOffsetY",
  "gridColor",
  "gridOpacity",
  "globalLightLevel",
  "darknessLevel",
  "globalIllumination",
  "globalIlluminationThreshold",
  "fogExploration",
  "fogEnabled",
  "dynamicVision",
  "explorationMemory",
  "fogUnexploredColor",
  "fogExploredColor",
  "fogExplorationImage",
  "fogExplorationMode",
  "fogUnexploredOpacity",
  "fogExploredOpacity",
  "fogEdgeSoftness",
  "fogUpdateDuringDrag",
  "isVisible",
  "sortOrder",
]);

export const emptySceneDraft = () => ({
  name: "",
  description: "",
  backgroundUrl: "",
  width: 1920,
  height: 1080,
  padding: 0,
  backgroundColor: "#20242B",
  gridType: GRID_TYPES.SQUARE,
  gridSize: 100,
  gridDistance: 5,
  gridUnit: "m",
  gridOffsetX: 0,
  gridOffsetY: 0,
  gridColor: "#000000",
  gridOpacity: 0.35,
  globalLightLevel: 0.8,
  darknessLevel: 0.2,
  globalIllumination: false,
  globalIlluminationThreshold: 1,
  fogExploration: true,
  fogEnabled: false,
  dynamicVision: true,
  explorationMemory: true,
  fogUnexploredColor: "#05070B",
  fogExploredColor: "#202733",
  fogExplorationImage: "",
  fogExplorationMode: "individual",
  fogUnexploredOpacity: 1,
  fogExploredOpacity: 0.62,
  fogEdgeSoftness: 32,
  fogUpdateDuringDrag: true,
  isVisible: true,
  sortOrder: 0,
});

export const sceneDraftFrom = (scene = null) => {
  const source = { ...emptySceneDraft(), ...(scene || {}) };
  return Object.fromEntries(
    SCENE_DRAFT_FIELDS.map((field) => [field, source[field]]),
  );
};

export const sceneDraftPayload = (draft = {}) =>
  Object.fromEntries(SCENE_DRAFT_FIELDS.map((field) => [field, draft[field]]));

export const sceneDraftChanges = (draft = {}, baseline = {}) =>
  Object.fromEntries(
    SCENE_DRAFT_FIELDS.filter(
      (field) => !Object.is(draft[field], baseline[field]),
    ).map((field) => [field, draft[field]]),
  );

export const sceneDraftFingerprint = (draft = {}) =>
  JSON.stringify(sceneDraftPayload(draft));

const validationIssue = (code, params = {}) => ({ code, params });
const integerInRange = (value, minimum, maximum) =>
  Number.isInteger(Number(value)) &&
  Number(value) >= minimum &&
  Number(value) <= maximum;
const numberInRange = (value, minimum, maximum) =>
  Number.isFinite(Number(value)) &&
  Number(value) >= minimum &&
  Number(value) <= maximum;

export const isSafeSceneAssetUrl = (value) => {
  const url = String(value || "").trim();
  if (!url) return true;
  const hasControlCharacter = Array.from(url).some((character) => {
    const code = character.codePointAt(0);
    return code <= 0x1f || code === 0x7f;
  });
  if (hasControlCharacter || url.startsWith("//")) return false;
  const scheme = url.match(/^([a-z][a-z\d+.-]*):/iu)?.[1]?.toLowerCase();
  if (!scheme) return true;
  if (!["http", "https"].includes(scheme)) return false;
  try {
    return Boolean(new URL(url));
  } catch (_error) {
    return false;
  }
};

export const validateSceneDraft = (draft = {}) => {
  const errors = {};
  const name = String(draft.name || "").trim();
  if (!name) errors.name = validationIssue("required");
  else if (name.length > 150)
    errors.name = validationIssue("maxLength", { max: 150 });
  if (String(draft.description || "").length > 10000)
    errors.description = validationIssue("maxLength", { max: 10000 });
  const backgroundUrl = String(draft.backgroundUrl || "").trim();
  if (backgroundUrl.length > 2048)
    errors.backgroundUrl = validationIssue("maxLength", { max: 2048 });
  else if (!isSafeSceneAssetUrl(backgroundUrl))
    errors.backgroundUrl = validationIssue("assetUrl");

  for (const [field, minimum, maximum] of [
    ["width", 256, 50000],
    ["height", 256, 50000],
    ["padding", 0, 5000],
    ["gridSize", 1, 1000],
    ["sortOrder", -100000, 100000],
  ]) {
    if (!integerInRange(draft[field], minimum, maximum)) {
      errors[field] = validationIssue("integerRange", { minimum, maximum });
    }
  }
  for (const [field, minimum, maximum] of [
    ["gridDistance", 0.01, 1000000],
    ["gridOffsetX", -50000, 50000],
    ["gridOffsetY", -50000, 50000],
    ["gridOpacity", 0, 1],
    ["globalLightLevel", 0, 1],
    ["darknessLevel", 0, 1],
    ["globalIlluminationThreshold", 0, 1],
    ["fogUnexploredOpacity", 0, 1],
    ["fogExploredOpacity", 0, 1],
    ["fogEdgeSoftness", 0, 200],
  ]) {
    if (!numberInRange(draft[field], minimum, maximum)) {
      errors[field] = validationIssue("numberRange", { minimum, maximum });
    }
  }
  if (!Object.values(GRID_TYPES).includes(draft.gridType)) {
    errors.gridType = validationIssue("invalid");
  }
  const unit = String(draft.gridUnit || "").trim();
  if (!unit) errors.gridUnit = validationIssue("required");
  else if (unit.length > 32)
    errors.gridUnit = validationIssue("maxLength", { max: 32 });
  for (const field of [
    "backgroundColor",
    "gridColor",
    "fogUnexploredColor",
    "fogExploredColor",
  ]) {
    if (!/^#[\dA-F]{6}(?:[\dA-F]{2})?$/iu.test(String(draft[field] || ""))) {
      errors[field] = validationIssue("color");
    }
  }
  if (!["none", "individual", "shared"].includes(draft.fogExplorationMode)) {
    errors.fogExplorationMode = validationIssue("invalid");
  }
  if (!isSafeSceneAssetUrl(draft.fogExplorationImage)) {
    errors.fogExplorationImage = validationIssue("assetUrl");
  }
  return errors;
};

export const API_SCENE_FIELD_MAP = Object.freeze({
  name: "name",
  description: "description",
  background_url: "backgroundUrl",
  width: "width",
  height: "height",
  padding: "padding",
  background_color: "backgroundColor",
  grid_type: "gridType",
  grid_size: "gridSize",
  grid_distance: "gridDistance",
  grid_unit: "gridUnit",
  grid_offset_x: "gridOffsetX",
  grid_offset_y: "gridOffsetY",
  grid_color: "gridColor",
  grid_opacity: "gridOpacity",
  global_light_level: "globalLightLevel",
  darkness_level: "darknessLevel",
  global_illumination: "globalIllumination",
  global_illumination_threshold: "globalIlluminationThreshold",
  fog_exploration: "fogExploration",
  fog_enabled: "fogEnabled",
  dynamic_vision: "dynamicVision",
  exploration_memory: "explorationMemory",
  fog_unexplored_color: "fogUnexploredColor",
  fog_explored_color: "fogExploredColor",
  fog_exploration_image: "fogExplorationImage",
  fog_exploration_mode: "fogExplorationMode",
  fog_unexplored_opacity: "fogUnexploredOpacity",
  fog_explored_opacity: "fogExploredOpacity",
  fog_edge_softness: "fogEdgeSoftness",
  fog_update_during_drag: "fogUpdateDuringDrag",
  is_visible: "isVisible",
  sort_order: "sortOrder",
});

export const firstInvalidSection = (errors = {}) => {
  const fields = Object.keys(errors);
  if (!fields.length) return null;
  if (
    fields.some((field) =>
      ["name", "description", "sortOrder", "isVisible"].includes(field),
    )
  )
    return "basic";
  if (
    fields.some((field) =>
      [
        "backgroundUrl",
        "width",
        "height",
        "padding",
        "backgroundColor",
      ].includes(field),
    )
  )
    return "map";
  if (fields.some((field) => field.startsWith("grid"))) return "grid";
  if (
    fields.some((field) =>
      [
        "globalLightLevel",
        "darknessLevel",
        "globalIllumination",
        "globalIlluminationThreshold",
      ].includes(field),
    )
  )
    return "lighting";
  return "fog";
};
