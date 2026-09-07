const finite = (value, fallback = 0) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
};

const clamp = (value, minimum, maximum) =>
  Math.min(maximum, Math.max(minimum, finite(value, minimum)));

export const normalizeFogFeather = (value) => {
  const configured = finite(value, 32);
  return configured <= 0 ? 0 : clamp(configured, 20, 50);
};

export const fogViewportRect = ({
  scene = {},
  camera = {},
  viewport = {},
  padding = 0,
  feather = 32,
}) => {
  const sceneWidth = Math.max(1, finite(scene.width, 1));
  const sceneHeight = Math.max(1, finite(scene.height, 1));
  const zoom = Math.max(0.01, finite(camera.scale, 1));
  const viewportWidth = Math.max(0, finite(viewport.width));
  const viewportHeight = Math.max(0, finite(viewport.height));
  if (!viewportWidth || !viewportHeight) {
    return { x: 0, y: 0, width: sceneWidth, height: sceneHeight };
  }
  const overscan = (Math.max(0, feather) * 2 + 8) / zoom;
  const visibleLeft = -finite(camera.x) / zoom - Math.max(0, finite(padding));
  const visibleTop = -finite(camera.y) / zoom - Math.max(0, finite(padding));
  const left = clamp(Math.floor(visibleLeft - overscan), 0, sceneWidth);
  const top = clamp(Math.floor(visibleTop - overscan), 0, sceneHeight);
  const right = clamp(
    Math.ceil(visibleLeft + viewportWidth / zoom + overscan),
    0,
    sceneWidth,
  );
  const bottom = clamp(
    Math.ceil(visibleTop + viewportHeight / zoom + overscan),
    0,
    sceneHeight,
  );
  return {
    x: left,
    y: top,
    width: Math.max(0, right - left),
    height: Math.max(0, bottom - top),
  };
};

export const fogBackingMetrics = (
  rect,
  zoom = 1,
  devicePixelRatio = 1,
  maximumPixels = 12582912,
  maximumDimension = 6144,
) => {
  const width = Math.max(1, finite(rect?.width, 1));
  const height = Math.max(1, finite(rect?.height, 1));
  const desiredScale = Math.max(
    0.01,
    finite(zoom, 1) * clamp(devicePixelRatio, 1, 3),
  );
  const scale = Math.min(
    desiredScale,
    maximumDimension / width,
    maximumDimension / height,
    Math.sqrt(maximumPixels / (width * height)),
  );
  return {
    width: Math.max(1, Math.ceil(width * scale)),
    height: Math.max(1, Math.ceil(height * scale)),
    scale: Math.max(0.01, scale),
  };
};
