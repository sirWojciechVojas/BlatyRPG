const number = (value, fallback = 0) => {
  const result = Number(value);
  return Number.isFinite(result) ? result : fallback;
};

const boolean = (value) => value === true || value === 1 || value === "1";

export const normalizeWall = (source = {}) => ({
  id: number(source.id),
  sceneId: number(source.sceneId ?? source.scene_id),
  name: String(source.name || `Wall ${number(source.id)}`),
  type: String(source.type || "wall"),
  x1: number(source.x1),
  y1: number(source.y1),
  x2: number(source.x2),
  y2: number(source.y2),
  blocksMovement: boolean(source.blocksMovement ?? source.blocks_movement),
  blocksSight: boolean(source.blocksSight ?? source.blocks_sight),
  blocksLight: boolean(source.blocksLight ?? source.blocks_light),
  doorState: source.doorState ?? source.door_state ?? null,
  color: source.color ? String(source.color).toUpperCase() : null,
  enabled:
    source.enabled === undefined && source.is_enabled === undefined
      ? true
      : boolean(source.enabled ?? source.is_enabled),
  hidden: boolean(source.hidden),
  revision: number(source.revision, 1),
  capabilities: { canManage: source.capabilities?.canManage === true },
});

export const wallWritePayload = (changes = {}, includeRevision = false) => {
  const allowed = [
    "name",
    "type",
    "x1",
    "y1",
    "x2",
    "y2",
    "blocksMovement",
    "blocksSight",
    "blocksLight",
    "doorState",
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
