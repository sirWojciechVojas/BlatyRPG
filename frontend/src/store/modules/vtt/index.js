import { sceneApiClient } from "@/lib/vtt/sceneApiClient";
import { tokenApiClient } from "@/lib/vtt/tokenApiClient";
import { wallApiClient } from "@/lib/vtt/wallApiClient";
import { lightApiClient } from "@/lib/vtt/lightApiClient";
import { tileApiClient } from "@/lib/vtt/tileApiClient";
import { tokenMovementRequestApiClient } from "@/lib/vtt/tokenMovementRequestApiClient";
import { combatApiClient } from "@/lib/vtt/combatApiClient";
import { fogApiClient } from "@/lib/vtt/fogApiClient";
import { regionApiClient } from "@/lib/vtt/regionApiClient";
import { tokenSyncApiClient } from "@/lib/vtt/tokenSyncApiClient";
import { createCombatActions } from "./combatActions";
import { createVttActions } from "./actions";
import { vttGetters } from "./getters";
import { vttMutations } from "./mutations";
import { createVttState } from "./state";

const SCENE_LIGHTING_FIELDS = Object.freeze([
  "globalLightLevel",
  "darknessLevel",
  "globalIllumination",
  "globalIlluminationThreshold",
  "fogExploration",
  "fogEnabled",
  "dynamicVision",
  "explorationMemory",
  "fogUnexploredColor",
  "fogExploredColor",
  "fogExplorationImage",
  "fogExplorationMode",
  "fogUnexploredOpacity",
  "fogExploredOpacity",
  "fogEdgeSoftness",
  "fogUpdateDuringDrag",
]);

const sceneLightingChanged = (scene, changes = {}) =>
  SCENE_LIGHTING_FIELDS.some(
    (field) =>
      Object.prototype.hasOwnProperty.call(changes, field) &&
      changes[field] !== scene?.[field],
  );

export const createVttModule = (
  api = sceneApiClient,
  tokens = api === sceneApiClient ? tokenApiClient : null,
  walls = api === sceneApiClient ? wallApiClient : null,
  lights = api === sceneApiClient ? lightApiClient : null,
  tiles = api === sceneApiClient ? tileApiClient : null,
  movementRequests = api === sceneApiClient
    ? tokenMovementRequestApiClient
    : null,
  combats = api === sceneApiClient ? combatApiClient : null,
  fog = api === sceneApiClient ? fogApiClient : null,
  regions = api === sceneApiClient ? regionApiClient : null,
  tokenSync = api === sceneApiClient ? tokenSyncApiClient : null,
) => {
  const actions = {
    ...createVttActions(
      api,
      tokens,
      walls,
      lights,
      tiles,
      movementRequests,
      fog,
      regions,
      tokenSync,
    ),
    ...createCombatActions(combats),
  };
  const updateScene = actions.updateScene;
  actions.updateScene = async (context, payload = {}) => {
    const previousScene = context.state.scenes.find(
      (item) => Number(item.id) === Number(payload.sceneId),
    );
    const shouldSyncLighting = sceneLightingChanged(
      previousScene,
      payload.changes,
    );
    const scene = await updateScene(context, payload);
    if (scene && shouldSyncLighting) {
      // Persistence already succeeded; realtime publication is best-effort.
      Promise.resolve(
        context.dispatch("realtime/syncSceneLighting", scene, {
          root: true,
        }),
      ).catch(() => {});
    }
    return scene;
  };
  return {
    namespaced: true,
    state: createVttState,
    getters: vttGetters,
    mutations: vttMutations,
    actions,
  };
};

export default createVttModule();
