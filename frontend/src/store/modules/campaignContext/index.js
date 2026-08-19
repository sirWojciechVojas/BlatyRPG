import { campaignApiClient } from "@/lib/campaign/campaignApiClient";
import { createCampaignContextActions } from "./actions";
import { campaignContextGetters } from "./getters";
import { campaignContextMutations } from "./mutations";
import { createCampaignContextState } from "./state";

export const createCampaignContextModule = (api = campaignApiClient) => ({
  namespaced: true,
  state: createCampaignContextState,
  getters: campaignContextGetters,
  mutations: campaignContextMutations,
  actions: createCampaignContextActions(api),
});

export default createCampaignContextModule();
