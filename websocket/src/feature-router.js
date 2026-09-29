export const routeRealtimeFeature = (handlers, session, message) => {
  if (message.type === "wall.audio.sync") {
    handlers.walls.syncAudio(session, message);
    return true;
  }
  if (message.type.startsWith("SOUND_EFFECT_")) {
    handlers.soundEffects.handle(session, message);
    return true;
  }
  if (message.type.startsWith("JUKEBOX_")) {
    handlers.jukebox.handle(session, message);
    return true;
  }
  if (message.type === "fog.sync") {
    handlers.fog.handle(session, message);
    return true;
  }
  if (message.type === "chat.send" || message.type === "chat.sync") {
    handlers.chat.handle(session, message);
    return true;
  }
  if (message.type === "handout.notify") {
    handlers.handouts.handle(session, message);
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
  if (message.type === "token.sync.command") {
    handlers.tokenSync.handle(session, message);
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
  if (message.type === "region.change") {
    handlers.regions.handle(session, message);
    return true;
  }
  if (message.type === "tile.change") {
    handlers.tiles.handle(session, message);
    return true;
  }
  return false;
};
