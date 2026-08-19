import { normalizePresence } from "./presence";

export const realtimeMutations = {
  SET_CAMPAIGN(state, campaignId) {
    state.campaignId = Number(campaignId) || null;
    state.presenceByUser = {};
    state.lastSequence = 0;
    state.error = null;
  },
  SET_STATUS(state, details) {
    state.status = details.status;
    state.attempt = Number(details.attempt) || 0;
    state.retryDelay = Number(details.delay) || null;
    state.manualRetryAvailable = details.manualRetryAvailable === true;
    state.error = details.error || null;
  },
  SET_PRESENCE_SNAPSHOT(state, items) {
    state.presenceByUser = Object.fromEntries(
      items
        .map(normalizePresence)
        .filter((item) => item.userId)
        .map((item) => [item.userId, item]),
    );
  },
  APPLY_PRESENCE_CHANGE(state, source) {
    const item = normalizePresence(source);
    if (!item.userId) return;
    state.presenceByUser = {
      ...state.presenceByUser,
      [item.userId]: item,
    };
  },
  SET_LAST_SEQUENCE(state, sequence) {
    state.lastSequence = Math.max(0, Number(sequence) || 0);
  },
};
