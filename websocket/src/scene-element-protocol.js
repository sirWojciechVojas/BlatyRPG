import { ProtocolError } from "./protocol-error.js";

const plainObject = (value) =>
  value !== null && typeof value === "object" && !Array.isArray(value);

const exactKeys = (value, allowed) => {
  for (const key of Object.keys(value)) {
    if (!allowed.includes(key))
      throw new ProtocolError("unexpected_field", key);
  }
};

const positiveId = (value, code) => {
  if (value === undefined || value === null) return null;
  if (!Number.isSafeInteger(value) || value < 1) throw new ProtocolError(code);
  return value;
};

const number = (value, code, minimum = -1000000, maximum = 1000000) => {
  if (
    typeof value !== "number" ||
    !Number.isFinite(value) ||
    value < minimum ||
    value > maximum
  ) {
    throw new ProtocolError(code);
  }
  return value;
};

const requestId = (value) => {
  const normalized = String(value || "");
  if (!/^[A-Za-z0-9._:-]{1,128}$/.test(normalized)) {
    throw new ProtocolError("request_id_required");
  }
  return normalized;
};

const wallChanges = (value, operation) => {
  if (!plainObject(value)) throw new ProtocolError("wall_changes_invalid");
  exactKeys(value, [
    "type",
    "x1",
    "y1",
    "x2",
    "y2",
    "blocksMovement",
    "blocksSight",
    "blocksLight",
    "doorState",
  ]);
  const changes = {};
  for (const field of ["x1", "y1", "x2", "y2"]) {
    if (value[field] !== undefined)
      changes[field] = number(value[field], `wall_${field}_invalid`);
  }
  for (const field of ["blocksMovement", "blocksSight", "blocksLight"]) {
    if (value[field] === undefined) continue;
    if (typeof value[field] !== "boolean")
      throw new ProtocolError(`wall_${field}_invalid`);
    changes[field] = value[field];
  }
  if (value.type !== undefined) {
    if (!["wall", "door", "secret"].includes(value.type))
      throw new ProtocolError("wall_type_invalid");
    changes.type = value.type;
  }
  if (value.doorState !== undefined) {
    if (!["closed", "open", "locked"].includes(value.doorState)) {
      throw new ProtocolError("wall_door_state_invalid");
    }
    changes.doorState = value.doorState;
  }
  if (
    operation === "create" &&
    !["x1", "y1", "x2", "y2"].every((key) => key in changes)
  ) {
    throw new ProtocolError("wall_geometry_required");
  }
  return changes;
};

const lightChanges = (value, operation) => {
  if (!plainObject(value)) throw new ProtocolError("light_changes_invalid");
  exactKeys(value, [
    "x",
    "y",
    "brightRadius",
    "dimRadius",
    "color",
    "intensity",
    "enabled",
    "hidden",
  ]);
  const changes = {};
  for (const field of ["x", "y"]) {
    if (value[field] !== undefined)
      changes[field] = number(value[field], `light_${field}_invalid`);
  }
  for (const field of ["brightRadius", "dimRadius"]) {
    if (value[field] !== undefined)
      changes[field] = number(
        value[field],
        `light_${field}_invalid`,
        0,
        100000,
      );
  }
  if (value.intensity !== undefined) {
    changes.intensity = number(
      value.intensity,
      "light_intensity_invalid",
      0,
      1,
    );
  }
  for (const field of ["enabled", "hidden"]) {
    if (value[field] === undefined) continue;
    if (typeof value[field] !== "boolean")
      throw new ProtocolError(`light_${field}_invalid`);
    changes[field] = value[field];
  }
  if (value.color !== undefined) {
    const color = String(value.color).toUpperCase();
    if (!/^#[0-9A-F]{6}(?:[0-9A-F]{2})?$/.test(color)) {
      throw new ProtocolError("light_color_invalid");
    }
    changes.color = color;
  }
  if (operation === "create" && !["x", "y"].every((key) => key in changes)) {
    throw new ProtocolError("light_geometry_required");
  }
  if (changes.brightRadius > changes.dimRadius) {
    throw new ProtocolError("light_radius_invalid");
  }
  return changes;
};

