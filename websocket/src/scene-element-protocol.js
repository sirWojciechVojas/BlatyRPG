import { ProtocolError } from "./protocol-error.js";
import { lightChanges } from "./light-change-protocol.js";
import { exactKeys, number, plainObject } from "./scene-element-validation.js";

const positiveId = (value, code) => {
  if (value === undefined || value === null) return null;
  if (!Number.isSafeInteger(value) || value < 1) throw new ProtocolError(code);
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
    "name",
    "type",
    "x1",
    "y1",
    "x2",
    "y2",
    "blocksMovement",
    "blocksSight",
    "blocksLight",
    "blocksSound",
    "wallType",
    "doorType",
    "restrictionType",
    "proximityThreshold",
    "playerOperable",
    "soundConfig",
    "animationConfig",
    "doorState",
    "actingTokenIds",
    "silent",
    "color",
    "enabled",
    "hidden",
  ]);
  const changes = {};
  for (const field of ["x1", "y1", "x2", "y2"]) {
    if (value[field] !== undefined)
      changes[field] = number(value[field], `wall_${field}_invalid`);
  }
  for (const field of [
    "blocksMovement",
    "blocksSight",
    "blocksLight",
    "blocksSound",
    "playerOperable",
    "enabled",
    "hidden",
  ]) {
    if (value[field] === undefined) continue;
    if (typeof value[field] !== "boolean")
      throw new ProtocolError(`wall_${field}_invalid`);
    changes[field] = value[field];
  }
  for (const [field, values] of [
    ["wallType", ["solid", "terrain", "invisible", "ethereal", "custom"]],
    ["doorType", ["none", "door", "secret", "window"]],
    ["restrictionType", ["normal", "limited", "proximity"]],
  ]) {
    if (value[field] === undefined) continue;
    if (!values.includes(value[field])) {
      throw new ProtocolError(`wall_${field}_invalid`);
    }
    changes[field] = value[field];
  }
  if (value.proximityThreshold !== undefined) {
    changes.proximityThreshold = number(
      value.proximityThreshold,
      "wall_proximity_threshold_invalid",
      0,
      100000,
    );
  }
  for (const field of ["soundConfig", "animationConfig"]) {
    if (value[field] === undefined) continue;
    if (!plainObject(value[field]) || JSON.stringify(value[field]).length > 16384) {
      throw new ProtocolError(`wall_${field}_invalid`);
    }
    changes[field] = value[field];
  }
  if (value.name !== undefined) {
    const name = String(value.name).trim();
    if (!name || Array.from(name).length > 150)
      throw new ProtocolError("wall_name_invalid");
    changes.name = name;
  }
  if (value.color !== undefined) {
    if (value.color === null || value.color === "") changes.color = null;
    else if (!/^#[0-9a-f]{6}([0-9a-f]{2})?$/iu.test(value.color))
      throw new ProtocolError("wall_color_invalid");
    else changes.color = String(value.color).toUpperCase();
  }
  if (value.type !== undefined) {
    if (
      ![
        "wall",
        "door",
        "window",
        "secret",
        "terrain",
        "invisible",
        "ethereal",
      ].includes(value.type)
    )
      throw new ProtocolError("wall_type_invalid");
    changes.type = value.type;
  }
  if (value.doorState !== undefined) {
    if (!["closed", "open", "locked"].includes(value.doorState)) {
      throw new ProtocolError("wall_door_state_invalid");
    }
    changes.doorState = value.doorState;
  }
  if (value.actingTokenIds !== undefined) {
    if (operation !== "interact" || !Array.isArray(value.actingTokenIds)) {
      throw new ProtocolError("wall_acting_token_ids_invalid");
    }
    const ids = value.actingTokenIds.map((id) =>
      positiveId(id, "wall_acting_token_ids_invalid"),
    );
    if (ids.length > 50 || ids.some((id) => id === null)) {
      throw new ProtocolError("wall_acting_token_ids_invalid");
    }
    changes.actingTokenIds = [...new Set(ids)];
  }
  if (value.silent !== undefined) {
    if (operation !== "interact" || typeof value.silent !== "boolean") {
      throw new ProtocolError("wall_silent_invalid");
    }
    changes.silent = value.silent;
  }
  if (
    operation === "create" &&
    !["x1", "y1", "x2", "y2"].every((key) => key in changes)
  ) {
    throw new ProtocolError("wall_geometry_required");
  }
  return changes;
};

