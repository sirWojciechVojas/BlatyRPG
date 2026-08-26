const finite = (value) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : 0;
};

export const lightDraftFromDrag = (origin, endpoint) => {
  const x = finite(origin?.x);
  const y = finite(origin?.y);
  const radius = Math.hypot(finite(endpoint?.x) - x, finite(endpoint?.y) - y);
  const dimRadius = Math.round(radius * 1000) / 1000;
  return {
    x,
    y,
    brightRadius: Math.round(dimRadius * 500) / 1000,
    dimRadius,
  };
};

export const validLightDraft = (draft) => finite(draft?.dimRadius) >= 2;

const COPY_FIELDS = [
  "brightRadius",
  "dimRadius",
  "color",
  "intensity",
  "opacity",
  "softness",
  "gradualIllumination",
  "darknessMin",
  "darknessMax",
  "sourceType",
  "providesVision",
  "constrainedByWalls",
  "animation",
  "animationSpeed",
  "animationIntensity",
  "elevation",
  "enabled",
  "hidden",
];

export const lightCopyDraft = (light, scene = {}) => {
  const offset = Math.max(1, finite(scene.gridSize) / 2 || 50);
  const draft = {
    x: Math.min(finite(scene.width) || Infinity, finite(light?.x) + offset),
    y: Math.min(finite(scene.height) || Infinity, finite(light?.y) + offset),
  };
  COPY_FIELDS.forEach((field) => {
    if (light?.[field] !== undefined) draft[field] = light[field];
  });
  return draft;
};
