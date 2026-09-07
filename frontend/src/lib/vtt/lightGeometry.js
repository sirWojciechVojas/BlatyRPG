import { effectiveLight } from "./lightPhotometry";

const TAU = Math.PI * 2;
const EPSILON = 0.00001;
const BASE_RAYS = 96;

const cross = (left, right) => left.x * right.y - left.y * right.x;

const sceneEdges = (scene) => {
  const width = Math.max(0, Number(scene?.width) || 0);
  const height = Math.max(0, Number(scene?.height) || 0);
  return [
    { x1: 0, y1: 0, x2: width, y2: 0 },
    { x1: width, y1: 0, x2: width, y2: height },
    { x1: width, y1: height, x2: 0, y2: height },
    { x1: 0, y1: height, x2: 0, y2: 0 },
  ];
};

export const wallBlocksLight = (wall) =>
  wall?.enabled !== false &&
  wall?.blocksLight === true &&
  !(wall.type !== "wall" && wall.doorState === "open");

export const wallBlocksSight = (wall) =>
  wall?.enabled !== false &&
  wall?.blocksSight === true &&
  !(wall.type !== "wall" && wall.doorState === "open");

const wallAtElevation = (wall, elevation) => {
  if (wall?.bottomElevation === undefined && wall?.topElevation === undefined)
    return true;
  const bottom = Number(wall.bottomElevation ?? -Infinity);
  const top = Number(wall.topElevation ?? Infinity);
  return (
    elevation >= Math.min(bottom, top) && elevation <= Math.max(bottom, top)
  );
};

const segmentsFor = (light, walls, scene, restriction = "light") => [
  ...sceneEdges(scene),
  ...(light?.constrainedByWalls === false
    ? []
    : walls.filter(
        (wall) =>
          (restriction === "sight"
            ? wallBlocksSight(wall)
            : wallBlocksLight(wall)) &&
          wallAtElevation(wall, Number(light?.elevation) || 0),
      )),
];

const endpointAngles = (origin, segment) =>
  [
    Math.atan2(segment.y1 - origin.y, segment.x1 - origin.x),
    Math.atan2(segment.y2 - origin.y, segment.x2 - origin.x),
  ].flatMap((angle) => [angle - EPSILON, angle, angle + EPSILON]);

const rayDistance = (origin, angle, maximum, segments) => {
  const direction = { x: Math.cos(angle), y: Math.sin(angle) };
  let nearest = maximum;
  for (const segment of segments) {
    const start = { x: Number(segment.x1), y: Number(segment.y1) };
    const edge = {
      x: Number(segment.x2) - start.x,
      y: Number(segment.y2) - start.y,
    };
    const denominator = cross(direction, edge);
    if (Math.abs(denominator) < EPSILON) continue;
    const delta = { x: start.x - origin.x, y: start.y - origin.y };
    const distance = cross(delta, edge) / denominator;
    const position = cross(delta, direction) / denominator;
    if (
      distance > EPSILON &&
      distance < nearest &&
      position >= -EPSILON &&
      position <= 1 + EPSILON
    ) {
      nearest = distance;
    }
  }
  return nearest;
};

const rounded = (value) => Math.round(value * 1000) / 1000;

const directional = (light) =>
  ["directional", "cone"].includes(light?.sourceType) &&
  Number(light?.angle ?? 90) < 360;

const angleDelta = (left, right) =>
  Math.atan2(Math.sin(left - right), Math.cos(left - right));

const withinSector = (angle, light) => {
  if (!directional(light)) return true;
  const center = ((Number(light.direction) || 0) * Math.PI) / 180;
  const half = ((Number(light.angle) || 90) * Math.PI) / 360;
  return Math.abs(angleDelta(angle, center)) <= half + EPSILON;
};

const alignedAngle = (angle, light) => {
  if (!directional(light)) return angle;
  const center = ((Number(light.direction) || 0) * Math.PI) / 180;
  return center + angleDelta(angle, center);
};

const baseAngles = (light) => {
  if (!directional(light)) {
    return Array.from(
      { length: BASE_RAYS },
      (_, index) => (index / BASE_RAYS) * TAU - Math.PI,
    );
  }
  const center = ((Number(light.direction) || 0) * Math.PI) / 180;
  const span = (Math.max(1, Number(light.angle) || 90) * Math.PI) / 180;
  const count = Math.max(3, Math.ceil((BASE_RAYS * span) / TAU));
  return Array.from(
    { length: count + 1 },
    (_, index) => center - span / 2 + (span * index) / count,
  );
};

