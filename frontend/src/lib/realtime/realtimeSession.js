import { getClientInstanceId } from "./clientInstanceId";
import { createRealtimeChatTransport } from "./realtimeChatTransport";
import { createAuthExpiryScheduler } from "./authExpiryScheduler";
import {
  authMessage,
  leaveMessage,
  syncRequestMessage,
} from "./realtimeProtocol";
import { createRealtimeEventRouter } from "./realtimeEventRouter";
import { createReconnectBudget } from "./reconnectBudget";
import {
  realtimeCloseStatus,
  shouldReconnectClose,
  shouldReconnectTicketError,
} from "./reconnectPolicy";
import { createRealtimeSequence } from "./realtimeSequence";
import { realtimeTicketApiClient } from "./realtimeTicketApiClient";
import { resolveRealtimeUrl } from "./realtimeUrl";
import { safeCallback } from "./safeCallback";
const noop = () => {};
export const createRealtimeSession = (options = {}) => {
  const ticketApi = options.ticketApi || realtimeTicketApiClient;
  const WebSocketImpl =
    options.WebSocket || (typeof WebSocket === "undefined" ? null : WebSocket);
  const sequence = options.sequence || createRealtimeSequence(options);
  const timers = {
    set: options.setTimeout || setTimeout,
    clear: options.clearTimeout || clearTimeout,
  };
  const clientInstanceId =
    options.clientInstanceId || getClientInstanceId(options);
  const callbacks = {
    onEvent: options.onEvent || noop,
    onPresenceSnapshot: options.onPresenceSnapshot || noop,
    onPresenceChange: options.onPresenceChange || noop,
    onRestore: options.onRestore || noop,
    onSequenceGap: options.onSequenceGap || noop,
    onStatus: options.onStatus || noop,
  };
  let campaignId = null;
  let generation = 0;
  let connectionSerial = 0;
  let socket = null;
  let retryTimer = null;
  let intentional = true;
  let blocked = false;
  let authenticated = false;
  let syncPending = false;
  let recoverableAuthClose = false;
  let status = "idle";
  let refreshTicket = noop;
  const authExpiry = createAuthExpiryScheduler({
    ...options,
    onRefresh: () => refreshTicket(),
  });
  const retryBudget = createReconnectBudget(options, timers);
  const setStatus = (next, details = {}) => {
    status = next;
    safeCallback(callbacks.onStatus, {
      status,
      campaignId,
      attempt: retryBudget.value(),
      ...details,
    });
  };
  const isActive = (expectedGeneration, serial) =>
    generation === expectedGeneration &&
    (serial === undefined || connectionSerial === serial);
  const clearRetry = () => {
    if (retryTimer !== null) timers.clear?.(retryTimer);
    retryTimer = null;
  };
  const send = (message) => {
    if (!socket || socket.readyState !== 1) return false;
    socket.send(JSON.stringify(message));
    return true;
  };
  const chat = createRealtimeChatTransport(() => authenticated, send);
  const requestSync = () => {
    if (!authenticated || syncPending) return false;
    syncPending = true;
    setStatus("syncing");
    return send(syncRequestMessage(sequence.get()));
  };
  const eventRouter = createRealtimeEventRouter({
    sequence,
    getCampaignId: () => campaignId,
    requestSync,
    onEvent: callbacks.onEvent,
    onPresenceChange: callbacks.onPresenceChange,
    onPresenceSnapshot: callbacks.onPresenceSnapshot,
    onSequenceGap: callbacks.onSequenceGap,
    onReady: ({ event, reconnected }) => {
      authenticated = true;
      retryBudget.markReady();
      authExpiry.schedule(event.payload.authExpiresAt);
      setStatus("ready", { reconnected });
      safeCallback(callbacks.onRestore, {
        campaignId,
        lastSequence: sequence.get(),
        reconnected,
      });
      requestSync();
    },
    onSessionError: ({ code, blocked: cannotRetry, recoverable }) => {
      if (recoverable) {
        authExpiry.cancel();
        recoverableAuthClose = true;
        setStatus("auth_expired", { error: code });
        return;
      }
      recoverableAuthClose = false;
      blocked = cannotRetry;
      setStatus(blocked ? "auth_failed" : "error", { error: code });
    },
    onSyncComplete: () => {
      syncPending = false;
      setStatus("ready", { resynced: true });
    },
  });
  let connectAttempt;
  const scheduleReconnect = (expectedGeneration, reason) => {
    if (
      !isActive(expectedGeneration) ||
      intentional ||
      blocked ||
      retryTimer !== null
    ) {
      return;
    }
    const nextRetry = retryBudget.consume();
    if (!nextRetry) {
      setStatus("exhausted", { reason, manualRetryAvailable: true });
      return;
    }
    const { delay } = nextRetry;
    setStatus("reconnecting", { delay, reason });
    retryTimer = timers.set?.(() => {
      retryTimer = null;
      connectAttempt(expectedGeneration);
    }, delay);
  };
  connectAttempt = async (expectedGeneration) => {
    if (!isActive(expectedGeneration) || intentional || blocked) return;
    clearRetry();
    authenticated = false;
    syncPending = false;
    recoverableAuthClose = false;
    setStatus("ticketing");
    const serial = ++connectionSerial;
    let ticketResult;
    try {
      ticketResult = await ticketApi.issue(campaignId, clientInstanceId);
    } catch (error) {
      if (!isActive(expectedGeneration, serial)) return;
      if (!shouldReconnectTicketError(error)) {
        blocked = true;
        setStatus(error?.status === 403 ? "forbidden" : "auth_failed", {
          error,
          manualRetryAvailable: true,
        });
        return;
      }
      scheduleReconnect(expectedGeneration, error);
      return;
    }
    if (!isActive(expectedGeneration, serial)) return;
    try {
      socket = new WebSocketImpl(
        typeof options.url === "function"
          ? options.url(campaignId)
          : options.url || resolveRealtimeUrl(),
      );
    } catch (error) {
      scheduleReconnect(expectedGeneration, error);
      return;
    }
    const activeSocket = socket;
    setStatus("connecting");
    activeSocket.onopen = () => {
      if (!isActive(expectedGeneration, serial) || socket !== activeSocket) {
        return;
      }
      setStatus("authenticating");
      send(
        authMessage({
          ticket: ticketResult.ticket,
          clientInstanceId,
          lastSequence: sequence.get(),
        }),
      );
    };
    activeSocket.onmessage = (event) => {
      if (isActive(expectedGeneration, serial)) eventRouter.handle(event);
    };
    activeSocket.onerror = () => {
      if (isActive(expectedGeneration, serial)) setStatus("connection_error");
    };
    activeSocket.onclose = (event) => {
      if (!isActive(expectedGeneration, serial)) return;
      authExpiry.cancel();
      if (socket === activeSocket) socket = null;
      authenticated = false;
      syncPending = false;
      const recoverableTicketClose = recoverableAuthClose;
      recoverableAuthClose = false;
      if (
        shouldReconnectClose(
          event,
          intentional || blocked,
          recoverableTicketClose,
        )
      ) {
        scheduleReconnect(expectedGeneration, event);
      } else {
        setStatus(realtimeCloseStatus(Number(event?.code)), {
          code: Number(event?.code) || 0,
          manualRetryAvailable: !intentional,
        });
      }
    };
  };
  const closeSocket = (notifyLeave) => {
    if (!socket) return;
    if (notifyLeave && authenticated) send(leaveMessage());
    try {
      socket.close(1000, "client_leave");
    } catch (_error) {
      // The generation check invalidates handlers even if close fails.
    }
    socket = null;
  };
  const connect = (nextCampaignId) => {
    const id = Number(nextCampaignId);
    if (!Number.isInteger(id) || id < 1) {
      throw new TypeError("campaignId_must_be_positive_integer");
    }
    if (campaignId === id && !["idle", "disconnected"].includes(status)) return;
    generation += 1;
    authExpiry.cancel();
    clearRetry();
    retryBudget.reset();
    closeSocket(true);
    campaignId = id;
    sequence.select(id);
    eventRouter.reset();
    intentional = false;
    blocked = false;
    authenticated = false;
    syncPending = false;
    recoverableAuthClose = false;
    connectAttempt(generation);
  };
  const disconnect = () => {
    generation += 1;
    intentional = true;
    recoverableAuthClose = false;
    authExpiry.cancel();
    clearRetry();
    retryBudget.reset();
    closeSocket(true);
    campaignId = null;
    sequence.select(null);
    safeCallback(callbacks.onPresenceSnapshot, []);
    setStatus("disconnected");
  };
  const restart = (resetBudget) => {
    if (!campaignId) return false;
    generation += 1;
    authExpiry.cancel();
    clearRetry();
    if (resetBudget) retryBudget.reset();
    else retryBudget.cancelStabilityWindow();
    closeSocket(false);
    intentional = false;
    blocked = false;
    recoverableAuthClose = false;
    connectAttempt(generation);
    return true;
  };
  const retry = () => restart(true);
  refreshTicket = () => restart(false);
  return {
    clientInstanceId,
    connect,
    disconnect,
    requestSync,
    retry,
    sendChat: chat.sendMessage,
    syncChat: chat.sync,
    snapshot: () => ({
      campaignId,
      status,
      attempt: retryBudget.value(),
      lastSequence: sequence.get(),
      connected: authenticated,
    }),
  };
};
