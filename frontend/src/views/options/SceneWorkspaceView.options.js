import UiConfirmDialog from "@/components/ui/UiConfirmDialog.vue";
import SceneCanvas from "@/components/vtt/scene/SceneCanvas.vue";
import SceneSettingsPanel from "@/components/vtt/scene/SceneSettingsPanel.vue";
import SceneToolbar from "@/components/vtt/scene/SceneToolbar.vue";
import TableFloatingWindow from "@/components/vtt/table/TableFloatingWindow.vue";
import TableHotbar from "@/components/vtt/table/TableHotbar.vue";
import TablePanelContent from "@/components/vtt/table/TablePanelContent.vue";
import TableToolRail from "@/components/vtt/table/TableToolRail.vue";
import TableUtilityDrawer from "@/components/vtt/table/TableUtilityDrawer.vue";
import TableUtilityRail from "@/components/vtt/table/TableUtilityRail.vue";
import TableWorkspaceHeader from "@/components/vtt/table/TableWorkspaceHeader.vue";
import { toggledSceneTool } from "@/components/vtt/table/tableSceneTools";
import {
  DEFAULT_TABLE_HOTBAR_ACTIONS,
  tableHotbarActions,
} from "@/components/vtt/table/tableHotbar";
import { tableWindowMethods } from "@/components/vtt/table/tableWindowMethods";
import { tableTokenMethods } from "@/components/vtt/token/tableTokenMethods";
import { tableWallMethods } from "@/components/vtt/wall/tableWallMethods";
import { tableLightMethods } from "@/components/vtt/light/tableLightMethods";
import { tableTileMethods } from "@/components/vtt/tile/tableTileMethods";
import {
  IMPLEMENTED_TABLE_UTILITIES,
  utilityById,
} from "@/components/vtt/table/tableUtilities";
import { ensureVttStoreModule } from "@/store/modules/loadVttModule";
import { sceneErrorMessage } from "./sceneErrorMessage";
import { emptySceneWorkspaceState } from "./sceneWorkspaceState";

