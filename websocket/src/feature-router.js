export const routeRealtimeFeature = (handlers, session, message) => {
  if (message.type === "chat.send" || message.type === "chat.sync") {
    handlers.chat.handle(session, message);
    return true;
  }
  if (
    ["token.move", "token.move.group", "token.change"].includes(message.type)
  ) {
    if (message.type === "token.move.group") {
      handlers.tokens.handleGroup(session, message);
    } else {
      handlers.tokens.handle(session, message);
    }
    return true;
  }
  if (message.type === "token.movement.request") {
    handlers.tokens.requestMovement(session, message);
    return true;
  }
  if (message.type === "token.movement.resolve") {
    handlers.tokens.resolveMovement(session, message);
    return true;
  }
  if (message.type === "combat.command") {
    handlers.combat.handle(session, message);
    return true;
  }
  if (message.type === "wall.change") {
    handlers.walls.handle(session, message);
    return true;
  }
  if (message.type === "light.change") {
    handlers.lights.handle(session, message);
    return true;
  }
  if (message.type === "tile.change") {
    handlers.tiles.handle(session, message);
    return true;
  }
  return false;
};
