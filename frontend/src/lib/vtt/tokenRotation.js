import { normalizeTokenAngle } from "./tokenFacing";

const finite = (value) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : 0;
};

export const tokenPointerAngle = (center, pointer) => {
  const radians = Math.atan2(
    finite(pointer?.clientY) - finite(center?.y),
    finite(pointer?.clientX) - finite(center?.x),
  );
  return normalizeTokenAngle(Math.round((radians * 1800) / Math.PI) / 10 + 90);
};

export const tokenAnglePreview = (token, preview = {}) => ({
  ...token,
  rotation: normalizeTokenAngle(preview.rotation, token?.rotation),
  facing: normalizeTokenAngle(preview.facing, token?.facing ?? token?.rotation),
});
