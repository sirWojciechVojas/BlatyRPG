import { reconnectDelay } from "./reconnectPolicy";

export const createReconnectBudget = (options = {}, timers = {}) => {
  const maxAttempts = Math.max(0, Number(options.maxAttempts ?? 6));
  const stableMs = Math.max(
    1_000,
    Number(options.stableConnectionMs) || 30_000,
  );
  let attempt = 0;
  let stableTimer = null;

  const cancelStabilityWindow = () => {
    if (stableTimer !== null) timers.clear?.(stableTimer);
    stableTimer = null;
  };

  const reset = () => {
    cancelStabilityWindow();
    attempt = 0;
  };

  const markReady = () => {
    cancelStabilityWindow();
    stableTimer = timers.set?.(() => {
      stableTimer = null;
      attempt = 0;
    }, stableMs);
  };

  const consume = () => {
    cancelStabilityWindow();
    if (attempt >= maxAttempts) return null;
    const current = attempt;
    attempt += 1;
    return { attempt: current, delay: reconnectDelay(current, options) };
  };

  return {
    cancelStabilityWindow,
    consume,
    markReady,
    reset,
    value: () => attempt,
  };
};
