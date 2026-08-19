export const createRealtimeChatState = () => ({
  messages: [],
  capabilities: { canRead: false, canSend: false, canModerate: false },
  initialized: false,
  latestRevision: 0,
  hasMoreBefore: false,
  syncing: false,
  loadingOlder: false,
  syncPages: 0,
  pending: null,
  sending: false,
  lastAckNonce: null,
  error: null,
});
