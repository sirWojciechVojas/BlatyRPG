import { BackendWallError } from "./backend-wall-client.js";
import { createServerEvent, sendEvent } from "./protocol.js";

const eventFor = (session, type, payload, sequence = null) =>
  createServerEvent({
    type,
    campaignId: session.campaignId,
    sequence,
    actorUserId: session.userId,
    payload,
  });

export const createWallHandler = ({ backend, rooms, onAuthenticationFailure }) => {
  const writes = new Map();

  const failure = (session, request, cause) => {
    const error =
      cause instanceof BackendWallError
        ? cause
        : new BackendWallError("wall_unavailable", 503);
    if (error.status === 401) {
      onAuthenticationFailure(session);
      return;
    }
    sendEvent(
      session.ws,
      eventFor(session, "wall.error", {
        requestId: request.requestId,
        code: error.code,
        status: error.status,
        ...(error.details?.errors ? { errors: error.details.errors } : {}),
      }),
    );
  };

  const publish = (session, request, result) => {
    const sequence = rooms.nextSequence(session.campaignId);
    const type = result.operation === "delete" ? "wall.deleted" : "wall.updated";
    const payload = result.wall
      ? { wall: result.wall }
      : { sceneId: result.sceneId, wallId: result.wallId };
    const event = eventFor(session, type, payload, sequence);
    const marker = createServerEvent({
      type: "sync.marker",
      campaignId: session.campaignId,
      sequence,
      actorUserId: null,
      payload: {},
    });
    for (const recipient of rooms.sessions(session.campaignId)) {
      const canManage = recipient.id === session.id
        || recipient.capabilities?.canManage === true
        || recipient.capabilities?.canViewHidden === true;
      sendEvent(recipient.ws, canManage ? event : marker);
    }
    sendEvent(
      session.ws,
      eventFor(session, "wall.ack", {
        requestId: request.requestId,
        operation: result.operation,
        wallId: result.wall?.id ?? result.wallId,
        revision: result.wall?.revision ?? null,
      }),
    );
  };

  const handle = (session, request) => {
    const key = `${session.campaignId}:${request.sceneId}:${request.wallId || request.requestId}`;
    const previous = writes.get(key) || Promise.resolve();
    const current = previous
      .catch(() => {})
      .then(() => backend.change(session, request))
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
