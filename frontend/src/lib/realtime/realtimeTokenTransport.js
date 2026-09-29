import {
  tokenChangeMessage,
  tokenMoveGroupMessage,
  tokenMoveMessage,
  tokenMovementRequestMessage,
  tokenMovementResolveMessage,
} from "./realtimeProtocol";

export const createRealtimeTokenTransport = (authenticated, send) => ({
  changeToken: (payload) =>
    authenticated() && send(tokenChangeMessage(payload)),
  moveToken: (payload) => authenticated() && send(tokenMoveMessage(payload)),
  moveTokenGroup: (payload) =>
    authenticated() && send(tokenMoveGroupMessage(payload)),
  requestTokenMovement: (payload) =>
    authenticated() && send(tokenMovementRequestMessage(payload)),
  resolveTokenMovement: (payload) =>
    authenticated() && send(tokenMovementResolveMessage(payload)),
});
