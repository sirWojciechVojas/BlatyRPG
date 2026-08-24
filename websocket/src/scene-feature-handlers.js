import { BackendLightClient } from "./backend-light-client.js";
import { BackendWallClient } from "./backend-wall-client.js";
import { createLightHandler } from "./light-handler.js";
import { createWallHandler } from "./wall-handler.js";

export const createSceneFeatureHandlers = (
  config,
  dependencies,
  rooms,
  onAuthenticationFailure,
) => ({
  walls: createWallHandler({
    backend: dependencies.wallBackend || new BackendWallClient(config),
    rooms,
    onAuthenticationFailure,
  }),
  lights: createLightHandler({
    backend: dependencies.lightBackend || new BackendLightClient(config),
    rooms,
    onAuthenticationFailure,
  }),
});
