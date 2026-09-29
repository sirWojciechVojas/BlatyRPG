import { createServerEvent, sendEvent } from "./protocol.js";

export const createFogHandler = ({ rooms }) => ({
  handle(session, request) {
    const manager = session.capabilities?.canManage === true;
    if (
      Number(request.userId) !== Number(session.userId) &&
      !manager &&
      request.shared !== true
    ) {
      sendEvent(
        session.ws,
        createServerEvent({
          type: "fog.error",
          campaignId: session.campaignId,
          sequence: rooms.currentSequence(session.campaignId),
          actorUserId: session.userId,
          payload: {
            requestId: request.requestId,
            code: "forbidden",
            status: 403,
          },
        }),
      );
      return;
    }
    const sequence = rooms.nextSequence(session.campaignId);
    const event = createServerEvent({
      type: "fog.updated",
      campaignId: session.campaignId,
      sequence,
      actorUserId: session.userId,
      payload: {
        sceneId: request.sceneId,
        userId: request.userId,
        revision: request.revision,
      },
    });
    const marker = createServerEvent({
      type: "sync.marker",
      campaignId: session.campaignId,
      sequence,
      actorUserId: null,
      payload: {},
    });
    for (const recipient of rooms.sessions(session.campaignId)) {
      const allowed =
        request.shared === true ||
        Number(recipient.userId) === Number(request.userId) ||
        recipient.capabilities?.canManage === true;
      sendEvent(recipient.ws, allowed ? event : marker);
    }
  },
});
