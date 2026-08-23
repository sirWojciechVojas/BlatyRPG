/* global globalThis */
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { event, FakeWebSocket, flush, setup } from "./realtimeTestSupport";

describe("realtime timer receiver", () => {
  beforeEach(() => {
    vi.useFakeTimers();
    FakeWebSocket.instances = [];
  });

  afterEach(() => vi.useRealTimers());

  it("survives session.ready with browser-style timers", async () => {
    const setTimer = vi.fn(function (...args) {
      if (this !== globalThis) throw new TypeError("Illegal invocation");
      return globalThis.setTimeout(...args);
    });
    const clearTimer = vi.fn(function (...args) {
      if (this !== globalThis) throw new TypeError("Illegal invocation");
      return globalThis.clearTimeout(...args);
    });
    const { session } = setup({
      setTimeout: setTimer,
      clearTimeout: clearTimer,
    });

    session.connect(7);
    await flush();
    const socket = FakeWebSocket.instances[0];
    socket.open();

    expect(() => socket.message(event("session.ready"))).not.toThrow();
    session.disconnect();
    expect(setTimer).toHaveBeenCalled();
    expect(clearTimer).toHaveBeenCalled();
  });
});
