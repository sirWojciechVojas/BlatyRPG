import { BackendTokenError } from "./backend-token-client.js";
import { createServerEvent, sendEvent } from "./protocol.js";

const eventFor = (session, type, payload, sequence = null) =>
  createServerEvent({
    type,
    campaignId: session.campaignId,
    sequence,
    actorUserId: session.userId,
    payload,
  });

export const createTokenHandler = ({ backend, rooms, onAuthenticationFailure }) => {
  const writes = new Map();

  const failure = (session, request, cause) => {
    const error =
      cause instanceof BackendTokenError
        ? cause
        : new BackendTokenError("token_unavailable", 503);
    if (error.status === 401) {
      onAuthenticationFailure(session);
      return;
    }
    sendEvent(
      session.ws,
      eventFor(session, "token.error", {
        requestId: request.requestId,
        code: error.code,
        status: error.status,
        ...(error.details?.errors ? { errors: error.details.errors } : {}),
      }),
    );
  };

  const publish = (session, request, result) => {
    const sequence = rooms.nextSequence(session.campaignId);
    const event = eventFor(
      session,
      "token.updated",
      { token: result.token },
      sequence,
    );
    for (const recipient of rooms.sessions(session.campaignId)) {
      if (
        recipient.id === session.id ||
        result.publishToPlayers ||
        recipient.capabilities?.canViewHidden === true
      ) {
        sendEvent(recipient.ws, event);
      }
    }
    sendEvent(
      session.ws,
      eventFor(session, "token.ack", {
        requestId: request.requestId,
        tokenId: result.token.id,
        revision: result.token.revision,
      }),
    );
  };

  const handle = (session, request) => {
    const key = `${session.campaignId}:${request.tokenId}`;
    const previous = writes.get(key) || Promise.resolve();
    const current = previous
      .catch(() => {})
      .then(() => backend.move(session, request))
      .then((result) => publish(session, request, result));
    writes.set(key, current);
    const cleanup = () => {
      if (writes.get(key) === current) writes.delete(key);
    };
    current.then(cleanup, cleanup);
    current.catch((cause) => failure(session, request, cause));
  };

  return { handle };
};
