import { sceneApiClient } from "@/lib/vtt/sceneApiClient";
import { tokenApiClient } from "@/lib/vtt/tokenApiClient";
import { wallApiClient } from "@/lib/vtt/wallApiClient";
import { createVttActions } from "./actions";
import { vttGetters } from "./getters";
import { vttMutations } from "./mutations";
import { createVttState } from "./state";

export const createVttModule = (
  api = sceneApiClient,
  tokens = api === sceneApiClient ? tokenApiClient : null,
  walls = api === sceneApiClient ? wallApiClient : null,
) => ({
  namespaced: true,
  state: createVttState,
  getters: vttGetters,
  mutations: vttMutations,
  actions: createVttActions(api, tokens, walls),
});

export default createVttModule();
