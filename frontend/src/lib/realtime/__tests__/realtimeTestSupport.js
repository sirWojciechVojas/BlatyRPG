import { vi } from "vitest";
import { createRealtimeSession } from "@/lib/realtime/realtimeSession";

export class FakeWebSocket {
  static instances = [];

  constructor(url) {
    this.url = url;
    this.readyState = 0;
    this.sent = [];
    FakeWebSocket.instances.push(this);
  }

  open() {
    this.readyState = 1;
    this.onopen?.();
  }

  message(value) {
    this.onmessage?.({ data: JSON.stringify(value) });
  }

  serverClose(code = 1006, reason = "") {
    this.readyState = 3;
    this.onclose?.({ code, reason });
  }

  send(value) {
    this.sent.push(JSON.parse(value));
  }

  close(code = 1000) {
    this.readyState = 3;
    this.onclose?.({ code });
  }
}

export const event = (
  type,
  sequence = 0,
  payload = {},
  eventId = `${type}-${sequence}`,
) => ({
  v: 1,
  eventId,
  type,
  campaignId: 7,
  sequence,
  actorUserId: 3,
  occurredAt: "2026-08-19T12:00:00Z",
  payload,
});

export const flush = async () => {
  await Promise.resolve();
  await Promise.resolve();
};

export const setup = (overrides = {}) => {
  const ticketApi = overrides.ticketApi || {
    issue: vi.fn().mockResolvedValue({ ticket: "ticket-1" }),
  };
  const options = {
    ticketApi,
    WebSocket: FakeWebSocket,
    clientInstanceId: "rt_1234567890123456",
    url: "ws://example.test/realtime",
    random: () => 0.5,
    baseDelayMs: 750,
    ...overrides,
  };
  return { session: createRealtimeSession(options), ticketApi };
};
