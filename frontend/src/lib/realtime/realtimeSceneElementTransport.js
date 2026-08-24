import { sceneElementChangeMessage } from "./realtimeProtocol";

export const sceneElementTransport = (isAuthenticated, send) => ({
  changeWall: (payload) =>
    isAuthenticated() && send(sceneElementChangeMessage("wall", payload)),
  changeLight: (payload) =>
    isAuthenticated() && send(sceneElementChangeMessage("light", payload)),
  changeTile: (payload) =>
    isAuthenticated() && send(sceneElementChangeMessage("tile", payload)),
});
