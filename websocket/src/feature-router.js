export const routeRealtimeFeature = (handlers, session, message) => {
  if (message.type === "chat.send" || message.type === "chat.sync") {
    handlers.chat.handle(session, message);
    return true;
  }
  if (message.type === "token.move") {
    handlers.tokens.handle(session, message);
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
