import { createRealtimeChatState } from "./chatState";

export const createRealtimeState = () => ({
  campaignId: null,
  status: "idle",
  attempt: 0,
  retryDelay: null,
  manualRetryAvailable: false,
  presenceByUser: {},
  lastSequence: 0,
  error: null,
  chat: createRealtimeChatState(),
});
