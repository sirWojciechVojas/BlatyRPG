import { BackendCombatError } from "./backend-combat-client.js";
import { createServerEvent, sendEvent } from "./protocol.js";

export const createCombatHandler = ({ backend, rooms, onAuthenticationFailure }) => {
  const handle = (session, request) => {
    backend
      .command(session, request)
      .then((result) => {
        const sequence = rooms.nextSequence(session.campaignId);
        const event = createServerEvent({
          type: "combat.updated",
          campaignId: session.campaignId,
          sequence,
          actorUserId: session.userId,
          payload: {
            sceneId: request.sceneId,
            action: result.action,
            movementChanged: result.movementChanged,
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
          const privileged =
            recipient.capabilities?.canManage === true ||
            recipient.capabilities?.canViewHidden === true;
          sendEvent(
            recipient.ws,
            result.publishToPlayers || privileged ? event : marker,
          );
        }
        sendEvent(
          session.ws,
          createServerEvent({
            type: "combat.ack",
            campaignId: session.campaignId,
            sequence,
            actorUserId: session.userId,
            payload: { requestId: request.requestId },
          }),
        );
      })
      .catch((cause) => {
        const error =
          cause instanceof BackendCombatError
            ? cause
            : new BackendCombatError("combat_unavailable", 503);
        if (error.status === 401) {
          onAuthenticationFailure(session);
          return;
        }
        sendEvent(
          session.ws,
          createServerEvent({
            type: "combat.error",
            campaignId: session.campaignId,
            sequence: null,
            actorUserId: session.userId,
            payload: {
              requestId: request.requestId,
              code: error.code,
              status: error.status,
              ...(error.details.errors ? { errors: error.details.errors } : {}),
            },
          }),
        );
      });
  };
  return { handle };
};
