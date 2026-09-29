export class BackendHandoutError extends Error {
  constructor(code, status = 503) {
    super(code);
    this.name = "BackendHandoutError";
    this.code = String(code || "handout_unavailable");
    this.status = Number(status) || 503;
  }
}

const batchId = (value) =>
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(
    String(value || ""),
  )
    ? String(value).toLowerCase()
    : null;

const target = (value) => {
  const userId = Number(value?.userId);
  const notificationId = Number(value?.notificationId);
  const handoutId = Number(value?.handoutId);
  const title = String(value?.title || "").slice(0, 180);
  if (
    !Number.isSafeInteger(userId) ||
    userId < 1 ||
    !Number.isSafeInteger(notificationId) ||
    notificationId < 1 ||
    !Number.isSafeInteger(handoutId) ||
    handoutId < 1 ||
    !title
  ) {
    throw new BackendHandoutError("backend_response_invalid", 502);
  }
  return { userId, notificationId, handoutId, title };
};

export class BackendHandoutClient {
  constructor(config, options = {}) {
    this.baseUrl = config.backendInternalUrl;
    this.timeoutMs = config.backendTimeoutMs;
    this.fetch = options.fetch || globalThis.fetch;
  }

  async deliveryTargets(session, requestedBatchId) {
    const normalizedBatchId = batchId(requestedBatchId);
    if (!normalizedBatchId) throw new BackendHandoutError("handout_batch_invalid", 422);
    let response;
    try {
      response = await this.fetch(
        this.baseUrl + "/campaigns/" + session.campaignId + "/handouts/delivery",
        {
          method: "POST",
          headers: {
            Authorization: "Realtime " + session.realtimeTicket,
            "Content-Type": "application/json",
            "X-Realtime-Client-Instance": session.clientInstanceId,
          },
          body: JSON.stringify({ batchId: normalizedBatchId }),
          signal: AbortSignal.timeout(this.timeoutMs),
        },
      );
    } catch (_error) {
      throw new BackendHandoutError("handout_unavailable", 503);
    }
    let body;
    try {
      const text = await response.text();
      if (Buffer.byteLength(text, "utf8") > 131072) throw new Error("response_too_large");
      body = JSON.parse(text);
    } catch (_error) {
      throw new BackendHandoutError("backend_response_invalid", 502);
    }
    if (!response.ok) throw new BackendHandoutError(body?.code, response.status);
    if (!Array.isArray(body?.targets) || body.targets.length > 200) {
      throw new BackendHandoutError("backend_response_invalid", 502);
    }
    return body.targets.map(target);
  }
}
