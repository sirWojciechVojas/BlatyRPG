import { BackendSoundEffectsError } from "./backend-sound-effects-client.js";
import { createServerEvent, sendEvent } from "./protocol.js";

const isController = (session) =>
  ["gm", "admin"].includes(String(session.campaignRole || "").toLowerCase());

const eventFor = (session, type, payload, sequence = null) =>
  createServerEvent({
    type,
    campaignId: session.campaignId,
    sequence,
    actorUserId: session.userId,
    payload,
  });

const included = (recipient, actor, payload) => {
  const scope = String(payload.audienceScope || "all");
  if (scope === "all") return true;
  if (scope === "gm") return isController(recipient);
  return (
    recipient === actor ||
    (payload.recipientUserIds || []).some(
      (id) => Number(id) === Number(recipient.userId),
    )
  );
};

export const createSoundEffectsHandler = ({
  backend,
  rooms,
  onAuthenticationFailure,
}) => {
  const writes = new Map();

  const reportError = (session, requestId, cause) => {
    if (Number(cause?.status) === 401) {
      onAuthenticationFailure(session);
      return;
    }
    sendEvent(
      session.ws,
      eventFor(session, "SOUND_EFFECT_ERROR", {
        requestId,
        code:
          cause instanceof BackendSoundEffectsError
            ? cause.code
            : "sound_effects_unavailable",
        status: Number(cause?.status) || 503,
      }),
    );
  };

  const state = async (session) => backend.state(session);

  const sendState = async (session, requestId = null) => {
    try {
      sendEvent(
        session.ws,
        eventFor(session, "SOUND_EFFECT_STATE", {
          ...(await state(session)),
          ...(requestId ? { requestId } : {}),
        }),
      );
    } catch (error) {
      reportError(session, requestId, error);
    }
  };

  const mutate = async (session, request) => {
    if (!isController(session)) {
      reportError(
        session,
        request.requestId,
        new BackendSoundEffectsError("sound_effect_forbidden", 403),
      );
      return;
    }
    const payload = await backend.command(session, request);
    if (payload.stopOthers) {
      const stopSequence = rooms.nextSequence(session.campaignId);
      const stopEvent = eventFor(
        session,
        "SOUND_EFFECT_STOP_ALL",
        {
          requestId: payload.requestId,
          serverTime: payload.serverTime,
          executeAt: payload.executeAt,
          fadeOutMs: 0,
          audienceScope: "all",
          recipientUserIds: [],
        },
        stopSequence,
      );
      for (const recipient of rooms.sessions(session.campaignId)) {
        sendEvent(recipient.ws, stopEvent);
      }
    }
    const sequence = rooms.nextSequence(session.campaignId);
    const type = String(payload.type || request.type);
    const event = eventFor(session, type, payload, sequence);
    for (const recipient of rooms.sessions(session.campaignId)) {
      if (included(recipient, session, payload)) sendEvent(recipient.ws, event);
    }
  };

  const orderedMutate = (session, request) => {
    const previous = writes.get(session.campaignId) || Promise.resolve();
    const current = previous
      .catch(() => {})
      .then(() => mutate(session, request));
    writes.set(session.campaignId, current);
    current
      .catch((cause) => reportError(session, request.requestId, cause))
      .finally(() => {
        if (writes.get(session.campaignId) === current) {
          writes.delete(session.campaignId);
        }
      });
  };

  const handle = (session, request) => {
    if (request.type === "SOUND_EFFECT_SYNC") {
      void sendState(session, request.requestId);
      return;
    }
    orderedMutate(session, request);
  };

  return { handle, sendState, state };
};
