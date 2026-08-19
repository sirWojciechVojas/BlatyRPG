import { parseServerEvent, presenceItems } from "./realtimeProtocol";
import { safeCallback } from "./safeCallback";

export const createRealtimeEventRouter = (options) => {
  const seen = new Set();
  const order = [];
  let hasConnected = false;

  const remember = (eventId) => {
    if (!eventId || seen.has(eventId)) return false;
    seen.add(eventId);
    order.push(eventId);
    if (order.length > 512) seen.delete(order.shift());
    return true;
  };

  const acceptSequence = (event, authoritative = false) => {
    const last = options.sequence.get();
    if (authoritative) {
      options.sequence.set(
        Math.max(event.sequence, Number(event.payload.latestSequence) || 0),
      );
      return true;
    }
    if (event.sequence <= 0) return true;
    if (event.sequence <= last) return false;
    if (last > 0 && event.sequence > last + 1) {
      safeCallback(options.onSequenceGap, {
        campaignId: options.getCampaignId(),
        expected: last + 1,
        received: event.sequence,
      });
      options.requestSync();
      return false;
    }
    options.sequence.set(event.sequence);
    return true;
  };

  const handle = (raw) => {
    const event = parseServerEvent(raw);
    if (!event) return;
    if (
      event.type !== "protocol.error" &&
      event.campaignId !== options.getCampaignId()
    ) {
      return;
    }
    if (
      event.type === "protocol.error" &&
      event.campaignId !== null &&
      event.campaignId !== options.getCampaignId()
    ) {
      return;
    }
    if (event.eventId && seen.has(event.eventId)) return;

    if (event.type === "session.error" || event.type === "protocol.error") {
      const code = String(event.payload.code || "session_error");
      options.onSessionError({
        code,
        recoverable: code === "ticket_expired" || code === "auth_expired",
        blocked:
          code.includes("forbidden") ||
          (code.includes("auth") && code !== "auth_expired"),
      });
      return;
    }
    if (event.type === "session.ready") {
      const reconnected = hasConnected;
      hasConnected = true;
      remember(event.eventId);
      if (event.sequence === options.sequence.get() + 1) {
        options.sequence.set(event.sequence);
      }
      options.onReady({ event, reconnected });
      return;
    }
    if (event.type === "sync.snapshot") {
      if (!acceptSequence(event, true)) return;
      remember(event.eventId);
      safeCallback(options.onPresenceSnapshot, presenceItems(event.payload));
      safeCallback(options.onEvent, event);
      options.onSyncComplete();
      return;
    }
    if (event.type === "presence.snapshot") {
      if (!acceptSequence(event, true)) return;
      remember(event.eventId);
      safeCallback(options.onPresenceSnapshot, presenceItems(event.payload));
      return;
    }
    if (!acceptSequence(event)) return;
    remember(event.eventId);
    if (event.type === "presence.changed") {
      safeCallback(
        options.onPresenceChange,
        event.payload.user ?? event.payload.presence ?? event.payload,
      );
      return;
    }
    safeCallback(options.onEvent, event);
  };

  const reset = () => {
    seen.clear();
    order.length = 0;
    hasConnected = false;
  };

  return { handle, reset };
};
