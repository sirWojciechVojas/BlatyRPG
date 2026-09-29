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

export const tokenFacingDelta = (current, next) => {
  const delta = normalizeTokenAngle(next) - normalizeTokenAngle(current);
  if (delta > 180) return delta - 360;
  if (delta < -180) return delta + 360;
  return delta;
};

export const tokenFacingChanges = (token, nextFacing) => {
  const currentFacing = normalizeTokenAngle(token?.facing, token?.rotation);
  const facing = normalizeTokenAngle(nextFacing, currentFacing);
  if (token?.rotationFollowsFacing !== true) return { facing };

  return {
    facing,
    rotation: normalizeTokenAngle(
      Number(token?.rotation) + tokenFacingDelta(currentFacing, facing),
      token?.rotation,
    ),
  };
};

export const tokenFacingWheelChanges = (token, deltaY, step = 15) => {
  const direction = Math.sign(Number(deltaY));
  if (!direction) return null;
  const currentFacing = normalizeTokenAngle(token?.facing, token?.rotation);
  return tokenFacingChanges(token, currentFacing + direction * Number(step));
};

export const tokenFacingStyle = (token) => ({
  transform: `rotate(${normalizeTokenAngle(
    token?.facing,
    token?.rotation,
  )}deg)`,
});

export const tokenFacingToGeometryDirection = (value, fallback = 0) =>
  normalizeTokenAngle(normalizeTokenAngle(value, fallback) - 90);
