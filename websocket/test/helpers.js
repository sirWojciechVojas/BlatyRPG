import { createHmac, randomUUID } from "node:crypto";
import { WebSocket } from "ws";
import { createRealtimeServer } from "../src/realtime-server.js";

export const TEST_SECRET = "test-only-realtime-secret-with-at-least-32-bytes";
export const TEST_ORIGIN = "https://allowed.test";

export const testConfig = (overrides = {}) => ({
  host: "127.0.0.1",
  port: 0,
  path: "/realtime",
  healthPath: "/health",
  allowedOrigins: [TEST_ORIGIN],
  allowMissingOrigin: false,
  backendInternalUrl: "http://backend.internal/api/internal/realtime",
  backendTimeoutMs: 1000,
  chatPageLimit: 20,
  ticketSecret: TEST_SECRET,
  ticketIssuer: "BlatyRPG",
  ticketAudience: "blatyrpg-realtime",
  ticketMaxLifetimeSec: 60,
  clockToleranceSec: 2,
  replayCacheMaxEntries: 1000,
  maxPayloadBytes: 4096,
  authTimeoutMs: 500,
  rateLimitMessages: 40,
  rateLimitWindowMs: 1000,
  offlineGraceMs: 50,
  heartbeatIntervalMs: 1000,
  ...overrides,
});

const encode = (value) => Buffer.from(JSON.stringify(value)).toString("base64url");

export const signTicket = (overrides = {}, options = {}) => {
  const now = options.now ?? Math.floor(Date.now() / 1000);
  const secret = options.secret || TEST_SECRET;
  const header = { typ: "JWT", alg: "HS256", ...(options.header || {}) };
  const payload = {
    iss: "BlatyRPG",
    aud: "blatyrpg-realtime",
    iat: now,
    exp: now + 30,
    jti: randomUUID(),
    sub: 1,
    campaign_id: 7,
    auth_session_id: 101,
    client_instance_id: "client-instance-0001",
    display_name: "Alice",
    campaign_role: "player",
    capabilities: { canManage: false },
    ...overrides,
  };
  const unsigned = `${encode(header)}.${encode(payload)}`;
  const signature = createHmac("sha256", secret).update(unsigned).digest("base64url");
  return `${unsigned}.${signature}`;
};

export class TestClient {
  constructor(ws) {
    this.ws = ws;
    this.queue = [];
    this.history = [];
    this.waiters = [];
    this.closeInfo = null;
    this.closeWaiters = [];
    ws.on("message", (data) => this.receive(JSON.parse(data.toString("utf8"))));
    ws.on("close", (code, reason) => {
      this.closeInfo = { code, reason: reason.toString("utf8") };
      for (const resolve of this.closeWaiters.splice(0)) resolve(this.closeInfo);
    });
  }

  receive(event) {
    this.history.push(event);
    const index = this.waiters.findIndex(({ predicate }) => predicate(event));
    if (index >= 0) {
      const [{ resolve, timer }] = this.waiters.splice(index, 1);
      clearTimeout(timer);
      resolve(event);
    } else {
      this.queue.push(event);
    }
  }

  send(message) {
    this.ws.send(typeof message === "string" ? message : JSON.stringify(message));
  }

  waitFor(predicate, timeoutMs = 1500) {
    const index = this.queue.findIndex(predicate);
    if (index >= 0) return Promise.resolve(this.queue.splice(index, 1)[0]);
    return new Promise((resolve, reject) => {
      const waiter = { predicate, resolve, timer: null };
      waiter.timer = setTimeout(() => {
        const current = this.waiters.indexOf(waiter);
        if (current >= 0) this.waiters.splice(current, 1);
        reject(new Error("Timed out waiting for WebSocket event"));
      }, timeoutMs);
      this.waiters.push(waiter);
    });
  }

  event(type, timeoutMs) {
    return this.waitFor((event) => event.type === type, timeoutMs);
  }

  waitForClose(timeoutMs = 1500) {
    if (this.closeInfo) return Promise.resolve(this.closeInfo);
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => reject(new Error("Timed out waiting for close")), timeoutMs);
      this.closeWaiters.push((info) => {
        clearTimeout(timer);
        resolve(info);
      });
    });
  }

  async close() {
    if ([WebSocket.CLOSED, WebSocket.CLOSING].includes(this.ws.readyState)) {
      return this.closeInfo;
    }
    const closed = this.waitForClose();
    this.ws.close(1000, "test_complete");
    return closed;
  }
}

export const startTestServer = async (overrides = {}, dependencies = {}) => {
  const server = createRealtimeServer(testConfig(overrides), dependencies);
  const address = await server.start();
  return {
    server,
    url: `ws://127.0.0.1:${address.port}/realtime`,
  };
};

export const connectClient = (url, options = {}) =>
  new Promise((resolve, reject) => {
    const ws = new WebSocket(url, {
      origin: options.origin || TEST_ORIGIN,
      autoPong: options.autoPong,
    });
    ws.once("open", () => resolve(new TestClient(ws)));
    ws.once("error", reject);
  });

export const authenticate = async (client, ticket, options = {}) => {
  client.send({
    v: 1,
    type: "auth",
    ticket,
    clientInstanceId: options.clientInstanceId || "client-instance-0001",
    ...(options.lastSequence === undefined ? {} : { lastSequence: options.lastSequence }),
    ...(options.requestId ? { requestId: options.requestId } : {}),
  });
  const ready = await client.event("session.ready");
  const snapshot = await client.event("presence.snapshot");
  return { ready, snapshot };
};

export const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
