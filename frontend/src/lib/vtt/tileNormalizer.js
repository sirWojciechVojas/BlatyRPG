const number = (value, fallback = 0) => {
  const result = Number(value);
  return Number.isFinite(result) ? result : fallback;
};

const boolean = (value, fallback = false) => {
  if (value === undefined || value === null) return fallback;
  return value === true || value === 1 || value === "1";
};

export const normalizeTile = (source = {}) => ({
  id: number(source.id),
  sceneId: number(source.sceneId ?? source.scene_id),
  name: String(source.name || "Tile"),
  assetUrl: String(source.assetUrl ?? source.asset_url ?? ""),
  mediaType: String(source.mediaType ?? source.media_type ?? "image"),
  layer: String(source.layer || "background"),
  x: number(source.x),
  y: number(source.y),
  width: number(source.width, 200),
  height: number(source.height, 200),
  rotation: number(source.rotation),
  opacity: number(source.opacity, 1),
  sortOrder: number(source.sortOrder ?? source.sort_order),
  hidden: boolean(source.hidden),
  locked: boolean(source.locked),
  autoplay: boolean(source.autoplay, true),
  loop: boolean(source.loop, true),
  muted: boolean(source.muted, true),
  revision: number(source.revision, 1),
  capabilities: { canManage: source.capabilities?.canManage === true },
});

export const tileWritePayload = (changes = {}, includeRevision = false) => {
  const allowed = [
    "name",
    "assetUrl",
    "mediaType",
    "layer",
    "x",
    "y",
    "width",
    "height",
    "rotation",
    "opacity",
    "sortOrder",
    "hidden",
    "locked",
    "autoplay",
    "loop",
    "muted",
  ];
  const payload = {};
  allowed.forEach((key) => {
    if (changes[key] !== undefined) payload[key] = changes[key];
  });
  if (includeRevision) payload.revision = number(changes.revision);
  return payload;
};