const tileChanges = (value, operation) => {
  if (!plainObject(value)) throw new ProtocolError("tile_changes_invalid");
  exactKeys(value, [
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
  ]);
  const changes = {};
  for (const field of ["x", "y", "rotation"]) {
    if (value[field] !== undefined)
      changes[field] = number(value[field], `tile_${field}_invalid`);
  }
  for (const field of ["width", "height"]) {
    if (value[field] !== undefined)
      changes[field] = number(value[field], `tile_${field}_invalid`, 8, 50000);
  }
  if (value.opacity !== undefined) {
    changes.opacity = number(value.opacity, "tile_opacity_invalid", 0, 1);
  }
  if (value.sortOrder !== undefined) {
    if (
      !Number.isSafeInteger(value.sortOrder) ||
      Math.abs(value.sortOrder) > 100000
    ) {
      throw new ProtocolError("tile_sort_order_invalid");
    }
    changes.sortOrder = value.sortOrder;
  }
  for (const field of ["hidden", "locked", "autoplay", "loop", "muted"]) {
    if (value[field] === undefined) continue;
    if (typeof value[field] !== "boolean")
      throw new ProtocolError(`tile_${field}_invalid`);
    changes[field] = value[field];
  }
  if (value.name !== undefined) {
    const name = String(value.name).trim();
    if (!name || Array.from(name).length > 150)
      throw new ProtocolError("tile_name_invalid");
    changes.name = name;
  }
  if (value.assetUrl !== undefined) {
    const url = String(value.assetUrl).trim();
    const safe = /^\/(?!\/)/u.test(url) || /^https?:\/\//iu.test(url);
    if (!safe || url.length > 2048)
      throw new ProtocolError("tile_asset_url_invalid");
    changes.assetUrl = url;
  }
  for (const [field, allowed] of [
    ["mediaType", ["image", "video"]],
    ["layer", ["background", "foreground"]],
  ]) {
    if (value[field] === undefined) continue;
    if (!allowed.includes(value[field]))
      throw new ProtocolError(`tile_${field}_invalid`);
    changes[field] = value[field];
  }
  if (
    operation === "create" &&
    !["assetUrl", "x", "y"].every((key) => key in changes)
  ) {
    throw new ProtocolError("tile_geometry_required");
  }
  return changes;
};

const definitions = {
  wall: { changes: wallChanges, id: "wallId" },
  light: { changes: lightChanges, id: "lightId" },
  tile: { changes: tileChanges, id: "tileId" },
};

export const parseSceneElementMessage = (message) => {
  const resource = String(message.type || "").replace(/\.change$/, "");
  const definition = definitions[resource];
  if (!definition || message.type !== `${resource}.change`) return null;
  const operation = String(message.operation || "");
  if (!["create", "update", "delete"].includes(operation)) {
    throw new ProtocolError(`${resource}_operation_invalid`);
  }
  const allowed = ["v", "type", "requestId", "operation", "sceneId"];
  if (operation !== "create") allowed.push(definition.id, "revision");
  if (operation !== "delete") allowed.push("changes");
  exactKeys(message, allowed);
  const sceneId = positiveId(message.sceneId, "scene_id_invalid");
  const elementId = positiveId(
    message[definition.id],
    `${resource}_id_invalid`,
  );
  const revision = positiveId(message.revision, `${resource}_revision_invalid`);
  if (!sceneId || (operation !== "create" && (!elementId || !revision))) {
    throw new ProtocolError(`${resource}_change_invalid`);
  }
  const changes =
    operation === "delete"
      ? null
      : definition.changes(message.changes, operation);
  if (operation === "update" && Object.keys(changes).length === 0) {
    throw new ProtocolError(`${resource}_changes_required`);
  }
  return {
    type: message.type,
    requestId: requestId(message.requestId),
    operation,
    sceneId,
    ...(elementId ? { [definition.id]: elementId, revision } : {}),
    ...(changes ? { changes } : {}),
  };
};
