export const LIGHT_EDIT_FIELDS = [
  "name",
  "sourceType",
  "lumens",
  "direction",
  "angle",
  "areaWidth",
  "areaHeight",
  "brightRadius",
  "dimRadius",
  "color",
  "opacity",
  "softness",
  "clarity",
  "gradualIllumination",
  "darknessMin",
  "darknessMax",
  "providesVision",
  "constrainedByWalls",
  "animation",
  "animationSpeed",
  "animationIntensity",
  "elevation",
  "enabled",
  "hidden",
];

export const lightPropertiesSnapshot = (light) =>
  Object.fromEntries(LIGHT_EDIT_FIELDS.map((field) => [field, light[field]]));

export const changedLightProperties = (initial, current) =>
  Object.fromEntries(
    LIGHT_EDIT_FIELDS.filter((field) => current[field] !== initial[field]).map(
      (field) => [field, current[field]],
    ),
  );

export const normalizeLightProperties = (form) => ({
  ...form,
  brightRadius: Math.min(form.brightRadius, form.dimRadius),
  darknessMin: Math.min(form.darknessMin, form.darknessMax),
  lumens: Math.round(form.lumens),
});
