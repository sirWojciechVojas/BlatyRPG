import {
  lightIsActive,
  lightPolygonPoints,
  tokenVisionSource,
} from "./lightGeometry";
import { markCircle, markPolygon } from "./fogGrid";
import { effectiveDarknessAt, globalIlluminationAt } from "./scenePerception";

const INDEX_BUCKET_SIZE = 512;
const CACHE_LIMIT = 8;
const wallIndexCache = new Map();
const illuminationCache = new Map();

const boundsOverlap = (bounds, wall) =>
  Math.max(Number(wall.x1), Number(wall.x2)) >= bounds.left &&
  Math.min(Number(wall.x1), Number(wall.x2)) <= bounds.right &&
  Math.max(Number(wall.y1), Number(wall.y2)) >= bounds.top &&
  Math.min(Number(wall.y1), Number(wall.y2)) <= bounds.bottom;

const remember = (cache, key, value) => {
  cache.set(key, value);
  if (cache.size > CACHE_LIMIT) cache.delete(cache.keys().next().value);
  return value;
};

const wallSignature = (walls) =>
  walls
    .map((wall) =>
      [
        wall.id,
        wall.revision,
        wall.x1,
        wall.y1,
        wall.x2,
        wall.y2,
        wall.type,
        wall.doorState,
        wall.enabled,
        wall.blocksSight,
        wall.blocksLight,
        wall.bottomElevation,
        wall.topElevation,
      ].join(":"),
    )
    .join("|");

export const createWallSpatialIndex = (walls = []) => {
  const signature = wallSignature(walls);
  if (wallIndexCache.has(signature)) return wallIndexCache.get(signature);
  const buckets = new Map();
  walls.forEach((wall) => {
    const left = Math.floor(
      Math.min(Number(wall.x1), Number(wall.x2)) / INDEX_BUCKET_SIZE,
    );
    const right = Math.floor(
      Math.max(Number(wall.x1), Number(wall.x2)) / INDEX_BUCKET_SIZE,
    );
    const top = Math.floor(
      Math.min(Number(wall.y1), Number(wall.y2)) / INDEX_BUCKET_SIZE,
    );
    const bottom = Math.floor(
      Math.max(Number(wall.y1), Number(wall.y2)) / INDEX_BUCKET_SIZE,
    );
    for (let row = top; row <= bottom; row += 1) {
      for (let column = left; column <= right; column += 1) {
        const key = `${column}:${row}`;
        if (!buckets.has(key)) buckets.set(key, []);
        buckets.get(key).push(wall);
      }
    }
  });
  return remember(wallIndexCache, signature, { signature, buckets });
};

export const nearbyWalls = (source, index) => {
  const radius = Math.max(
    0,
    Number(source.dimRadius ?? source.range) || 0,
    Number(source.areaWidth) / 2 || 0,
    Number(source.areaHeight) / 2 || 0,
  );
  const bounds = {
    left: Number(source.x) - radius,
    top: Number(source.y) - radius,
    right: Number(source.x) + radius,
    bottom: Number(source.y) + radius,
  };
  const candidates = new Set();
  for (
    let row = Math.floor(bounds.top / INDEX_BUCKET_SIZE);
    row <= Math.floor(bounds.bottom / INDEX_BUCKET_SIZE);
    row += 1
  ) {
    for (
      let column = Math.floor(bounds.left / INDEX_BUCKET_SIZE);
      column <= Math.floor(bounds.right / INDEX_BUCKET_SIZE);
      column += 1
    ) {
      (index.buckets.get(`${column}:${row}`) || []).forEach((wall) =>
        candidates.add(wall),
      );
    }
  }
  return [...candidates].filter((wall) => boundsOverlap(bounds, wall));
};

const polygonFor = (source, wallIndex, scene, restriction) =>
  lightPolygonPoints(
    source,
    nearbyWalls(source, wallIndex),
    scene,
    restriction,
  );

const sourceOrigin = (token, scene) => ({
  x: Number(token.x) + Number(token.width || scene.gridSize || 100) / 2,
  y: Number(token.y) + Number(token.height || scene.gridSize || 100) / 2,
});

const lightSignature = (lights) =>
  lights
    .map((light) =>
      [
        light.id,
        light.revision,
        light.x,
        light.y,
        light.dimRadius,
        light.brightRadius,
        light.sourceType,
        light.direction,
        light.angle,
        light.areaWidth,
        light.areaHeight,
        light.enabled,
        light.hidden,
        light.constrainedByWalls,
        light.darknessMin,
        light.darknessMax,
        light.elevation,
      ].join(":"),
    )
    .join("|");

const regionSignature = (regions) =>
  regions
    .map((region) =>
      [
        region.id,
        region.revision,
        region.enabled,
        region.darknessMode,
        region.darknessValue,
        region.disableGlobalIllumination,
      ].join(":"),
    )
    .join("|");

