import { ProtocolError } from "./protocol-error.js";
import { exactKeys, number, plainObject } from "./scene-element-validation.js";

const booleans = [
  "enabled",
  "hidden",
  "gradualIllumination",
  "providesVision",
  "constrainedByWalls",
];

const ranges = {
  lumens: [0, 1000000],
  direction: [0, 360],
  angle: [1, 360],
  areaWidth: [1, 100000],
  areaHeight: [1, 100000],
  brightRadius: [0, 100000],
  dimRadius: [0, 100000],
  intensity: [0, 1],
  opacity: [0, 1],
  softness: [0, 1],
  clarity: [0, 1],
  darknessMin: [0, 1],
  darknessMax: [0, 1],
  animationSpeed: [0.1, 10],
  animationIntensity: [0, 1],
};

const enumeration = (value, field, allowed, changes) => {
  if (value[field] === undefined) return;
  if (!allowed.includes(value[field])) {
    throw new ProtocolError(`light_${field}_invalid`);
  }
  changes[field] = value[field];
};

export const lightChanges = (value, operation) => {
  if (!plainObject(value)) throw new ProtocolError("light_changes_invalid");
  const allowed = [
    "x",
    "y",
    "color",
    "name",
    "sourceType",
    "animation",
    "elevation",
    ...Object.keys(ranges),
    ...booleans,
  ];
  exactKeys(value, allowed);
  const changes = {};
  for (const field of ["x", "y", "elevation"]) {
    if (value[field] !== undefined) {
      changes[field] = number(value[field], `light_${field}_invalid`);
    }
  }
  for (const [field, [minimum, maximum]] of Object.entries(ranges)) {
    if (value[field] !== undefined) {
      changes[field] = number(
        value[field],
        `light_${field}_invalid`,
        minimum,
        maximum,
      );
    }
  }
  for (const field of booleans) {
    if (value[field] === undefined) continue;
    if (typeof value[field] !== "boolean") {
      throw new ProtocolError(`light_${field}_invalid`);
    }
    changes[field] = value[field];
  }
  enumeration(
    value,
    "sourceType",
    ["light", "omni", "directional", "cone", "area", "darkness"],
    changes,
  );
  enumeration(value, "animation", ["none", "flicker", "pulse", "vortex"], changes);
  if (value.color !== undefined) {
    const color = String(value.color).toUpperCase();
    if (!/^#[0-9A-F]{6}(?:[0-9A-F]{2})?$/.test(color)) {
      throw new ProtocolError("light_color_invalid");
    }
    changes.color = color;
  }
  if (value.name !== undefined) {
    const name = String(value.name).trim();
    if (!name || name.length > 100) throw new ProtocolError("light_name_invalid");
    changes.name = name;
  }
  if (operation === "create" && !["x", "y"].every((key) => key in changes)) {
    throw new ProtocolError("light_geometry_required");
  }
  if (changes.brightRadius > changes.dimRadius) {
    throw new ProtocolError("light_radius_invalid");
  }
  if (changes.darknessMin > changes.darknessMax) {
    throw new ProtocolError("light_darkness_range_invalid");
  }
  return changes;
};