export default {
  name: "SceneWorkspaceView",
  components: {
    SceneCanvas,
    SceneSettingsPanel,
    SceneToolbar,
    TableFloatingWindow,
    TableHotbar,
    TablePanelContent,
    TableToolRail,
    TableUtilityDrawer,
    TableUtilityRail,
    TableWorkspaceHeader,
    UiConfirmDialog,
  },
  data: () => ({
    moduleReady: false,
    settingsOpen: false,
    settingsMode: "edit",
    zoomPercent: 100,
    activeSceneTool: "select",
    activePanelId: "",
    panelWindows: [],
    nextWindowZ: 400,
    confirmDeleteOpen: false,
  }),
  computed: {
    state() {
      return this.$store.state.vtt || emptySceneWorkspaceState();
    },
    campaignContext() {
      return this.$store.state.campaignContext || {};
    },
    realtime() {
      return this.$store.state.realtime || { status: "disconnected" };
    },
    campaign() {
      return this.campaignContext.currentCampaign || {};
    },
    currentCampaignId() {
      const raw = this.$route.params.campaignId;
      const numeric = Number(raw);
      return Number.isFinite(numeric) ? numeric : raw;
    },
    members() {
      return this.$store.getters["campaignContext/membersWithPresence"] || [];
    },
    onlineMembers() {
      return this.members.filter((member) => member.isOnline);
    },
    characters() {
      return this.campaignContext.characters || [];
    },
    invitations() {
      return this.campaignContext.invitations || [];
    },
    scenes() {
      return this.$store.getters["vtt/sortedScenes"] || this.state.scenes;
    },
    selectedScene() {
      return this.$store.getters["vtt/selectedScene"] || null;
    },
    selectedSceneTokens() {
      return this.$store.getters["vtt/selectedSceneTokens"] || [];
    },
    selectedSceneWalls() {
      return this.$store.getters["vtt/selectedSceneWalls"] || [];
    },
    selectedSceneLights() {
      return this.$store.getters["vtt/selectedSceneLights"] || [];
    },
    selectedSceneTiles() {
      return this.$store.getters["vtt/selectedSceneTiles"] || [];
    },
    canCreateToken() {
      return this.$store.getters["vtt/canCreateToken"] === true;
    },
    canManageWalls() {
      return this.$store.getters["vtt/canManageWalls"] === true;
    },
    canManageLights() {
      return this.$store.getters["vtt/canManageLights"] === true;
    },
    canManageTiles() {
      return this.$store.getters["vtt/canManageTiles"] === true;
    },
    canManage() {
      return this.$store.getters["vtt/canManage"] === true;
    },
    canOpenShop() {
      return this.campaignContext.capabilities?.canOpenShop === true;
    },
    busy() {
      return ["loading", "saving"].includes(this.state.phase);
    },
    tokenBusy() {
      return ["loading", "saving"].includes(this.state.tokenPhase);
    },
    wallBusy() {
      return ["loading", "saving"].includes(this.state.wallPhase);
    },
    lightBusy() {
      return ["loading", "saving"].includes(this.state.lightPhase);
    },
    tileBusy() {
      return ["loading", "saving"].includes(this.state.tilePhase);
    },
    initialLoading() {
      return this.state.phase === "loading" && !this.state.scenes.length;
    },
    activeUtility() {
      return utilityById(this.activePanelId);
    },
    drawerOpen() {
      return this.settingsOpen || Boolean(this.activeUtility);
    },
    availableUtilityIds() {
      return IMPLEMENTED_TABLE_UTILITIES.filter(
        (id) => id !== "shop" || this.canOpenShop,
      );
    },
    hotbarStorageKey() {
      return `blatyrpg.table.${this.currentCampaignId}.hotbar`;
    },
    defaultHotbarActions() {
      return DEFAULT_TABLE_HOTBAR_ACTIONS;
    },
    hotbarActions() {
      return tableHotbarActions(this.$t, this.canManage);
    },
    errorMessage() {
      return sceneErrorMessage(this.$t, this.state.error);
    },
  },
  watch: {
    "$route.params.campaignId": "loadCampaign",
    "$route.hash": {
      immediate: true,
      handler(hash) {
        if (hash === "#campaign-chat") {
          this.settingsOpen = false;
          this.activePanelId = "chat";
        }
      },
    },
  },
  created() {
    this.loadCampaign();
  },
  methods: {
    ...tableWindowMethods,
    ...tableTokenMethods,
    ...tableWallMethods,
    ...tableLightMethods,
    ...tableTileMethods,
    async loadCampaign() {
      this.activePanelId = this.$route.hash === "#campaign-chat" ? "chat" : "";
      this.settingsOpen = false;
      await ensureVttStoreModule(this.$store);
      this.moduleReady = true;
      this.$store.commit("vtt/SET_CAMPAIGN", this.currentCampaignId);
      await this.$store.dispatch("vtt/initialize").catch(() => {});
    },
    selectUtility(id) {
      this.settingsOpen = false;
      const existing = this.panelWindows.find((item) => item.panelId === id);
      if (existing) {
        this.activePanelId = "";
        this.focusUtilityWindow(existing.id);
        return;
      }
      this.activePanelId = this.activePanelId === id ? "" : id;
    },
    selectScene(sceneId) {
      this.settingsOpen = false;
      this.$store.dispatch("vtt/selectScene", sceneId).catch(() => {});
    },
    selectRelativeScene(offset) {
      if (!this.scenes.length) return;
      const index = this.scenes.findIndex(
        (scene) => scene.id === this.state.selectedSceneId,
      );
      const next =
        (Math.max(0, index) + offset + this.scenes.length) % this.scenes.length;
      this.selectScene(this.scenes[next].id);
    },
    selectSceneTool(id) {
      if (id === "grid") {
        this.openEdit();
        return;
      }
      this.activeSceneTool = toggledSceneTool(this.activeSceneTool, id);
    },
    refresh() {
      this.$store.dispatch("vtt/initialize").catch(() => {});
    },
    refreshCampaignContext() {
      this.$store.dispatch("campaignContext/refresh").catch(() => {});
    },
    zoomOut() {
      this.$refs.canvas?.zoomBy(1 / 1.2);
    },
    zoomIn() {
      this.$refs.canvas?.zoomBy(1.2);
    },
    fitCanvas() {
      this.$refs.canvas?.fit();
    },
    openCreate() {
      this.activePanelId = "";
      this.settingsMode = "create";
      this.settingsOpen = true;
    },
    openEdit() {
      this.activePanelId = "";
      this.settingsMode = "edit";
      this.settingsOpen = true;
    },
    duplicateScene() {
      if (!this.selectedScene) return;
      const name = this.$t("vtt.scene.actions.copyName", {
        name: this.selectedScene.name,
      });
      this.$store.dispatch("vtt/duplicateSelectedScene", name).catch(() => {});
    },
    async saveSettings(payload) {
      const action =
        this.settingsMode === "create"
          ? "vtt/createScene"
          : "vtt/updateSelectedScene";
      try {
        await this.$store.dispatch(action, payload);
        this.settingsOpen = false;
      } catch (_error) {
        // Store exposes the API error in a visible workspace notice.
      }
    },
    requestDelete() {
      this.confirmDeleteOpen = true;
    },
    async deleteScene() {
      try {
        await this.$store.dispatch("vtt/deleteSelectedScene");
        this.settingsOpen = false;
        this.confirmDeleteOpen = false;
      } catch (_error) {
        // Store exposes the API error in a visible workspace notice.
      }
    },
    activate() {
      this.$store.dispatch("vtt/activateSelectedScene").catch(() => {});
    },
    runHotbarAction(id) {
      const actions = {
        "zoom-out": this.zoomOut,
        fit: this.fitCanvas,
        "zoom-in": this.zoomIn,
        refresh: this.refresh,
        chat: () => this.selectUtility("chat"),
        characters: () => this.selectUtility("characters"),
        scenes: () => this.selectUtility("scenes"),
        settings: this.openEdit,
      };
      actions[id]?.();
    },
  },
};
