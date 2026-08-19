const MAX_SYNC_PAGES = 100;

const nonce = () => {
  const cryptoImpl = typeof window === "undefined" ? null : window.crypto;
  if (cryptoImpl?.randomUUID) return cryptoImpl.randomUUID();
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (token) => {
    const random = Math.floor(Math.random() * 16);
    const value = token === "x" ? random : (random & 0x3) | 0x8;
    return value.toString(16);
  });
};

let requestSerial = 0;
const requestId = (kind) => `chat-${kind}-${++requestSerial}`;

const startSync = (context, session, page = {}, continuation = false) => {
  if (context.state.chat.syncPages >= MAX_SYNC_PAGES && continuation) {
    context.commit("SET_CHAT_ERROR", { code: "sync_limit", status: 503 });
    return false;
  }
  const mode = page.beforeRevision
    ? "older"
    : page.afterRevision
      ? "missing"
      : "initial";
  const request = {
    ...page,
    requestId: requestId(mode),
  };
  context.commit("START_CHAT_SYNC", { mode, continuation });
  const sent = session.syncChat(request);
  if (!sent) {
    context.commit("SET_CHAT_ERROR", { code: "offline", status: 0 });
  }
  return sent;
};

export const restoreRealtimeChat = (context, session) => {
  const chat = context.state.chat;
  startSync(
    context,
    session,
    chat.initialized && chat.latestRevision
      ? { afterRevision: chat.latestRevision }
      : {},
  );
  if (chat.pending) {
    session.sendChat({
      requestId: chat.pending.nonce,
      clientNonce: chat.pending.nonce,
      body: chat.pending.body,
    });
  }
};

export const routeRealtimeChatEvent = (context, session, event) => {
  if (event.type === "chat.message") {
    context.commit("APPLY_CHAT_MESSAGE", event.payload.message);
    return;
  }
  if (event.type === "chat.ack") {
    context.commit("APPLY_CHAT_ACK", event.payload);
    return;
  }
  if (event.type === "chat.error") {
    context.commit("SET_CHAT_ERROR", event.payload);
    return;
  }
  if (event.type !== "chat.snapshot") return;

  context.commit("APPLY_CHAT_SNAPSHOT", event.payload);
  if (event.payload.pagination?.hasMoreAfter) {
    const afterRevision = Number(event.payload.pagination.afterRevision) || 0;
    if (!afterRevision) {
      context.commit("SET_CHAT_ERROR", { code: "sync_invalid", status: 502 });
      return;
    }
    startSync(context, session, { afterRevision }, true);
  }
};

export const createRealtimeChatActions = (ensureSession) => ({
  syncChat(context) {
    const chat = context.state.chat;
    return startSync(
      context,
      ensureSession(context),
      chat.initialized && chat.latestRevision
        ? { afterRevision: chat.latestRevision }
        : {},
    );
  },
  loadOlderChat(context) {
    const first = context.state.chat.messages[0];
    if (!first || context.state.chat.loadingOlder) return false;
    return startSync(context, ensureSession(context), {
      beforeRevision: first.revision,
    });
  },
  sendChatMessage(context, value) {
    const body = String(value || "").trim();
    if (!body || !context.state.chat.capabilities.canSend) return null;
    const previous = context.state.chat.pending;
    const pending =
      previous?.body === body ? previous : { body, nonce: nonce() };
    context.commit("SET_CHAT_PENDING", pending);
    const sent = ensureSession(context).sendChat({
      requestId: pending.nonce,
      clientNonce: pending.nonce,
      body,
    });
    if (!sent) {
      context.commit("SET_CHAT_ERROR", { code: "offline", status: 0 });
    }
    return pending.nonce;
  },
});
