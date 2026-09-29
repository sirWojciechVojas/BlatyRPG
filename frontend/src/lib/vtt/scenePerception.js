export const clamp01 = (value) =>
  Math.min(1, Math.max(0, Number.isFinite(Number(value)) ? Number(value) : 0));

export const pointInPolygon = (point, polygon = []) => {
  let inside = false;
  for (
    let index = 0, previous = polygon.length - 1;
    index < polygon.length;
    previous = index++
  ) {
    const a = polygon[index];
    const b = polygon[previous];
    const intersects =
      a.y > point.y !== b.y > point.y &&
      point.x <
        ((b.x - a.x) * (point.y - a.y)) / (b.y - a.y || Number.EPSILON) + a.x;
    if (intersects) inside = !inside;
  }
  return inside;
};

export const pointInRegion = (point, region) =>
  region?.enabled !== false &&
  (region?.polygons || []).some((polygon) => pointInPolygon(point, polygon));

export const transitionedDarkness = (scene, now = Date.now()) => {
  const transition = scene?.darknessTransition;
  const fallback = clamp01(
    scene?.darknessLevel ?? 1 - Number(scene?.globalLightLevel || 0),
  );
  if (!transition?.startedAt || Number(transition.duration) <= 0)
    return fallback;
  const start = Date.parse(transition.startedAt);
  if (!Number.isFinite(start)) return fallback;
  const progress = clamp01((now - start) / Number(transition.duration));
  return clamp01(
    Number(transition.from) +
      (Number(transition.to) - Number(transition.from)) * progress,
  );
};

export const effectiveDarknessAt = (
  scene,
  regions = [],
  point = null,
  now = Date.now(),
) => {
  let darkness = transitionedDarkness(scene, now);
  if (!point) return darkness;
  for (const region of regions) {
    if (!pointInRegion(point, region)) continue;
    const value = clamp01(region.darknessValue);
    if (region.darknessMode === "add") darkness += value;
    else if (region.darknessMode === "subtract") darkness -= value;
    else darkness = value;
    darkness = clamp01(darkness);
  }
  return darkness;
};

export const globalIlluminationAt = (
  scene,
  regions = [],
  point = null,
  now = Date.now(),
) => {
  const enabled =
    scene?.globalIllumination === true ||
    (scene?.globalIllumination === undefined &&
      Number(scene?.globalLightLevel) > 0);
  if (!enabled) return false;
  if (
    point &&
    regions.some(
      (region) =>
        region.disableGlobalIllumination === true &&
        pointInRegion(point, region),
    )
  )
    return false;
  return (
    effectiveDarknessAt(scene, regions, point, now) <=
    clamp01(scene?.globalIlluminationThreshold ?? 1)
  );
};

export const applyWindowTransmission = (
  distanceToWindow,
  threshold,
  remainingRange,
) => {
  const limit = Math.max(0.001, Number(threshold) || 10);
  const distance = Math.max(0, Number(distanceToWindow) || 0);
  const proximity = 1 / (1 + distance / limit);
  return Math.max(0, Number(remainingRange) || 0) * proximity;
};
