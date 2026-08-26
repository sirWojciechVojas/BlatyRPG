import { emptyCombat } from "@/lib/vtt/combatNormalizer";
import { sceneErrorMessage } from "./sceneErrorMessage";

export const sceneWorkspaceCombat = {
  computed: {
    errorMessage() {
      return sceneErrorMessage(this.$t, this.state.error);
    },
    isMovementNotice() {
      return ["movement_points_depleted", "movement_group_blocked"].includes(
        this.state.error?.code,
      );
    },
    movementNoticeTitle() {
      const key =
        this.state.error?.code === "movement_group_blocked"
          ? "movementGroupBlockedTitle"
          : "movementPointsDepletedTitle";
      return this.$t(`vtt.scene.errors.${key}`);
    },
    selectedSceneCombat() {
      return (
        this.$store.getters["vtt/selectedSceneCombat"] ||
        emptyCombat(this.state.selectedSceneId)
      );
    },
    canManageCombat() {
      return this.$store.getters["vtt/canManageCombat"] === true;
    },
    activeCombatTokenId() {
      return this.selectedSceneCombat.active
        ? this.selectedSceneCombat.activeTokenId
        : null;
    },
    waitingCombatTokenIds() {
      if (!this.selectedSceneCombat.active) return [];
      return this.selectedSceneCombat.combatants
        .map(({ tokenId }) => tokenId)
        .filter((id) => id !== this.activeCombatTokenId);
    },
    combatBusy() {
      return ["loading", "saving"].includes(this.state.combatPhase);
    },
    tablePanelContext() {
      return {
        campaignId: this.currentCampaignId,
        campaign: this.campaign,
        scenes: this.scenes,
        selectedId: this.state.selectedSceneId,
        activeId: this.state.activeSceneId,
        characters: this.characters,
        members: this.members,
        invitations: this.invitations,
        movementRequests: this.state.movementRequests,
        realtimeStatus: this.realtime.status,
        canManage: this.canManage,
        canOpenShop: this.canOpenShop,
        canCreateToken: this.canCreateToken,
        canResolveMovement: this.state.movementRequestCapabilities.canResolve,
        movementRequestBusy: this.state.movementRequestPhase === "saving",
        characterId: this.focusedCharacterId,
        busy: this.busy,
        tokens: this.selectedSceneTokens,
        combat: this.selectedSceneCombat,
        combatError: this.state.combatError,
        canManageCombat: this.canManageCombat,
        combatBusy: this.combatBusy,
      };
    },
  },
  methods: {
    commandCombat(command) {
      const sent = this.$store.dispatch("realtime/commandCombat", {
        sceneId: this.state.selectedSceneId,
        command,
      });
      Promise.resolve(sent).then((accepted) => {
        if (!accepted) {
          this.$store.commit("vtt/COMBAT_FAILED", {
            code: "realtime_unavailable",
            status: 0,
          });
        }
      });
    },
  },
};
