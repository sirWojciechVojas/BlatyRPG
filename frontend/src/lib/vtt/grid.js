export const GRID_TYPES = Object.freeze({
  GRIDLESS: "gridless",
  SQUARE: "square",
  HEX_POINTY: "hex_pointy",
  HEX_FLAT: "hex_flat",
});

const SUPPORTED_TYPES = new Set(Object.values(GRID_TYPES));
const SQRT_THREE = Math.sqrt(3);
const rounded = (value) => Number(value.toFixed(3));
const point = ([x, y]) => `${rounded(x)} ${rounded(y)}`;

export const clamp = (value, minimum, maximum) =>
  Math.min(maximum, Math.max(minimum, Number(value)));

export const normalizeGridSettings = (scene = {}) => {
  const type = SUPPORTED_TYPES.has(scene.gridType)
    ? scene.gridType
    : GRID_TYPES.SQUARE;
  return {
    type,
    size: clamp(scene.gridSize || 100, 8, 512),
    offsetX: Number(scene.gridOffsetX || 0),
    offsetY: Number(scene.gridOffsetY || 0),
    color: String(scene.gridColor || "#000000"),
    opacity: clamp(scene.gridOpacity ?? 0.35, 0, 1),
  };
};

const hexPath = (centers, radius, startAngle) =>
  centers
    .map(([centerX, centerY]) => {
      const vertices = Array.from({ length: 6 }, (_, index) => {
        const angle = startAngle + (Math.PI / 3) * index;
        return [
          centerX + radius * Math.cos(angle),
          centerY + radius * Math.sin(angle),
        ];
      });
      return `M ${point(vertices[0])} ${vertices
        .slice(1)
        .map((vertex) => `L ${point(vertex)}`)
        .join(" ")} Z`;
    })
    .join(" ");

const pointyPattern = (size) => {
  const radius = size / SQRT_THREE;
  const height = 3 * radius;
  const centers = [
    [0, 0],
    [size, 0],
    [size * 2, 0],
    [size / 2, height / 2],
    [size * 1.5, height / 2],
    [0, height],
    [size, height],
    [size * 2, height],
  ];
  return {
    width: size * 2,
    height,
    path: hexPath(centers, radius, -Math.PI / 2),
  };
};

const flatPattern = (size) => {
  const radius = size / SQRT_THREE;
  const width = 3 * radius;
  const centers = [
    [0, 0],
    [0, size],
    [0, size * 2],
    [width / 2, size / 2],
    [width / 2, size * 1.5],
    [width, 0],
    [width, size],
    [width, size * 2],
  ];
  return {
    width,
    height: size * 2,
    path: hexPath(centers, radius, 0),
  };
};

export const buildGridPattern = (scene = {}) => {
  const settings = normalizeGridSettings(scene);
  if (settings.type === GRID_TYPES.GRIDLESS) return null;

  let geometry;
  if (settings.type === GRID_TYPES.SQUARE) {
    geometry = {
      width: settings.size,
      height: settings.size,
      path: `M ${settings.size} 0 H 0 V ${settings.size}`,
    };
  } else if (settings.type === GRID_TYPES.HEX_POINTY) {
    geometry = pointyPattern(settings.size);
  } else {
    geometry = flatPattern(settings.size);
  }
  return { ...settings, ...geometry };
};
