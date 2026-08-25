const coordinate = (value) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : 0;
};

export const tokenTravelDuration = (from = {}, to = {}) => {
  const distance = Math.hypot(
    coordinate(to.x) - coordinate(from.x),
    coordinate(to.y) - coordinate(from.y),
  );
  return Math.round(Math.min(720, Math.max(260, 220 + distance * 0.65)));
};

export const pendingTokenPositionResolved = (pending = {}, token = null) => {
  if (!token) return true;
  const arrived =
    coordinate(token.x) === coordinate(pending.x) &&
    coordinate(token.y) === coordinate(pending.y);
  const pendingRevision = Number(pending.revision);
  const tokenRevision = Number(token.revision);
  const answered =
    Number.isFinite(pendingRevision) &&
    Number.isFinite(tokenRevision) &&
    tokenRevision !== pendingRevision;
  return arrived || answered;
};
