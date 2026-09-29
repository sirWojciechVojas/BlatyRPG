const clamp01 = (value) =>
  Math.min(1, Math.max(0, Number.isFinite(Number(value)) ? Number(value) : 0));

export const lightTintWeight = (light = {}, sceneDarkness = 1) => {
  const clarity = clamp01(light.clarity);
  const darknessWeight = 0.2 + clamp01(sceneDarkness) * 0.8;
  return (0.04 + clarity * 0.14) * darknessWeight;
};