const regionChanges = (value, operation) => {
  if (!plainObject(value)) throw new ProtocolError("region_changes_invalid");
  exactKeys(value, [
    "name",
    "polygons",
    "darknessMode",
    "darknessValue",
    "disableGlobalIllumination",
    "color",
    "enabled",
    "hidden",
  ]);
  const changes = {};
  if (value.name !== undefined) {
    const name = String(value.name).trim();
    if (!name || Array.from(name).length > 150)
      throw new ProtocolError("region_name_invalid");
    changes.name = name;
  }
  if (value.polygons !== undefined) {
    if (!Array.isArray(value.polygons) || !value.polygons.length || value.polygons.length > 32) {
      throw new ProtocolError("region_polygons_invalid");
    }
    let count = 0;
    changes.polygons = value.polygons.map((polygon) => {
      if (!Array.isArray(polygon) || polygon.length < 3 || polygon.length > 1000) {
        throw new ProtocolError("region_polygon_invalid");
      }
      count += polygon.length;
      if (count > 5000) throw new ProtocolError("region_polygons_invalid");
      return polygon.map((point) => {
        if (!plainObject(point)) throw new ProtocolError("region_point_invalid");
        exactKeys(point, ["x", "y"]);
        return {
          x: number(point.x, "region_x_invalid"),
          y: number(point.y, "region_y_invalid"),
        };
      });
    });
  }
  if (value.darknessMode !== undefined) {
    if (!["add", "subtract", "override"].includes(value.darknessMode)) {
      throw new ProtocolError("region_darkness_mode_invalid");
    }
    changes.darknessMode = value.darknessMode;
  }
  if (value.darknessValue !== undefined) {
    changes.darknessValue = number(
      value.darknessValue,
      "region_darkness_value_invalid",
      0,
      1,
    );
  }
  for (const field of ["disableGlobalIllumination", "enabled", "hidden"]) {
    if (value[field] === undefined) continue;
    if (typeof value[field] !== "boolean") {
      throw new ProtocolError(`region_${field}_invalid`);
    }
    changes[field] = value[field];
  }
  if (value.color !== undefined) {
    if (!/^#[0-9a-f]{6}([0-9a-f]{2})?$/iu.test(value.color)) {
      throw new ProtocolError("region_color_invalid");
    }
    changes.color = String(value.color).toUpperCase();
  }
  if (operation === "create" && !changes.polygons) {
    throw new ProtocolError("region_polygons_required");
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
  region: { changes: regionChanges, id: "regionId" },
};

export const parseSceneElementMessage = (message) => {
  const resource = String(message.type || "").replace(/\.change$/, "");
  const definition = definitions[resource];
  if (!definition || message.type !== `${resource}.change`) return null;
  const operation = String(message.operation || "");
  if (resource === "light" && operation === "syncScene") {
    exactKeys(message, ["v", "type", "requestId", "operation", "sceneId"]);
    const sceneId = positiveId(message.sceneId, "scene_id_invalid");
    if (!sceneId) throw new ProtocolError("light_change_invalid");
    return {
      type: message.type,
      requestId: requestId(message.requestId),
      operation,
      sceneId,
    };
  }
  const operations = resource === "wall"
    ? ["create", "update", "delete", "interact"]
    : ["create", "update", "delete"];
  if (!operations.includes(operation)) {
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
  if (["update", "interact"].includes(operation) && Object.keys(changes).length === 0) {
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
