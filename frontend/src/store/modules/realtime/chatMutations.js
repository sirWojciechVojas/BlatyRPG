import { mergeChatMessages } from "@/lib/chat/campaignChatState";
import { normalizeRealtimeChatMessage } from "@/lib/chat/realtimeChatMessage";
import { createRealtimeChatState } from "./chatState";

const scoped = (state, items) =>
  (Array.isArray(items) ? items : [])
    .map(normalizeRealtimeChatMessage)
    .filter((message) => message?.campaignId === state.campaignId);

const capabilities = (value = {}) => ({
  canRead: value.canRead === true,
  canSend: value.canSend === true,
  canModerate: value.canModerate === true,
});

export const realtimeChatMutations = {
  RESET_CHAT(state) {
    state.chat = createRealtimeChatState();
  },
  START_CHAT_SYNC(state, { mode, continuation = false }) {
    if (!continuation) state.chat.syncPages = 0;
    state.chat.syncPages += 1;
    state.chat.syncing = mode !== "older";
    state.chat.loadingOlder = mode === "older";
    state.chat.error = null;
  },
  APPLY_CHAT_SNAPSHOT(state, payload) {
    const items = scoped(state, payload.items);
    state.chat.messages = mergeChatMessages(state.chat.messages, items);
    state.chat.latestRevision = Math.max(
      state.chat.latestRevision,
      Number(payload.latestRevision) || 0,
      ...items.map((item) => item.revision),
    );
    state.chat.capabilities = capabilities(payload.capabilities);
    state.chat.initialized = true;
    if (["initial", "older"].includes(payload.mode)) {
      state.chat.hasMoreBefore = payload.pagination?.hasMoreBefore === true;
    }
    state.chat.syncing = false;
    state.chat.loadingOlder = false;
    state.chat.error = null;
  },
  APPLY_CHAT_MESSAGE(state, source) {
    const item = normalizeRealtimeChatMessage(source);
    if (!item || item.campaignId !== state.campaignId) return;
    state.chat.messages = mergeChatMessages(state.chat.messages, [item]);
    state.chat.latestRevision = Math.max(
      state.chat.latestRevision,
      item.revision,
    );
  },
  SET_CHAT_PENDING(state, pending) {
    state.chat.pending = pending;
    state.chat.sending = Boolean(pending);
    state.chat.error = null;
  },
  APPLY_CHAT_ACK(state, payload) {
    state.chat.capabilities = capabilities(payload.capabilities);
    state.chat.lastAckNonce = String(payload.clientNonce || "");
    if (state.chat.pending?.nonce === payload.clientNonce) {
      state.chat.pending = null;
    }
    state.chat.sending = false;
  },
  SET_CHAT_ERROR(state, error) {
    state.chat.error = error;
    state.chat.syncing = false;
    state.chat.loadingOlder = false;
    state.chat.sending = false;
    if ([401, 403].includes(Number(error?.status))) {
      state.chat.capabilities = {
        canRead: false,
        canSend: false,
        canModerate: false,
      };
    }
    if (error?.code === "nonce_conflict") state.chat.pending = null;
  },
};
