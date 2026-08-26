import { sceneApiClient } from "@/lib/vtt/sceneApiClient";
import { tokenApiClient } from "@/lib/vtt/tokenApiClient";
import { wallApiClient } from "@/lib/vtt/wallApiClient";
import { lightApiClient } from "@/lib/vtt/lightApiClient";
import { tileApiClient } from "@/lib/vtt/tileApiClient";
import { tokenMovementRequestApiClient } from "@/lib/vtt/tokenMovementRequestApiClient";
import { combatApiClient } from "@/lib/vtt/combatApiClient";
import { createCombatActions } from "./combatActions";
import { createVttActions } from "./actions";
import { vttGetters } from "./getters";
import { vttMutations } from "./mutations";
import { createVttState } from "./state";

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
) => ({
  namespaced: true,
  state: createVttState,
  getters: vttGetters,
  mutations: vttMutations,
  actions: {
    ...createVttActions(api, tokens, walls, lights, tiles, movementRequests),
    ...createCombatActions(combats),
  },
});

export default createVttModule();
