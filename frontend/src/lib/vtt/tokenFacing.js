export const normalizeTokenAngle = (value, fallback = 0) => {
  const number = Number(value);
  const base = Number.isFinite(number) ? number : Number(fallback) || 0;
  const angle = base % 360;
  return angle < 0 ? angle + 360 : angle;
};

export const rotateTokenFacing = (token, delta) => ({
  rotation: normalizeTokenAngle(
    Number(token?.rotation) + Number(delta),
    token?.rotation,
  ),
  facing: normalizeTokenAngle(
    Number(token?.facing ?? token?.rotation) + Number(delta),
    token?.facing ?? token?.rotation,
  ),
});

export const tokenFacingStyle = (token) => ({
  transform: `rotate(${normalizeTokenAngle(
    token?.facing,
    token?.rotation,
  )}deg)`,
});
