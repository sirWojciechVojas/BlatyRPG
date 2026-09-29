import { starterAssetById } from "./starterAssets";

export const MAP_DOCUMENT_VERSION = 1;
export const MAP_COORDINATE_UNIT = "px";
export const METERS_PER_GRID_CELL = 1;

let localSequence = 0;
export const mapObjectId = (prefix = "object") => {
  localSequence += 1;
  const random =
    (typeof window !== "undefined" && window.crypto?.randomUUID?.()) ||
    `${Date.now()}-${localSequence}`;
  return `${prefix}-${random}`;
};

export const createMapDocument = (options = {}) => ({
  schemaVersion: MAP_DOCUMENT_VERSION,
  coordinateSystem: {
    unit: MAP_COORDINATE_UNIT,
    origin: "top-left",
    yAxis: "down",
  },
  width: Math.max(800, Number(options.width) || 4000),
  height: Math.max(600, Number(options.height) || 3000),
  pixelsPerMeter: Math.max(10, Number(options.pixelsPerMeter) || 100),
  backgroundColor: options.backgroundColor || "#222820",
  grid: {
    type: ["square", "hex", "none"].includes(options.gridType)
      ? options.gridType
      : "square",
    size: Math.max(10, Number(options.gridSize) || 100),
    distance: Math.max(
      0.01,
      Number(options.gridDistance) || METERS_PER_GRID_CELL,
    ),
    unit: String(options.gridUnit || "m"),
    offsetX: 0,
    offsetY: 0,
    color: "#d8cab0",
    opacity: 0.22,
    snap: true,
  },
  levels: [{ id: "ground", name: "Parter", elevation: 0, visible: true }],
  activeLevelId: "ground",
  layers: [
    {
      id: "terrain",
      name: "Teren",
      kind: "terrain",
      order: 0,
      visible: true,
      opacity: 1,
      locked: false,
    },
    {
      id: "rooms",
      name: "Pomieszczenia",
      kind: "geometry",
      order: 10,
      visible: true,
      opacity: 1,
      locked: false,
    },
    {
      id: "objects",
      name: "Obiekty",
      kind: "objects",
      order: 20,
      visible: true,
      opacity: 1,
      locked: false,
    },
    {
      id: "walls",
      name: "Ściany i otwory",
      kind: "walls",
      order: 30,
      visible: true,
      opacity: 1,
      locked: false,
    },
    {
      id: "lights",
      name: "Światła",
      kind: "lights",
      order: 40,
      visible: true,
      opacity: 1,
      locked: false,
    },
    {
      id: "annotations",
      name: "Adnotacje",
      kind: "annotations",
      order: 50,
      visible: true,
      opacity: 1,
      locked: false,
    },
  ],
  objects: [],
  masks: [
    { id: "inside-rooms", kind: "dynamic-room-interior" },
    { id: "outside-rooms", kind: "dynamic-room-exterior" },
  ],
  private: { gmNotes: [] },
  metadata: {
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
    editor: "BlatyRPG Map Builder",
  },
});

export const cloneMapDocument = (document) =>
  JSON.parse(JSON.stringify(document));

export const layerForTool = (tool) =>
  ({
    terrain: "terrain",
    road: "terrain",
    river: "terrain",
    fence: "walls",
    roomRect: "rooms",
    roomCircle: "rooms",
    roomPolygon: "rooms",
    wall: "walls",
    door: "walls",
    window: "walls",
    text: "annotations",
    marker: "annotations",
    light: "lights",
  })[tool] || "objects";

export const snapPoint = (document, point) => {
  if (!document?.grid?.snap || document.grid.type === "none")
    return { ...point };
  const size = Math.max(1, Number(document.grid.size) || 100);
  const ox = Number(document.grid.offsetX) || 0;
  const oy = Number(document.grid.offsetY) || 0;
  return {
    x: Math.round((point.x - ox) / size) * size + ox,
    y: Math.round((point.y - oy) / size) * size + oy,
  };
};

const finite = (value, fallback = 0) =>
  Number.isFinite(Number(value)) ? Number(value) : fallback;
const clamped = (value, minimum, maximum) =>
  Math.min(maximum, Math.max(minimum, finite(value)));

