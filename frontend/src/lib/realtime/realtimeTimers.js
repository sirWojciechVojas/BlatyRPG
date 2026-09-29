/* global globalThis */

const callableTimer = (timer) =>
  typeof timer === "function"
    ? (...args) => Reflect.apply(timer, globalThis, args)
    : null;

export const createRealtimeTimers = (options = {}) => ({
  set: callableTimer(options.setTimeout || globalThis.setTimeout),
  clear: callableTimer(options.clearTimeout || globalThis.clearTimeout),
});
