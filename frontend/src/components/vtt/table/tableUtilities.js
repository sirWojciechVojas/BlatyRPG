export const TABLE_UTILITIES = Object.freeze([
  { id: "chat", icon: "chat", labelKey: "vtt.table.rail.chat" },
  { id: "graphics", icon: "image", labelKey: "vtt.table.rail.graphics" },
  { id: "characters", icon: "users", labelKey: "vtt.table.rail.characters" },
  { id: "handouts", icon: "file", labelKey: "vtt.table.rail.handouts" },
  { id: "scenario", icon: "book", labelKey: "vtt.table.rail.scenario" },
  { id: "shop", icon: "shop", labelKey: "vtt.table.rail.shop" },
  { id: "jukebox", icon: "music", labelKey: "vtt.table.rail.jukebox" },
  {
    id: "notifications",
    icon: "bell",
    labelKey: "vtt.table.rail.notifications",
  },
  { id: "settings", icon: "settings", labelKey: "vtt.table.rail.settings" },
]);

export const utilityById = (id) =>
  TABLE_UTILITIES.find((utility) => utility.id === id) || null;