const illuminationFor = (scene, lights, regions, wallIndex, grid) => {
  const key = [
    scene.width,
    scene.height,
    scene.globalLightLevel,
    grid.cellSize,
    grid.columns,
    grid.rows,
    wallIndex.signature,
    lightSignature(lights),
    regionSignature(regions),
    scene.globalIllumination,
    scene.globalIlluminationThreshold,
    scene.darknessTransition?.startedAt,
  ].join("#");
  if (illuminationCache.has(key)) return illuminationCache.get(key);
  const illuminated = new Uint8Array(grid.length);
  const activeLights = lights.filter((light) =>
    lightIsActive(
      light,
      effectiveDarknessAt(scene, regions, { x: light.x, y: light.y }),
    ),
  );
  const global = globalIlluminationAt(scene, regions);
  const lightPolygons = activeLights
    .filter((light) => light.sourceType !== "darkness")
    .map((light) => polygonFor(light, wallIndex, scene, "light"));
  const darknessPolygons = activeLights
    .filter((light) => light.sourceType === "darkness")
    .map((light) => polygonFor(light, wallIndex, scene, "light"));
  if (global && !regions.length) illuminated.fill(1);
  else if (scene.globalIllumination === true) {
    for (let index = 0; index < illuminated.length; index += 1) {
      const point = {
        x: ((index % grid.columns) + 0.5) * grid.cellSize,
        y: (Math.floor(index / grid.columns) + 0.5) * grid.cellSize,
      };
      if (globalIlluminationAt(scene, regions, point)) illuminated[index] = 1;
    }
  }
  lightPolygons.forEach((polygon) => markPolygon(illuminated, grid, polygon));
  const darkMask = new Uint8Array(grid.length);
  darknessPolygons.forEach((polygon) => markPolygon(darkMask, grid, polygon));
  for (let index = 0; index < illuminated.length; index += 1) {
    if (darkMask[index]) illuminated[index] = 0;
  }
  return remember(illuminationCache, key, {
    mask: illuminated,
    geometry: { global, lightPolygons, darknessPolygons },
  });
};

export const computeFogVisibility = ({
  scene,
  tokens = [],
  walls = [],
  lights = [],
  regions = [],
  grid,
}) => {
  const visible = new Uint8Array(grid.length);
  const wallIndex = createWallSpatialIndex(walls);
  const illumination = illuminationFor(scene, lights, regions, wallIndex, grid);
  const illuminated = illumination.mask;

  const sources = [];
  tokens.forEach((token) => {
    const source = tokenVisionSource(token, scene);
    if (!source) return;
    const sourceMask = new Uint8Array(grid.length);
    const polygon = polygonFor(source, wallIndex, scene, "sight");
    markPolygon(sourceMask, grid, polygon);
    const vision = token.vision || {};
    const mode = String(
      vision.mode || (vision.darkvision === true ? "darkvision" : "basic"),
    );
    for (let index = 0; index < sourceMask.length; index += 1) {
      if (
        sourceMask[index] &&
        (vision.limitByLight === false ||
          [
            "darkvision",
            "light_amplification",
            "monochromatic",
            "tremorsense",
          ].includes(mode) ||
          illuminated[index])
      )
        visible[index] = 1;
    }
    const origin = sourceOrigin(token, scene);
    let minimumPolygon = null;
    if (Number(vision.minimumRadius) > 0) {
      const minimum = new Uint8Array(grid.length);
      markCircle(minimum, grid, origin, Number(vision.minimumRadius));
      for (let index = 0; index < minimum.length; index += 1) {
        if (minimum[index] && sourceMask[index]) visible[index] = 1;
      }
      minimumPolygon = polygonFor(
        {
          ...source,
          dimRadius: Math.min(source.dimRadius, Number(vision.minimumRadius)),
        },
        wallIndex,
        scene,
        "sight",
      );
    }
    let darkvisionPolygon = null;
    if (
      (mode === "darkvision" || vision.darkvision === true) &&
      Number(vision.darkvisionRange) > 0
    ) {
      const darkSource = {
        ...source,
        dimRadius: Math.min(source.dimRadius, Number(vision.darkvisionRange)),
      };
      darkvisionPolygon = polygonFor(darkSource, wallIndex, scene, "sight");
      markPolygon(visible, grid, darkvisionPolygon);
    }
    sources.push({
      tokenId: token.id,
      polygon,
      limitedByLight: vision.limitByLight !== false,
      mode,
      minimumPolygon,
      darkvisionPolygon,
    });
  });
  return {
    visible,
    illuminated,
    sources,
    illuminationGeometry: illumination.geometry,
    rasterized: regions.length > 0,
  };
};
