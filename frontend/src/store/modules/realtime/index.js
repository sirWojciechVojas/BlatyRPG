import { createRealtimeSession } from "@/lib/realtime/realtimeSession";
import { createRealtimeActions } from "./actions";
import { realtimeGetters } from "./getters";
import { realtimeMutations } from "./mutations";
import { createRealtimeState } from "./state";

export const createRealtimeModule = (options = {}) => ({
  namespaced: true,
  state: createRealtimeState,
  getters: realtimeGetters,
  mutations: realtimeMutations,
  actions: createRealtimeActions(
    options.sessionFactory || createRealtimeSession,
    options.restore,
  ),
});

export default createRealtimeModule();