export const normalizeMapObject = (source, document) => {
  const value = { ...source };
  value.id = String(value.id || mapObjectId(value.type || "object")).slice(
    0,
    128,
  );
  value.type = String(value.type || "asset");
  value.layerId = String(value.layerId || layerForTool(value.type));
  value.levelId = String(value.levelId || document.activeLevelId || "ground");
  value.x = clamped(value.x, 0, document.width);
  value.y = clamped(value.y, 0, document.height);
  value.width = clamped(value.width || 100, 1, document.width * 2);
  value.height = clamped(value.height || 100, 1, document.height * 2);
  value.rotation = ((finite(value.rotation) % 360) + 360) % 360;
  value.scaleX = clamped(value.scaleX ?? 1, -20, 20) || 1;
  value.scaleY = clamped(value.scaleY ?? 1, -20, 20) || 1;
  value.opacity = clamped(value.opacity ?? 1, 0, 1);
  value.visible = value.visible !== false;
  value.locked = value.locked === true;
  value.points = Array.isArray(value.points)
    ? value.points.slice(0, 2048).map((point) => ({
        x: clamped(point?.x, 0, document.width),
        y: clamped(point?.y, 0, document.height),
      }))
    : [];
  if (
    value.assetId &&
    !starterAssetById(value.assetId) &&
    !String(value.assetId).startsWith("custom.")
  ) {
    throw new TypeError(`unknown_asset:${value.assetId}`);
  }
  return value;
};

export const validateMapDocument = (source) => {
  const errors = [];
  if (!source || typeof source !== "object" || Array.isArray(source))
    errors.push("document_object_required");
  if (Number(source?.schemaVersion) !== MAP_DOCUMENT_VERSION)
    errors.push("schema_version_unsupported");
  if (finite(source?.width) < 800 || finite(source?.width) > 50000)
    errors.push("width_invalid");
  if (finite(source?.height) < 600 || finite(source?.height) > 50000)
    errors.push("height_invalid");
  if (
    !Array.isArray(source?.layers) ||
    source.layers.length < 1 ||
    source.layers.length > 128
  )
    errors.push("layers_invalid");
  if (
    !Array.isArray(source?.levels) ||
    source.levels.length < 1 ||
    source.levels.length > 32
  )
    errors.push("levels_invalid");
  if (!Array.isArray(source?.objects) || source.objects.length > 20000)
    errors.push("objects_invalid");
  const layerIds = new Set(
    (source?.layers || []).map((layer) => String(layer.id)),
  );
  const levelIds = new Set(
    (source?.levels || []).map((level) => String(level.id)),
  );
  if (!levelIds.has(String(source?.activeLevelId)))
    errors.push("active_level_invalid");
  const objectIds = new Set();
  for (const object of source?.objects || []) {
    if (!object?.id || objectIds.has(String(object.id)))
      errors.push("object_id_invalid");
    objectIds.add(String(object?.id));
    if (!layerIds.has(String(object?.layerId)))
      errors.push(`object_layer_invalid:${object?.id}`);
    if (!levelIds.has(String(object?.levelId)))
      errors.push(`object_level_invalid:${object?.id}`);
    for (const coordinate of [object?.x, object?.y]) {
      if (
        !Number.isFinite(Number(coordinate)) ||
        Math.abs(Number(coordinate)) > 1000000
      ) {
        errors.push(`object_coordinate_invalid:${object?.id}`);
      }
    }
  }
  return { valid: errors.length === 0, errors };
};

export const addAssetObject = (document, assetId, point, options = {}) => {
  const asset =
    options.asset?.id === assetId ? options.asset : starterAssetById(assetId);
  if (!asset) throw new TypeError(`unknown_asset:${assetId}`);
  if (asset.kind === "composition") {
    return asset.objects.map((entry) =>
      addAssetObject(
        document,
        entry.assetId,
        {
          x: point.x + entry.dx * document.pixelsPerMeter,
          y: point.y + entry.dy * document.pixelsPerMeter,
        },
        {
          rotation: entry.rotation,
          groupId: options.groupId || mapObjectId("group"),
        },
      ),
    );
  }
  if (asset.kind === "material") {
    return normalizeMapObject(
      {
        id: mapObjectId("terrain"),
        type: "terrain",
        layerId: "terrain",
        x: point.x,
        y: point.y,
        width: options.width || document.grid.size * 2,
        height: options.height || document.grid.size * 2,
        materialId: asset.id,
        color: asset.material.color,
        hardness: options.hardness ?? 0.65,
        flow: options.flow ?? 0.75,
      },
      document,
    );
  }
  const ppm = document.pixelsPerMeter;
  return normalizeMapObject(
    {
      id: mapObjectId("asset"),
      type: "asset",
      layerId: "objects",
      x: point.x,
      y: point.y,
      width: asset.physicalSize.width * ppm,
      height: asset.physicalSize.height * ppm,
      assetId: asset.id,
      assetVersion: Number(asset.version) || 1,
      assetUrl: asset.source?.url || null,
      anchor: asset.anchor || { x: 0.5, y: 0.5 },
      obstacle: asset.obstacle || null,
      light: asset.light
        ? {
            ...asset.light,
            brightRadius: Number(asset.light.brightRadius || 0) * ppm,
            dimRadius: Number(asset.light.dimRadius || 0) * ppm,
          }
        : null,
      rotation: options.rotation || 0,
      groupId: options.groupId || null,
      variant: options.variant || "standard",
    },
    document,
  );
};

