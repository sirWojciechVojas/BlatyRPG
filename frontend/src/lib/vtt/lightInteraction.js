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
