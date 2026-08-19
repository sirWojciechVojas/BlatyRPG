import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { event, FakeWebSocket, flush, setup } from "./realtimeTestSupport";

describe("realtimeSession", () => {
  beforeEach(() => {
    vi.useFakeTimers();
    FakeWebSocket.instances = [];
  });

  afterEach(() => vi.useRealTimers());

  it("fetches a ticket and sends auth as the first WebSocket message", async () => {
    const { session, ticketApi } = setup();
    session.connect(7);
    await flush();
    const socket = FakeWebSocket.instances[0];
    socket.open();

    expect(ticketApi.issue).toHaveBeenCalledWith(7, "rt_1234567890123456");
    expect(socket.sent[0]).toEqual({
      v: 1,
      type: "auth",
      ticket: "ticket-1",
      clientInstanceId: "rt_1234567890123456",
    });
  });

  it("reconnects with a fresh ticket and restores snapshots", async () => {
    const restore = vi.fn();
    const ticketApi = {
      issue: vi
        .fn()
        .mockResolvedValueOnce({ ticket: "ticket-1" })
        .mockResolvedValueOnce({ ticket: "ticket-2" }),
    };
    const { session } = setup({ ticketApi, onRestore: restore });
    session.connect(7);
    await flush();
    FakeWebSocket.instances[0].open();
    FakeWebSocket.instances[0].message(event("session.ready"));
    FakeWebSocket.instances[0].serverClose();

    await vi.advanceTimersByTimeAsync(750);
    await flush();
    const next = FakeWebSocket.instances[1];
    next.open();
    next.message(event("session.ready", 0, {}, "ready-2"));

    expect(ticketApi.issue).toHaveBeenCalledTimes(2);
    expect(next.sent[0].ticket).toBe("ticket-2");
    expect(restore).toHaveBeenLastCalledWith(
      expect.objectContaining({ campaignId: 7, reconnected: true }),
    );
  });

  it("exhausts retries after repeated short-lived ready connections", async () => {
    const status = vi.fn();
    const { session, ticketApi } = setup({
      maxAttempts: 2,
      onStatus: status,
    });
    session.connect(7);
    await flush();

    for (const delay of [750, 1_500]) {
      const socket = FakeWebSocket.instances.at(-1);
      socket.open();
      socket.message(event("session.ready", 0, {}, `ready-${delay}`));
      socket.serverClose(1006);
      await vi.advanceTimersByTimeAsync(delay);
      await flush();
    }

    const lastSocket = FakeWebSocket.instances.at(-1);
    lastSocket.open();
    lastSocket.message(event("session.ready", 0, {}, "ready-final"));
    lastSocket.serverClose(1006);
    await vi.runAllTimersAsync();

    expect(ticketApi.issue).toHaveBeenCalledTimes(3);
    expect(status).toHaveBeenLastCalledWith(
      expect.objectContaining({ status: "exhausted" }),
    );
  });

  it("rotates the connection before the signed ticket expires", async () => {
    vi.setSystemTime(new Date("2026-08-19T12:00:00Z"));
    const ticketApi = {
      issue: vi
        .fn()
        .mockResolvedValueOnce({ ticket: "ticket-1" })
        .mockResolvedValueOnce({ ticket: "ticket-2" }),
    };
    const { session } = setup({ ticketApi });
    session.connect(7);
    await flush();
    const first = FakeWebSocket.instances[0];
    first.open();
    first.message(
      event("session.ready", 0, {
        authExpiresAt: "2026-08-19T12:01:00Z",
      }),
    );

    await vi.advanceTimersByTimeAsync(54_999);
    expect(ticketApi.issue).toHaveBeenCalledTimes(1);
    await vi.advanceTimersByTimeAsync(1);
    await flush();

    expect(ticketApi.issue).toHaveBeenCalledTimes(2);
    expect(FakeWebSocket.instances).toHaveLength(2);
  });

  it("does not immediately rotate a ticket inside the refresh margin", async () => {
    vi.setSystemTime(new Date("2026-08-19T12:00:00Z"));
    const { session, ticketApi } = setup();
    session.connect(7);
    await flush();
    const socket = FakeWebSocket.instances[0];
    socket.open();
    socket.message(
      event("session.ready", 0, {
        authExpiresAt: "2026-08-19T12:00:04Z",
      }),
    );

    await vi.advanceTimersByTimeAsync(5_000);

    expect(ticketApi.issue).toHaveBeenCalledTimes(1);
    expect(FakeWebSocket.instances).toHaveLength(1);
  });

  it("recovers 4001 auth_expired but blocks auth_failed", async () => {
    const expiredApi = {
      issue: vi
        .fn()
        .mockResolvedValueOnce({ ticket: "ticket-1" })
        .mockResolvedValueOnce({ ticket: "ticket-2" }),
    };
    const expired = setup({ ticketApi: expiredApi }).session;
    expired.connect(7);
    await flush();
    FakeWebSocket.instances[0].serverClose(4001, "auth_expired");
    await vi.advanceTimersByTimeAsync(750);
    await flush();
    expect(expiredApi.issue).toHaveBeenCalledTimes(2);

    FakeWebSocket.instances = [];
    const failedApi = {
      issue: vi.fn().mockResolvedValue({ ticket: "ticket-1" }),
    };
    const failed = setup({ ticketApi: failedApi }).session;
    failed.connect(7);
    await flush();
    FakeWebSocket.instances[0].serverClose(4001, "auth_failed");
    await vi.runAllTimersAsync();
    expect(failedApi.issue).toHaveBeenCalledTimes(1);
  });

  it("handles protocol.error ticket expiry with a fresh ticket", async () => {
    const ticketApi = {
      issue: vi
        .fn()
        .mockResolvedValueOnce({ ticket: "ticket-1" })
        .mockResolvedValueOnce({ ticket: "ticket-2" }),
    };
    const { session } = setup({ ticketApi });
    session.connect(7);
    await flush();
    const socket = FakeWebSocket.instances[0];
    socket.open();
    socket.message(event("session.ready"));
    socket.message(event("protocol.error", 0, { code: "ticket_expired" }));
    expect(ticketApi.issue).toHaveBeenCalledTimes(1);
    socket.serverClose(4001, "auth_expired");
    await vi.advanceTimersByTimeAsync(750);
    await flush();

    expect(ticketApi.issue).toHaveBeenCalledTimes(2);
  });

  it("reissues a ticket that expires during the initial handshake", async () => {
    const ticketApi = {
      issue: vi
        .fn()
        .mockResolvedValueOnce({ ticket: "expired-in-flight" })
        .mockResolvedValueOnce({ ticket: "fresh" }),
    };
    const { session } = setup({ ticketApi });
    session.connect(7);
    await flush();
    const socket = FakeWebSocket.instances[0];
    socket.open();
    socket.message(event("protocol.error", 0, { code: "ticket_expired" }));
    socket.serverClose(4001, "auth_failed");

    await vi.advanceTimersByTimeAsync(750);
    await flush();

    expect(ticketApi.issue).toHaveBeenCalledTimes(2);
  });

  it("does not retry an authentication or authorization ticket failure", async () => {
    const error = Object.assign(new Error("unauthorized"), { status: 401 });
    const ticketApi = { issue: vi.fn().mockRejectedValue(error) };
    const status = vi.fn();
    const { session } = setup({ ticketApi, onStatus: status });
    session.connect(7);
    await flush();
    await vi.runAllTimersAsync();

    expect(ticketApi.issue).toHaveBeenCalledTimes(1);
    expect(status).toHaveBeenLastCalledWith(
      expect.objectContaining({ status: "auth_failed" }),
    );
  });

  it("deduplicates events and requests resync on a sequence gap", async () => {
    const onEvent = vi.fn();
    const onGap = vi.fn();
    const presence = vi.fn();
    const { session } = setup({
      onEvent,
      onSequenceGap: onGap,
      onPresenceSnapshot: presence,
    });
    session.connect(7);
    await flush();
    const socket = FakeWebSocket.instances[0];
    socket.open();
    socket.message(event("session.ready"));
    socket.message(
      event("sync.snapshot", 1, {
        latestSequence: 1,
        users: [{ userId: 3, displayName: "Ada", online: true }],
      }),
    );
    const domain = event("character.updated", 2, { characterId: 8 }, "same");
    socket.message(domain);
    socket.message(domain);
    socket.message(event("chat.message", 4, {}, "gap"));

    expect(
      onEvent.mock.calls.filter(([item]) => item.type === "character.updated"),
    ).toHaveLength(1);
    expect(presence).toHaveBeenCalledWith([
      { userId: 3, displayName: "Ada", online: true },
    ]);
    expect(onGap).toHaveBeenCalledWith({
      campaignId: 7,
      expected: 3,
      received: 4,
    });
    expect(socket.sent.at(-1).type).toBe("sync.request");
  });

  it("cancels an in-flight ticket when the route changes campaign", async () => {
    const pending = [];
    const ticketApi = {
      issue: vi.fn(
        () =>
          new Promise((resolve) => {
            pending.push(resolve);
          }),
      ),
    };
    const { session } = setup({ ticketApi });
    session.connect(1);
    session.connect(7);
    pending[0]({ ticket: "old" });
    await flush();
    expect(FakeWebSocket.instances).toHaveLength(0);

    pending[1]({ ticket: "new" });
    await flush();
    expect(FakeWebSocket.instances).toHaveLength(1);
    expect(ticketApi.issue.mock.calls).toEqual([
      [1, "rt_1234567890123456"],
      [7, "rt_1234567890123456"],
    ]);
  });
});