export const createCommandHistory = (initialDocument, limit = 100) => {
  let current = cloneMapDocument(initialDocument);
  const undoStack = [];
  const redoStack = [];
  const snapshot = () => cloneMapDocument(current);
  const execute = (label, producer) => {
    const before = snapshot();
    const draft = snapshot();
    const produced = producer(draft) || draft;
    const validation = validateMapDocument(produced);
    if (!validation.valid) throw new TypeError(validation.errors.join(","));
    produced.metadata = {
      ...(produced.metadata || {}),
      updatedAt: new Date().toISOString(),
    };
    undoStack.push({ label, document: before });
    if (undoStack.length > limit) undoStack.shift();
    redoStack.length = 0;
    current = cloneMapDocument(produced);
    return snapshot();
  };
  return {
    get document() {
      return snapshot();
    },
    get canUndo() {
      return undoStack.length > 0;
    },
    get canRedo() {
      return redoStack.length > 0;
    },
    execute,
    undo() {
      const entry = undoStack.pop();
      if (!entry) return snapshot();
      redoStack.push({ label: entry.label, document: snapshot() });
      current = entry.document;
      return snapshot();
    },
    redo() {
      const entry = redoStack.pop();
      if (!entry) return snapshot();
      undoStack.push({ label: entry.label, document: snapshot() });
      current = entry.document;
      return snapshot();
    },
    replace(document) {
      const validation = validateMapDocument(document);
      if (!validation.valid) throw new TypeError(validation.errors.join(","));
      current = cloneMapDocument(document);
      undoStack.length = 0;
      redoStack.length = 0;
      return snapshot();
    },
  };
};

export const applyAiProposal = (document, proposal) => {
  if (!proposal || !Array.isArray(proposal.objects))
    throw new TypeError("ai_proposal_invalid");
  const lockedLayerIds = new Set(
    document.layers.filter((layer) => layer.locked).map((layer) => layer.id),
  );
  const lockedIds = new Set(
    document.objects
      .filter((item) => item.locked || lockedLayerIds.has(item.layerId))
      .map((item) => item.id),
  );
  const removeIds = new Set(
    (proposal.removeObjectIds || []).filter((id) => !lockedIds.has(id)),
  );
  const next = cloneMapDocument(document);
  next.objects = next.objects.filter((item) => !removeIds.has(item.id));
  for (const object of proposal.objects) {
    if (lockedLayerIds.has(object.layerId))
      throw new TypeError(`ai_locked_layer:${object.layerId}`);
    next.objects.push(normalizeMapObject(object, next));
  }
  const validation = validateMapDocument(next);
  if (!validation.valid) throw new TypeError(validation.errors.join(","));
  return next;
};

export const publicMapDocument = (document) => {
  const result = cloneMapDocument(document);
  delete result.private;
  const publicLayerIds = new Set(
    result.layers
      .filter((layer) => layer.visible !== false && layer.private !== true)
      .map((layer) => layer.id),
  );
  const publicLevelIds = new Set(
    result.levels
      .filter((level) => level.visible !== false)
      .map((level) => level.id),
  );
  result.levels = result.levels.filter((level) => publicLevelIds.has(level.id));
  result.layers = result.layers.filter((layer) => publicLayerIds.has(layer.id));
  result.objects = result.objects.filter(
    (item) =>
      publicLayerIds.has(item.layerId) &&
      publicLevelIds.has(item.levelId) &&
      item.private !== true &&
      item.visible !== false,
  );
  return result;
};
