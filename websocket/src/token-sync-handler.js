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

export const createTokenSyncHandler = ({
  backend,
  rooms,
  onAuthenticationFailure,
}) => {
  const failure = (session, request, cause) => {
    const error =
      cause instanceof BackendTokenError
        ? cause
        : new BackendTokenError("token_sync_unavailable", 503);
    if (error.status === 401) {
      onAuthenticationFailure(session);
      return;
    }
    sendEvent(
      session.ws,
      eventFor(session, "token.sync.error", {
        requestId: request.requestId,
        code: error.code,
        status: error.status,
        ...(error.details?.errors ? { errors: error.details.errors } : {}),
      }),
    );
  };

  const publishToken = (session, result) => {
    const sequence = rooms.nextSequence(session.campaignId);
    const affectsVisibility = ["hidden", "visibleTo", "vision"].some((field) =>
      result.changedFields?.includes(field),
    );
    const event = eventFor(
      session,
      "token.updated",
      { token: result.token, visibilityChanged: affectsVisibility },
      sequence,
    );
    const visibility = eventFor(
      session,
      "scene.visibility.changed",
      { sceneId: result.token.sceneId },
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
      if (recipient.id === session.id || recipient.capabilities?.canManage === true) {
        sendEvent(recipient.ws, event);
      } else if (affectsVisibility && recipient.capabilities?.fogOfWar === true) {
        sendEvent(recipient.ws, visibility);
      } else if (canReceiveToken(recipient, result)) {
        sendEvent(recipient.ws, event);
      } else sendEvent(recipient.ws, marker);
    }
  };

  const publishMetadata = (session, request, result) => {
    const sequence = rooms.nextSequence(session.campaignId);
    const event = eventFor(
      session,
      "token.sync.changed",
      {
        action: request.action,
        tokenIds: result.synchronizedTokens.map(({ token }) => token.id),
      },
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
      sendEvent(
        recipient.ws,
        recipient.capabilities?.canManageTokenSync === true ? event : marker,
      );
    }
  };

  const handle = (session, request) => {
    backend
      .command(session, request)
      .then((result) => {
        result.synchronizedTokens.forEach((item) => publishToken(session, item));
        publishMetadata(session, request, result);
        sendEvent(
          session.ws,
          eventFor(session, "token.sync.ack", {
            requestId: request.requestId,
            action: request.action,
            synchronizedTokenIds: result.synchronizedTokens.map(
              ({ token }) => token.id,
            ),
          }),
        );
      })
      .catch((cause) => failure(session, request, cause));
  };

  return { handle };
};
