import { BackendHandoutError } from "./backend-handout-client.js";
import { createServerEvent, sendEvent } from "./protocol.js";

export const createHandoutHandler = ({ backend, rooms, onAuthenticationFailure }) => {
  const failure = (session, request, cause) => {
    const error =
      cause instanceof BackendHandoutError
        ? cause
        : new BackendHandoutError("handout_unavailable", 503);
    if (error.status === 401) {
      onAuthenticationFailure(session);
      return;
    }
    sendEvent(
      session.ws,
      createServerEvent({
        type: "handout.error",
        campaignId: session.campaignId,
        actorUserId: session.userId,
        payload: { requestId: request.requestId, code: error.code, status: error.status },
      }),
    );
  };

  const handle = (session, request) => {
    backend
      .deliveryTargets(session, request.batchId)
      .then((targets) => {
        const byUserId = new Map(targets.map((target) => [target.userId, target]));
        for (const recipient of rooms.sessions(session.campaignId)) {
          const target = byUserId.get(recipient.userId);
          if (!target) continue;
          sendEvent(
            recipient.ws,
            createServerEvent({
              type: "handout.available",
              campaignId: session.campaignId,
              actorUserId: session.userId,
              payload: {
                notificationId: target.notificationId,
                handoutId: target.handoutId,
                title: target.title,
              },
            }),
          );
        }
        sendEvent(
          session.ws,
          createServerEvent({
            type: "handout.ack",
            campaignId: session.campaignId,
            actorUserId: session.userId,
            payload: { requestId: request.requestId, delivered: targets.length },
          }),
        );
      })
      .catch((cause) => failure(session, request, cause));
  };

  return { handle };
};
