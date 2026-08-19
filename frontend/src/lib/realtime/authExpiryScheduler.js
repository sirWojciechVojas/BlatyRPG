const expiryTime = (value) => {
  const numeric = Number(value);
  if (Number.isFinite(numeric) && numeric > 0) {
    return numeric < 1e12 ? numeric * 1000 : numeric;
  }
  const parsed = Date.parse(String(value || ""));
  return Number.isFinite(parsed) ? parsed : null;
};

export const createAuthExpiryScheduler = (options = {}) => {
  const setTimer = options.setTimeout || setTimeout;
  const clearTimer = options.clearTimeout || clearTimeout;
  const now = options.now || Date.now;
  const leadMs = Math.max(1_000, Number(options.authRefreshLeadMs) || 5_000);
  let timer = null;

  const cancel = () => {
    if (timer !== null) clearTimer?.(timer);
    timer = null;
  };

  const schedule = (expiresAt) => {
    cancel();
    const expiry = expiryTime(expiresAt);
    if (!expiry || !setTimer) return false;
    const remaining = expiry - now();
    if (remaining <= leadMs) return false;
    timer = setTimer(() => {
      timer = null;
      options.onRefresh?.();
    }, remaining - leadMs);
    return true;
  };

  return { cancel, schedule };
};
