const DEFAULT_LUMENS = 800;

const clamp = (value, minimum, maximum) =>
  Math.min(maximum, Math.max(minimum, Number(value) || 0));

export const normalizedLumens = (light = {}) => {
  if (light.lumens !== undefined) return clamp(light.lumens, 0, 1000000);
  return clamp(Number(light.intensity ?? 1) * DEFAULT_LUMENS, 0, 1000000);
};

export const lumenRangeScale = (light = {}) =>
  clamp(Math.sqrt(normalizedLumens(light) / DEFAULT_LUMENS), 0, 8);

export const lumenStrength = (light = {}) =>
  clamp(Math.sqrt(normalizedLumens(light) / DEFAULT_LUMENS), 0, 1);

export const effectiveLight = (light = {}) => {
  const scale = lumenRangeScale(light);
  return {
    ...light,
    brightRadius: Math.max(0, Number(light.brightRadius) || 0) * scale,
    dimRadius: Math.max(0, Number(light.dimRadius) || 0) * scale,
    areaWidth: Math.max(1, Number(light.areaWidth) || 400) * scale,
    areaHeight: Math.max(1, Number(light.areaHeight) || 400) * scale,
  };
};
