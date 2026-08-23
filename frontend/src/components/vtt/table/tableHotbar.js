export const DEFAULT_TABLE_HOTBAR_ACTIONS = Object.freeze([
  "fit",
  "zoom-in",
  "chat",
  "characters",
  "scenes",
  "settings",
]);

export const tableHotbarActions = (translate, canManage) => [
  {
    id: "zoom-out",
    icon: "zoomOut",
    label: translate("vtt.scene.actions.zoomOut"),
  },
  { id: "fit", icon: "fit", label: translate("vtt.scene.actions.fit") },
  {
    id: "zoom-in",
    icon: "zoomIn",
    label: translate("vtt.scene.actions.zoomIn"),
  },
  {
    id: "refresh",
    icon: "refresh",
    label: translate("vtt.scene.actions.refresh"),
  },
  { id: "chat", icon: "chat", label: translate("vtt.table.rail.chat") },
  {
    id: "characters",
    icon: "users",
    label: translate("vtt.table.rail.characters"),
  },
  { id: "scenes", icon: "scene", label: translate("vtt.table.rail.scenes") },
  {
    id: "settings",
    icon: "settings",
    label: translate("vtt.scene.actions.settings"),
    disabled: !canManage,
  },
];
