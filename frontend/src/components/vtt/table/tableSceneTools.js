export const TABLE_SCENE_TOOLS = Object.freeze([
  { id: "select", icon: "cursor", labelKey: "vtt.table.tools.select" },
  {
    id: "map-builder",
    icon: "palette",
    labelKey: "vtt.table.tools.mapBuilder",
    gmOnly: true,
  },
  { id: "tokens", icon: "token", labelKey: "vtt.table.tools.tokens" },
  { id: "measure", icon: "ruler", labelKey: "vtt.table.tools.measure" },
  { id: "templates", icon: "template", labelKey: "vtt.table.tools.templates" },
  {
    id: "walls",
    icon: "wall",
    labelKey: "vtt.table.tools.walls",
    gmOnly: true,
  },
  {
    id: "lights",
    icon: "light",
    labelKey: "vtt.table.tools.lights",
    gmOnly: true,
  },
  {
    id: "sounds",
    icon: "music",
    labelKey: "vtt.table.tools.sounds",
    gmOnly: true,
  },
  {
    id: "tiles",
    icon: "layers",
    labelKey: "vtt.table.tools.tiles",
    gmOnly: true,
  },
  { id: "drawings", icon: "pencil", labelKey: "vtt.table.tools.drawings" },
  { id: "notes", icon: "pin", labelKey: "vtt.table.tools.notes", gmOnly: true },
  {
    id: "regions",
    icon: "region",
    labelKey: "vtt.table.tools.regions",
    gmOnly: true,
  },
  { id: "fog", icon: "fog", labelKey: "vtt.table.tools.fog", gmOnly: true },
  { id: "grid", icon: "grid", labelKey: "vtt.table.tools.grid", gmOnly: true },
]);

export const implementedSceneTool = (id) =>
  [
    "select",
    "map-builder",
    "tokens",
    "measure",
    "templates",
    "walls",
    "lights",
    "tiles",
    "regions",
    "fog",
    "grid",
  ].includes(id);

export const toggledSceneTool = (activeId, selectedId) =>
  activeId === selectedId && selectedId !== "select" ? "select" : selectedId;