const shapeDistance = (light, angle) => {
  if (light.sourceType !== "area") return Math.max(0, light.dimRadius);
  const horizontal = Math.abs(Math.cos(angle));
  const vertical = Math.abs(Math.sin(angle));
  return Math.min(
    horizontal < EPSILON ? Infinity : light.areaWidth / 2 / horizontal,
    vertical < EPSILON ? Infinity : light.areaHeight / 2 / vertical,
  );
};

export const lightPolygonPoints = (
  light,
  walls = [],
  scene = {},
  restriction = "light",
) => {
  const geometry = effectiveLight(light);
  const origin = { x: Number(geometry.x) || 0, y: Number(geometry.y) || 0 };
  const segments = segmentsFor(geometry, walls, scene, restriction);
  const angles = baseAngles(geometry);
  for (const segment of segments) {
    angles.push(
      ...endpointAngles(origin, segment)
        .filter((angle) => withinSector(angle, geometry))
        .map((angle) => alignedAngle(angle, geometry)),
    );
  }
  const points = angles
    .sort((left, right) => left - right)
    .map((angle) => {
      const distance = rayDistance(
        origin,
        angle,
        shapeDistance(geometry, angle),
        segments,
      );
      return {
        x: rounded(origin.x + Math.cos(angle) * distance),
        y: rounded(origin.y + Math.sin(angle) * distance),
      };
    });
  return directional(geometry) ? [origin, ...points] : points;
};

export const lightIsActive = (light, darkness) => {
  const level = Math.min(1, Math.max(0, Number(darkness) || 0));
  return (
    light?.enabled === true &&
    level >= Number(light.darknessMin ?? 0) &&
    level <= Number(light.darknessMax ?? 1)
  );
};

export const lightTransitionOffsets = (light) => {
  const bright = Math.min(
    100,
    (Math.max(0, Number(light?.brightRadius) || 0) /
      Math.max(1, Number(light?.dimRadius) || 0)) *
      100,
  );
  const softness =
    light?.gradualIllumination === false ? 0 : Number(light?.softness ?? 0.5);
  return {
    bright,
    fade: bright + (100 - bright) * (1 - Math.min(1, Math.max(0, softness))),
  };
};

export const tokenVisionSource = (token, scene = {}) => {
  const vision = token?.vision || {};
  if (vision.enabled !== true || token?.capabilities?.canControl !== true)
    return null;
  const radius = Number(
    vision.dimRadius ??
      vision.radius ??
      vision.range ??
      (Number(scene.gridSize) || 100) * 6,
  );
  return {
    id: `token-${token.id}`,
    x: Number(token.x) + Number(token.width || scene.gridSize || 100) / 2,
    y: Number(token.y) + Number(token.height || scene.gridSize || 100) / 2,
    dimRadius: Math.max(0, radius),
    sourceType: Number(vision.angle ?? 360) < 360 ? "cone" : "omni",
    angle: Math.min(360, Math.max(1, Number(vision.angle) || 360)),
    direction: Number.isFinite(Number(token.facing))
      ? Number(token.facing)
      : Number(vision.direction ?? token.rotation) || 0,
    constrainedByWalls: vision.constrainedByWalls !== false,
    elevation: Number(token.elevation) || 0,
  };
};

export const lightPolygonPath = (
  light,
  walls,
  scene,
  restriction = "light",
) => {
  const points = lightPolygonPoints(light, walls, scene, restriction);
  if (!points.length) return "";
  return `${points
    .map((point, index) => `${index ? "L" : "M"} ${point.x} ${point.y}`)
    .join(" ")} Z`;
};

export const lightTechnicalPath = (light, walls, scene, bright = false) => {
  if (!bright) return lightPolygonPath(light, walls, scene);
  const ratio = Math.min(
    1,
    Math.max(0, Number(light?.brightRadius) || 0) /
      Math.max(1, Number(light?.dimRadius) || 0),
  );
  return lightPolygonPath(
    {
      ...light,
      dimRadius: Number(light?.brightRadius) || 0,
      areaWidth: (Number(light?.areaWidth) || 400) * ratio,
      areaHeight: (Number(light?.areaHeight) || 400) * ratio,
    },
    walls,
    scene,
  );
};
