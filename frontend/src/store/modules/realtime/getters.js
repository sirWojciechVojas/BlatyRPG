export const realtimeGetters = {
  chatMessages: (state) => state.chat.messages,
  chatCapabilities: (state) => state.chat.capabilities,
  chatReady: (state) => state.chat.initialized,
  chatSending: (state) => state.chat.sending,
  onlineUsers: (state) =>
    Object.values(state.presenceByUser)
      .filter((item) => item.online)
      .sort((left, right) => left.displayName.localeCompare(right.displayName)),
  isConnected: (state) =>
    state.status === "ready" || state.status === "syncing",
  isReconnecting: (state) =>
    ["ticketing", "connecting", "authenticating", "reconnecting"].includes(
      state.status,
    ),
};
