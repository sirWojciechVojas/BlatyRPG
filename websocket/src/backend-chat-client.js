export class BackendChatError extends Error {
  constructor(code, status = 503, details = {}) {
    super(code);
    this.name = "BackendChatError";
    this.code = String(code || "chat_unavailable");
    this.status = Number(status) || 503;
    this.details = details && typeof details === "object" ? details : {};
  }
}

const positiveId = (value) => {
  const number = Number(value);
  return Number.isSafeInteger(number) && number > 0 ? number : null;
};

const capabilities = (value = {}) => ({
  canRead: value.canRead === true,
  canSend: value.canSend === true,
  canModerate: value.canModerate === true,
});

const message = (value, campaignId) => {
  const id = positiveId(value?.id);
  const revision = positiveId(value?.revision ?? value?.id);
  const scopedCampaign = positiveId(value?.campaignId ?? value?.campaign_id);
  const rawAuthorId = value?.author?.id;
  const authorId = rawAuthorId === null ? null : positiveId(rawAuthorId);
  if (
    !id ||
    revision !== id ||
    scopedCampaign !== campaignId ||
    (rawAuthorId !== null && !authorId) ||
    typeof value?.body !== "string" ||
    Array.from(value.body).length > 2000
  ) {
    throw new BackendChatError("backend_response_invalid", 502);
  }
  return {
    id,
    revision,
    campaignId: scopedCampaign,
    type: String(value.type || "text"),
    body: value.body,
    author: {
      id: authorId,
      name: String(value.author?.name || "").slice(0, 100),
    },
    clientNonce: value.clientNonce ?? null,
    metadata: value.metadata ?? null,
    createdAt: value.createdAt ?? null,
  };
};

const pagination = (value = {}) => ({
  limit: Number(value.limit) || 20,
  beforeRevision: positiveId(value.beforeId ?? value.beforeRevision),
  afterRevision: positiveId(value.afterId ?? value.afterRevision),
  hasMoreBefore: value.hasMoreBefore === true,
  hasMoreAfter: value.hasMoreAfter === true,
});

export class BackendChatClient {
  constructor(config, options = {}) {
    this.baseUrl = config.backendInternalUrl;
    this.timeoutMs = config.backendTimeoutMs;
    this.pageLimit = config.chatPageLimit;
    this.fetch = options.fetch || globalThis.fetch;
  }

  sync(session, page = {}) {
    const payload = { limit: page.limit || this.pageLimit };
    if (positiveId(page.afterRevision)) payload.afterRevision = Number(page.afterRevision);
    if (positiveId(page.beforeRevision)) payload.beforeRevision = Number(page.beforeRevision);
    return this.request(session, "sync", payload).then((result) => ({
      items: (Array.isArray(result.items) ? result.items : []).map((item) =>
        message(item, session.campaignId),
      ),
      pagination: pagination(result.pagination),
      capabilities: capabilities(result.capabilities),
      latestRevision: Math.max(0, Number(result.sync?.latestRevision) || 0),
    }));
  }

  send(session, payload) {
    return this.request(session, "send", {
      body: payload.body,
      clientNonce: payload.clientNonce,
    }).then((result) => ({
      message: message(result.message, session.campaignId),
      capabilities: capabilities(result.capabilities),
      duplicate: result.duplicate === true,
    }));
  }

  async request(session, action, payload) {
    const endpoint =
      `${this.baseUrl}/campaigns/${session.campaignId}/chat/${action}`;
    let response;
    try {
      response = await this.fetch(endpoint, {
        method: "POST",
        headers: {
          Authorization: `Realtime ${session.realtimeTicket}`,
          "Content-Type": "application/json",
          "X-Realtime-Client-Instance": session.clientInstanceId,
        },
        body: JSON.stringify(payload),
        signal: AbortSignal.timeout(this.timeoutMs),
      });
    } catch (_error) {
      throw new BackendChatError("chat_unavailable", 503);
    }

    let result;
    try {
      const text = await response.text();
      if (Buffer.byteLength(text, "utf8") > 524288) {
        throw new Error("response_too_large");
      }
      result = JSON.parse(text);
    } catch (_error) {
      throw new BackendChatError("backend_response_invalid", 502);
    }
    if (!response.ok) {
      throw new BackendChatError(result?.code, response.status, {
        errors: result?.errors,
        retryAfter: Number(result?.errors?.retryAfter) || null,
      });
    }
    if (!result || typeof result !== "object" || Array.isArray(result)) {
      throw new BackendChatError("backend_response_invalid", 502);
    }
    return result;
  }
}
