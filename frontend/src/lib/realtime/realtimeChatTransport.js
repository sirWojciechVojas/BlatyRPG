import { chatSendMessage, chatSyncMessage } from "./realtimeProtocol";

export const createRealtimeChatTransport = (isReady, send) => ({
  sendMessage(payload) {
    if (!isReady()) return false;
    return send(chatSendMessage(payload));
  },
  sync(page) {
    if (!isReady()) return false;
    return send(chatSyncMessage(page));
  },
});
