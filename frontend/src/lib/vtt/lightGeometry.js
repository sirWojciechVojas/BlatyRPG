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
  wall?.blocksLight === true &&
  !(wall.type !== "wall" && wall.doorState === "open");

const segmentsFor = (walls, scene) => [
  ...sceneEdges(scene),
  ...walls.filter(wallBlocksLight),
];

const endpointAngles = (origin, segment) =>
  [
    Math.atan2(segment.y1 - origin.y, segment.x1 - origin.x),
    Math.atan2(segment.y2 - origin.y, segment.x2 - origin.x),
  ].flatMap((angle) => [angle - EPSILON, angle, angle + EPSILON]);

const rayDistance = (origin, angle, radius, segments) => {
  const direction = { x: Math.cos(angle), y: Math.sin(angle) };
  let nearest = radius;
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

export const lightPolygonPoints = (light, walls = [], scene = {}) => {
  const origin = { x: Number(light?.x) || 0, y: Number(light?.y) || 0 };
  const radius = Math.max(0, Number(light?.dimRadius) || 0);
  const segments = segmentsFor(walls, scene);
  const angles = Array.from(
    { length: BASE_RAYS },
    (_, index) => (index / BASE_RAYS) * TAU - Math.PI,
  );
  for (const segment of segments) {
    angles.push(...endpointAngles(origin, segment));
  }
  return angles
    .sort((left, right) => left - right)
    .map((angle) => {
      const distance = rayDistance(origin, angle, radius, segments);
      return {
        x: rounded(origin.x + Math.cos(angle) * distance),
        y: rounded(origin.y + Math.sin(angle) * distance),
      };
    });
};

export const lightPolygonPath = (light, walls, scene) => {
  const points = lightPolygonPoints(light, walls, scene);
  if (!points.length) return "";
  return `${points
    .map((point, index) => `${index ? "L" : "M"} ${point.x} ${point.y}`)
    .join(" ")} Z`;
};
