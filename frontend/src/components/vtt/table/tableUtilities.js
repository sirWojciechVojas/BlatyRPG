export const TABLE_UTILITIES = Object.freeze([
  { id: "chat", icon: "chat", labelKey: "vtt.table.rail.chat" },
  { id: "combat", icon: "sword", labelKey: "vtt.table.rail.combat" },
  { id: "graphics", icon: "image", labelKey: "vtt.table.rail.graphics" },
  { id: "characters", icon: "users", labelKey: "vtt.table.rail.characters" },
  { id: "items", icon: "package", labelKey: "vtt.table.rail.items" },
  { id: "handouts", icon: "file", labelKey: "vtt.table.rail.handouts" },
  { id: "scenario", icon: "book", labelKey: "vtt.table.rail.scenario" },
  { id: "scenes", icon: "scene", labelKey: "vtt.table.rail.scenes" },
  { id: "tables", icon: "dice", labelKey: "vtt.table.rail.tables" },
  { id: "shop", icon: "shop", labelKey: "vtt.table.rail.shop" },
  { id: "jukebox", icon: "music", labelKey: "vtt.table.rail.jukebox" },
  { id: "compendium", icon: "database", labelKey: "vtt.table.rail.compendium" },
  {
    id: "notifications",
    icon: "bell",
    labelKey: "vtt.table.rail.notifications",
  },
  { id: "settings", icon: "settings", labelKey: "vtt.table.rail.settings" },
]);

export const IMPLEMENTED_TABLE_UTILITIES = Object.freeze([
  "chat",
  "graphics",
  "characters",
  "scenario",
  "scenes",
  "shop",
  "notifications",
  "settings",
]);

export const utilityById = (id) =>
  TABLE_UTILITIES.find((utility) => utility.id === id) || null;
