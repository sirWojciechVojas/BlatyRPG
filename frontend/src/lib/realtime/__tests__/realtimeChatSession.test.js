import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { event, FakeWebSocket, flush, setup } from "./realtimeTestSupport";

describe("realtime chat transport", () => {
  beforeEach(() => {
    vi.useFakeTimers();
    FakeWebSocket.instances = [];
  });

  afterEach(() => vi.useRealTimers());

  it("sends chat commands only through an authenticated socket", async () => {
    const { session } = setup();
    session.connect(7);
    await flush();
    expect(
      session.sendChat({
        requestId: "550e8400-e29b-41d4-a716-446655440000",
        clientNonce: "550e8400-e29b-41d4-a716-446655440000",
        body: "Ready",
      }),
    ).toBe(false);

    const socket = FakeWebSocket.instances[0];
    socket.open();
    socket.message(event("session.ready"));
    expect(
      session.sendChat({
        requestId: "550e8400-e29b-41d4-a716-446655440000",
        clientNonce: "550e8400-e29b-41d4-a716-446655440000",
        body: "Ready",
      }),
    ).toBe(true);
    expect(socket.sent.at(-1)).toEqual({
      v: 1,
      type: "chat.send",
      requestId: "550e8400-e29b-41d4-a716-446655440000",
      clientNonce: "550e8400-e29b-41d4-a716-446655440000",
      body: "Ready",
    });

    session.syncChat({ requestId: "sync-chat-1", afterRevision: 12 });
    expect(socket.sent.at(-1)).toEqual({
      v: 1,
      type: "chat.sync",
      requestId: "sync-chat-1",
      afterRevision: 12,
      limit: 20,
    });
  });

  it("accepts an authoritative zero after a realtime server restart", async () => {
    let value = 9;
    const sequence = {
      get: () => value,
      select: () => value,
      set: (next) => {
        value = next;
        return value;
      },
    };
    const { session } = setup({ sequence });
    session.connect(7);
    await flush();
    const socket = FakeWebSocket.instances[0];
    socket.open();
    socket.message(event("session.ready", 0));
    socket.message(
      event(
        "sync.snapshot",
        0,
        {
          latestSequence: 0,
          users: [],
        },
        "restart-snapshot",
      ),
    );

    expect(value).toBe(0);
  });
});
