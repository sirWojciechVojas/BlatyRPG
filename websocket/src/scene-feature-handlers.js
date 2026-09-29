import { BackendLightClient } from "./backend-light-client.js";
import { BackendWallClient } from "./backend-wall-client.js";
import { BackendTileClient } from "./backend-tile-client.js";
import { BackendCombatClient } from "./backend-combat-client.js";
import { createLightHandler } from "./light-handler.js";
import { createWallHandler } from "./wall-handler.js";
import { createTileHandler } from "./tile-handler.js";
import { createCombatHandler } from "./combat-handler.js";
import { BackendRegionClient } from "./backend-region-client.js";
import { createRegionHandler } from "./region-handler.js";

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
  regions: createRegionHandler({
    backend: dependencies.regionBackend || new BackendRegionClient(config),
    rooms,
    onAuthenticationFailure,
  }),
  tiles: createTileHandler({
    backend: dependencies.tileBackend || new BackendTileClient(config),
    rooms,
    onAuthenticationFailure,
  }),
  combat: createCombatHandler({
    backend: dependencies.combatBackend || new BackendCombatClient(config),
    rooms,
    onAuthenticationFailure,
  }),
});
