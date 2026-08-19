import CampaignSettingsPanel from "@/components/campaign/CampaignSettingsPanel.vue";
import CampaignMembersPanel from "@/components/campaign/CampaignMembersPanel.vue";
import CharacterAssignmentsPanel from "@/components/campaign/CharacterAssignmentsPanel.vue";
import OnlinePresencePanel from "@/components/campaign/OnlinePresencePanel.vue";
import { ensureCampaignRouteSession } from "@/router/campaignSessionGuard";
import { gameCatalogApiClient } from "@/lib/catalog/gameCatalogApiClient";

export default {
  name: "CampaignLobbyView",
  components: {
    CampaignSettingsPanel,
    CampaignMembersPanel,
    CharacterAssignmentsPanel,
    OnlinePresencePanel,
  },
  data: () => ({
    localError: "",
    initialized: false,
    games: [],
  }),
  computed: {
    context() {
      return this.$store.state.campaignContext || {};
    },
    realtime() {
      return this.$store.state.realtime || {};
    },
    campaign() {
      return this.context.currentCampaign;
    },
    canManage() {
      return this.context.capabilities?.canManage === true;
    },
    members() {
      return this.$store.getters["campaignContext/membersWithPresence"] || [];
    },
    permissions() {
      const entries = Object.values(this.context.characterPermissions || {});
      return entries.at(-1) || [];
    },
    busy() {
      return Boolean(this.context.pendingRequests);
    },
  },
  async mounted() {
    await this.activateCampaign(this.$route);
  },
  async beforeRouteUpdate(to) {
    await this.activateCampaign(to);
  },
  methods: {
    async activateCampaign(route) {
      this.initialized = false;
      this.localError = "";
      try {
        await ensureCampaignRouteSession(this.$store, route);
        if (!this.games.length) {
          this.games = await gameCatalogApiClient.listGames();
        }
        this.initialized = true;
      } catch (error) {
        this.localError = this.message(error);
      }
    },
    message(error) {
      if (error?.network) return this.$t("campaignLobby.errors.network");
      if (error?.status === 403)
        return this.$t("campaignLobby.errors.forbidden");
      if (error?.status === 429)
        return this.$t("campaignLobby.errors.rateLimited");
      return this.$t("campaignLobby.errors.generic");
    },
    async run(action, payload) {
      this.localError = "";
      try {
        return await this.$store.dispatch(`campaignContext/${action}`, payload);
      } catch (error) {
        this.localError = this.message(error);
        return null;
      }
    },
    updateSettings(value) {
      return this.run("updateSettings", value);
    },
    invite(value) {
      return this.run("invite", value);
    },
    changeRole(value) {
      return this.run("changeMemberRole", value);
    },
    removeMember(value) {
      return this.run("removeMember", value);
    },
    revokeInvitation(value) {
      return this.run("revokeInvitation", value);
    },
    loadPermissions(value) {
      return this.run("loadCharacterPermissions", value);
    },
    setAccess(value) {
      return this.run("setCharacterAccess", value);
    },
    assignOwner(value) {
      return this.run("assignCharacterOwner", value);
    },
    setVisibility(value) {
      return this.run("updateCharacterVisibility", value);
    },
  },
};
