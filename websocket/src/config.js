const integer = (value, fallback, name, min, max) => {
  const parsed = value === undefined || value === "" ? fallback : Number(value);
  if (!Number.isSafeInteger(parsed) || parsed < min || parsed > max) {
    throw new Error(`${name} must be an integer between ${min} and ${max}`);
  }
  return parsed;
};

const boolean = (value, fallback = false) => {
  if (value === undefined || value === "") return fallback;
  if (["1", "true", "yes", "on"].includes(String(value).toLowerCase())) return true;
  if (["0", "false", "no", "off"].includes(String(value).toLowerCase())) return false;
  throw new Error("Boolean environment value is invalid");
};

const pathValue = (value, fallback, name) => {
  const path = String(value || fallback).trim();
  if (!path.startsWith("/") || path.includes("?") || path.includes("#")) {
    throw new Error(`${name} must be an absolute URL path`);
  }
  return path;
};

const normalizedOrigin = (value) => {
  let parsed;
  try {
    parsed = new URL(String(value).trim());
  } catch {
    throw new Error(`Invalid WebSocket origin: ${value}`);
  }
  if (!["http:", "https:"].includes(parsed.protocol) || parsed.origin === "null") {
    throw new Error(`Invalid WebSocket origin: ${value}`);
  }
  return parsed.origin;
};

const origins = (value) => {
  const entries = String(value || "")
    .split(",")
    .map((entry) => entry.trim())
    .filter(Boolean);
  if (!entries.length) throw new Error("WS_ALLOWED_ORIGINS must not be empty");
  if (entries.includes("*")) throw new Error("Wildcard WebSocket origins are forbidden");
  return Object.freeze([...new Set(entries.map(normalizedOrigin))]);
};

const internalUrl = (value) => {
  let parsed;
  try {
    parsed = new URL(String(value || ""));
  } catch {
    throw new Error("WS_BACKEND_INTERNAL_URL must be an absolute HTTP(S) URL");
  }
  if (!["http:", "https:"].includes(parsed.protocol) || parsed.username || parsed.password) {
    throw new Error("WS_BACKEND_INTERNAL_URL is invalid");
  }
  if (parsed.search || parsed.hash || parsed.pathname === "/") {
    throw new Error("WS_BACKEND_INTERNAL_URL must include an API path");
  }
  return parsed.toString().replace(/\/$/, "");
};

export const createConfig = (env = process.env) => {
  const ticketSecret = String(env.WS_TICKET_SECRET || "");
  if (Buffer.byteLength(ticketSecret, "utf8") < 32) {
    throw new Error("WS_TICKET_SECRET must contain at least 32 bytes");
  }

  return Object.freeze({
    host: String(env.WS_HOST || "0.0.0.0"),
    port: integer(env.WS_PORT, 8081, "WS_PORT", 0, 65535),
    path: pathValue(env.WS_PATH, "/realtime", "WS_PATH"),
    healthPath: pathValue(env.WS_HEALTH_PATH, "/health", "WS_HEALTH_PATH"),
    allowedOrigins: origins(env.WS_ALLOWED_ORIGINS),
    allowMissingOrigin: boolean(env.WS_ALLOW_MISSING_ORIGIN, false),
    backendInternalUrl: internalUrl(env.WS_BACKEND_INTERNAL_URL),
    backendTimeoutMs: integer(
      env.WS_BACKEND_TIMEOUT_MS,
      5000,
      "WS_BACKEND_TIMEOUT_MS",
      500,
      30000,
    ),
    chatPageLimit: integer(env.WS_CHAT_PAGE_LIMIT, 20, "WS_CHAT_PAGE_LIMIT", 1, 25),
    ticketSecret,
    ticketIssuer: String(env.WS_TICKET_ISSUER || "BlatyRPG"),
    ticketAudience: String(env.WS_TICKET_AUDIENCE || "blatyrpg-realtime"),
    ticketMaxLifetimeSec: integer(
      env.WS_TICKET_MAX_LIFETIME_SEC,
      60,
      "WS_TICKET_MAX_LIFETIME_SEC",
      5,
      300,
    ),
    clockToleranceSec: integer(
      env.WS_CLOCK_TOLERANCE_SEC,
      2,
      "WS_CLOCK_TOLERANCE_SEC",
      0,
      30,
    ),
    replayCacheMaxEntries: integer(
      env.WS_REPLAY_CACHE_MAX_ENTRIES,
      10000,
      "WS_REPLAY_CACHE_MAX_ENTRIES",
      100,
      1000000,
    ),
    maxPayloadBytes: integer(
      env.WS_MAX_PAYLOAD_BYTES,
      65536,
      "WS_MAX_PAYLOAD_BYTES",
      1024,
      1048576,
    ),
    authTimeoutMs: integer(env.WS_AUTH_TIMEOUT_MS, 5000, "WS_AUTH_TIMEOUT_MS", 100, 30000),
    rateLimitMessages: integer(
      env.WS_RATE_LIMIT_MESSAGES,
      40,
      "WS_RATE_LIMIT_MESSAGES",
      2,
      10000,
    ),
    rateLimitWindowMs: integer(
      env.WS_RATE_LIMIT_WINDOW_MS,
      10000,
      "WS_RATE_LIMIT_WINDOW_MS",
      100,
      60000,
    ),
    offlineGraceMs: integer(
      env.WS_OFFLINE_GRACE_MS,
      5000,
      "WS_OFFLINE_GRACE_MS",
      0,
      60000,
    ),
    heartbeatIntervalMs: integer(
      env.WS_HEARTBEAT_INTERVAL_MS,
      25000,
      "WS_HEARTBEAT_INTERVAL_MS",
      1000,
      120000,
    ),
  });
};
