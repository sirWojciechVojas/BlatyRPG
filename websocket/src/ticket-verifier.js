import { createHmac, timingSafeEqual } from "node:crypto";
import { ReplayCache } from "./replay-cache.js";

export class TicketError extends Error {
  constructor(code) {
    super(code);
    this.name = "TicketError";
    this.code = code;
  }
}

const fail = (code) => {
  throw new TicketError(code);
};

const decodeObject = (segment, code) => {
  if (!segment || !/^[A-Za-z0-9_-]+$/.test(segment)) fail(code);
  try {
    const value = JSON.parse(Buffer.from(segment, "base64url").toString("utf8"));
    if (!value || typeof value !== "object" || Array.isArray(value)) fail(code);
    return value;
  } catch (error) {
    if (error instanceof TicketError) throw error;
    return fail(code);
  }
};

const positiveId = (value, code) => {
  if (typeof value === "string" && !/^[1-9][0-9]*$/.test(value)) fail(code);
  const parsed = Number(value);
  if (!Number.isSafeInteger(parsed) || parsed < 1) fail(code);
  return parsed;
};

const integerClaim = (value, code) => {
  if (!Number.isSafeInteger(value)) fail(code);
  return value;
};

const safeIdentifier = (value, code, min = 8, max = 128) => {
  const normalized = String(value || "");
  if (
    normalized.length < min ||
    normalized.length > max ||
    !/^[A-Za-z0-9._:-]+$/.test(normalized)
  ) {
    fail(code);
  }
  return normalized;
};

const clientIdentifier = (value, code) => {
  const normalized = String(value || "");
  if (
    normalized.length < 16 ||
    normalized.length > 128 ||
    !/^[A-Za-z0-9_-]+$/.test(normalized)
  ) {
    fail(code);
  }
  return normalized;
};

const audienceMatches = (claim, expected) =>
  typeof claim === "string"
    ? claim === expected
    : Array.isArray(claim) && claim.some((entry) => entry === expected);

const optionalText = (value, max) => {
  if (value === undefined || value === null) return null;
  const normalized = String(value).trim();
  return normalized ? normalized.slice(0, max) : null;
};

const capabilities = (value) => {
  if (!value || typeof value !== "object" || Array.isArray(value)) return {};
  const result = {};
  for (const [key, enabled] of Object.entries(value).slice(0, 32)) {
    if (/^[A-Za-z][A-Za-z0-9_.:-]{0,63}$/.test(key) && typeof enabled === "boolean") {
      result[key] = enabled;
    }
  }
  return Object.freeze(result);
};

export class TicketVerifier {
  constructor(config, options = {}) {
    this.secret = config.ticketSecret;
    this.issuer = config.ticketIssuer;
    this.audience = config.ticketAudience;
    this.maxLifetimeSec = config.ticketMaxLifetimeSec;
    this.clockToleranceSec = config.clockToleranceSec;
    this.now = options.now || (() => Math.floor(Date.now() / 1000));
    this.replayCache =
      options.replayCache || new ReplayCache({ maxEntries: config.replayCacheMaxEntries });
  }

  verify(token, { clientInstanceId } = {}) {
    const raw = String(token || "");
    if (!raw || raw.length > 8192) fail("ticket_invalid");
    const parts = raw.split(".");
    if (parts.length !== 3) fail("ticket_invalid");
    const [encodedHeader, encodedPayload, encodedSignature] = parts;
    const header = decodeObject(encodedHeader, "ticket_header_invalid");
    const payload = decodeObject(encodedPayload, "ticket_payload_invalid");
    if (header.alg !== "HS256" || (header.typ && header.typ !== "JWT")) {
      fail("ticket_algorithm_invalid");
    }

    const expected = createHmac("sha256", this.secret)
      .update(`${encodedHeader}.${encodedPayload}`)
      .digest();
    let received;
    try {
      received = Buffer.from(encodedSignature, "base64url");
    } catch {
      fail("ticket_signature_invalid");
    }
    if (received.length !== expected.length || !timingSafeEqual(received, expected)) {
      fail("ticket_signature_invalid");
    }

    if (payload.iss !== this.issuer) fail("ticket_issuer_invalid");
    if (!audienceMatches(payload.aud, this.audience)) fail("ticket_audience_invalid");
    const now = this.now();
    const issuedAt = integerClaim(payload.iat, "ticket_iat_invalid");
    const expiresAt = integerClaim(payload.exp, "ticket_exp_invalid");
    if (issuedAt > now + this.clockToleranceSec) fail("ticket_not_yet_valid");
    if (payload.nbf !== undefined) {
      const notBefore = integerClaim(payload.nbf, "ticket_nbf_invalid");
      if (notBefore > now + this.clockToleranceSec) fail("ticket_not_yet_valid");
    }
    if (expiresAt <= now) fail("ticket_expired");
    if (expiresAt <= issuedAt || expiresAt - issuedAt > this.maxLifetimeSec) {
      fail("ticket_lifetime_invalid");
    }

    const jti = safeIdentifier(payload.jti, "ticket_jti_invalid");
    const userId = positiveId(payload.sub, "ticket_subject_invalid");
    const campaignId = positiveId(payload.campaign_id, "ticket_campaign_invalid");
    const authSessionId = positiveId(
      payload.auth_session_id,
      "ticket_auth_session_invalid",
    );
    const normalizedInstance = clientIdentifier(clientInstanceId, "client_instance_invalid");
    const ticketInstance = clientIdentifier(
      payload.client_instance_id,
      "ticket_client_instance_invalid",
    );
    if (ticketInstance !== normalizedInstance) {
      fail("ticket_client_instance_mismatch");
    }
    try {
      if (!this.replayCache.consume(jti, expiresAt * 1000)) fail("ticket_replayed");
    } catch (error) {
      if (error instanceof TicketError) throw error;
      fail("ticket_replay_cache_full");
    }

    return Object.freeze({
      jti,
      userId,
      campaignId,
      authSessionId,
      clientInstanceId: normalizedInstance,
      displayName: optionalText(payload.display_name, 100),
      campaignRole: optionalText(payload.campaign_role, 32),
      capabilities: capabilities(payload.capabilities),
      issuedAt: issuedAt * 1000,
      expiresAt: expiresAt * 1000,
    });
  }
}
