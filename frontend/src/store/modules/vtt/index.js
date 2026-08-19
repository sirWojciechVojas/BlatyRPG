import { sceneApiClient } from "@/lib/vtt/sceneApiClient";
import { createVttActions } from "./actions";
import { vttGetters } from "./getters";
import { vttMutations } from "./mutations";
import { createVttState } from "./state";

export const createVttModule = (api = sceneApiClient) => ({
  namespaced: true,
  state: createVttState,
  getters: vttGetters,
  mutations: vttMutations,
  actions: createVttActions(api),
});

export default createVttModule();
