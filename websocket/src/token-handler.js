import { BackendTokenError } from "./backend-token-client.js";
import { createServerEvent, sendEvent } from "./protocol.js";
import { canReceiveToken } from "./token-visibility.js";

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

  const movementFailure = (session, request, cause) => {
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
      eventFor(session, "token.movement.error", {
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
    const marker = createServerEvent({
      type: "sync.marker",
      campaignId: session.campaignId,
      sequence,
      actorUserId: null,
      payload: {},
    });
    for (const recipient of rooms.sessions(session.campaignId)) {
      if (
        recipient.id === session.id ||
        canReceiveToken(recipient, result)
      ) {
        sendEvent(recipient.ws, event);
      } else {
        sendEvent(recipient.ws, marker);
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

  const publishGroup = (session, request, result) => {
    const sequence = rooms.nextSequence(session.campaignId);
    const marker = createServerEvent({
      type: "sync.marker",
      campaignId: session.campaignId,
      sequence,
      actorUserId: null,
      payload: {},
    });
    for (const recipient of rooms.sessions(session.campaignId)) {
      const visible = result.items.filter(
        (item) => recipient.id === session.id || canReceiveToken(recipient, item),
      );
      sendEvent(
        recipient.ws,
        visible.length
          ? eventFor(
              session,
              "token.group.updated",
              { tokens: visible.map(({ token }) => token) },
              sequence,
            )
          : marker,
      );
    }
    sendEvent(
      session.ws,
      eventFor(session, "token.group.ack", {
        requestId: request.requestId,
        tokenIds: result.items.map(({ token }) => token.id),
      }),
    );
  };

  const enqueue = (keys, task) => {
    const previous = Promise.all(
      keys.map((key) => writes.get(key)?.catch(() => {}) || Promise.resolve()),
    );
    const current = previous.then(task);
    keys.forEach((key) => writes.set(key, current));
    const cleanup = () => {
      keys.forEach((key) => {
        if (writes.get(key) === current) writes.delete(key);
      });
    };
    current.then(cleanup, cleanup);
    return current;
  };

  const handle = (session, request) => {
    const key = `${session.campaignId}:${request.tokenId}`;
    enqueue([key], () =>
      request.type === "token.change"
        ? backend.change(session, request)
        : backend.move(session, request),
    )
      .then((result) => publish(session, request, result))
      .catch((cause) => failure(session, request, cause));
  };

  const handleGroup = (session, request) => {
    const keys = request.moves.map(
      ({ tokenId }) => `${session.campaignId}:${tokenId}`,
    );
    enqueue(keys, () => backend.moveGroup(session, request))
      .then((result) => publishGroup(session, request, result))
      .catch((cause) => failure(session, request, cause));
  };

  const publishMovement = (session, type, payload, allowed) => {
    const sequence = rooms.nextSequence(session.campaignId);
    const event = eventFor(session, type, payload, sequence);
    const marker = createServerEvent({
      type: "sync.marker",
      campaignId: session.campaignId,
      sequence,
      actorUserId: null,
      payload: {},
    });
    for (const recipient of rooms.sessions(session.campaignId)) {
      sendEvent(recipient.ws, allowed(recipient) ? event : marker);
    }
  };

  const requestMovement = (session, request) => {
    backend
      .requestMovement(session, request)
      .then((result) => {
        publishMovement(
          session,
          "token.movement.requested",
          { request: result.request },
          (recipient) =>
            recipient.userId === result.request.requestedByUserId ||
            recipient.campaignRole === "gm",
        );
        sendEvent(session.ws, eventFor(session, "token.movement.ack", {
          requestId: request.requestId,
          movementRequestId: result.request.id,
        }));
      })
      .catch((cause) => movementFailure(session, request, cause));
  };

  const resolveMovement = (session, request) => {
    backend
      .resolveMovement(session, request)
      .then((result) => {
        const tokenPatch = result.token
          ? {
              id: result.token.id,
              sceneId: result.token.sceneId,
              x: result.token.x,
              y: result.token.y,
              movementRange: result.token.movementRange,
              movementSpent: result.token.movementSpent,
              movementPoints: result.token.movementPoints,
              resources: result.token.resources,
              revision: result.token.revision,
            }
          : null;
        publishMovement(
          session,
          "token.movement.resolved",
          { request: result.request, ...(tokenPatch ? { tokenPatch } : {}) },
          (recipient) =>
            recipient.userId === result.request.requestedByUserId ||
            recipient.campaignRole === "gm" ||
            (result.token && canReceiveToken(recipient, result)),
        );
        sendEvent(session.ws, eventFor(session, "token.movement.ack", {
          requestId: request.requestId,
          movementRequestId: result.request.id,
        }));
      })
      .catch((cause) => movementFailure(session, request, cause));
  };

  return { handle, handleGroup, requestMovement, resolveMovement };
};
