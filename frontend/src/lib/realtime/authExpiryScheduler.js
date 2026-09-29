import { createRealtimeTimers } from "./realtimeTimers";

const expiryTime = (value) => {
  const numeric = Number(value);
  if (Number.isFinite(numeric) && numeric > 0) {
    return numeric < 1e12 ? numeric * 1000 : numeric;
  }
  const parsed = Date.parse(String(value || ""));
  return Number.isFinite(parsed) ? parsed : null;
};

export const createAuthExpiryScheduler = (options = {}) => {
  const timers = createRealtimeTimers(options);
  const now = options.now || Date.now;
  const leadMs = Math.max(1_000, Number(options.authRefreshLeadMs) || 5_000);
  let timer = null;

  const cancel = () => {
    if (timer !== null) timers.clear?.(timer);
    timer = null;
  };

  const schedule = (expiresAt) => {
    cancel();
    const expiry = expiryTime(expiresAt);
    if (!expiry || !timers.set) return false;
    const remaining = expiry - now();
    if (remaining <= leadMs) return false;
    timer = timers.set(() => {
      timer = null;
      options.onRefresh?.();
    }, remaining - leadMs);
    return true;
  };

  return { cancel, schedule };
};
