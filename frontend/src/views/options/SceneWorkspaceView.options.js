import { markRaw, nextTick } from "vue";
import { Modal } from "bootstrap";
import UiConfirmDialog from "@/components/ui/UiConfirmDialog.vue";
import PlayerCharacterStatsModal from "@/components/characters/PlayerCharacterStatsModal.vue";
import SceneCanvas from "@/components/vtt/scene/SceneCanvas.vue";
import SceneSettingsPanel from "@/components/vtt/scene/SceneSettingsPanel.vue";
import SceneToolbar from "@/components/vtt/scene/SceneToolbar.vue";
import PlayerCharacterHud from "@/components/vtt/table/PlayerCharacterHud.vue";
import TableFloatingWindow from "@/components/vtt/table/TableFloatingWindow.vue";
import TablePanelContent from "@/components/vtt/table/TablePanelContent.vue";
import TableToolRail from "@/components/vtt/table/TableToolRail.vue";
import TableUtilityDrawer from "@/components/vtt/table/TableUtilityDrawer.vue";
import TableUtilityRail from "@/components/vtt/table/TableUtilityRail.vue";
import TableWorkspaceHeader from "@/components/vtt/table/TableWorkspaceHeader.vue";
import { toggledSceneTool } from "@/components/vtt/table/tableSceneTools";
import { tableWindowMethods } from "@/components/vtt/table/tableWindowMethods";
import { tableTokenMethods } from "@/components/vtt/token/tableTokenMethods";
import { tableWallMethods } from "@/components/vtt/wall/tableWallMethods";
import { tableLightMethods } from "@/components/vtt/light/tableLightMethods";
import { tableTileMethods } from "@/components/vtt/tile/tableTileMethods";
import { shopApiClient } from "@/lib/trade/shopApiClient";
import { setShopAccessSession } from "@/lib/trade/shopAccessSession";
import { ensureShopStoreModule } from "@/store/modules/loadShopModule";
import { shopOwnerCodeForCharacter } from "@/components/vtt/table/playerCharacterHudModel";
import { authSession } from "@/lib/auth/authSession";
import { handoutApiClient } from "@/lib/handouts/handoutApiClient";
import {
  IMPLEMENTED_TABLE_UTILITIES,
  utilityById,
} from "@/components/vtt/table/tableUtilities";
import { ensureVttStoreModule } from "@/store/modules/loadVttModule";
import { emptySceneWorkspaceState } from "./sceneWorkspaceState";
import { sceneWorkspaceCombat } from "./sceneWorkspaceCombat";

export default {
  name: "SceneWorkspaceView",
  components: {
    SceneCanvas,
    SceneSettingsPanel,
    SceneToolbar,
    PlayerCharacterHud,
    PlayerCharacterStatsModal,
    TableFloatingWindow,
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
    sceneViewCenter: null,
    activeSceneTool: "select",
    activePanelId: "",
    panelWindows: [],
    nextWindowZ: 400,
    confirmDeleteOpen: false,
    focusedCharacterId: null,
    hudCharacterId: null,
    playerCharacterStatsId: null,
    playerShopMounted: false,
    playerShopComponent: null,
    playerShopOpening: false,
    playerShopError: "",
    handoutUnreadCount: 0,
    playerShopRequestSequence: 0,
    fogPreview: { mode: "gm", id: null },
    fogPatchQueue: null,
  }),
  computed: {
    ...sceneWorkspaceCombat.computed,
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
    selectedFogState() {
      return this.$store.getters["vtt/selectedFogState"] || null;
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
    playerHudCanManage() {
      return (
        this.canManage || this.campaignContext.capabilities?.canManage === true
      );
    },
    playerHudCharacterId() {
      return this.playerHudCanManage
        ? this.hudCharacterId
        : this.focusedCharacterId;
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
    fogBusy() {
      return ["loading", "saving"].includes(this.state.fogPhase);
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
  },
  watch: {
    "$route.params.campaignId": "loadCampaign",
    characters: {
      immediate: true,
      handler() {
        this.ensureHudCharacterSelection();
      },
    },
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
    window.addEventListener(
      "blatyrpg:handout-available",
      this.handleHandoutAvailable,
    );
    window.addEventListener(
      "blatyrpg:handout-refresh",
      this.refreshHandoutNotifications,
    );
    this.loadCampaign();
  },
  beforeUnmount() {
    window.removeEventListener(
      "blatyrpg:handout-available",
      this.handleHandoutAvailable,
    );
    window.removeEventListener(
      "blatyrpg:handout-refresh",
      this.refreshHandoutNotifications,
    );
    this.hidePlayerCharacterStats();
    this.hidePlayerShop();
  },
  methods: {
    ...sceneWorkspaceCombat.methods,
    ...tableWindowMethods,
    ...tableTokenMethods,
    ...tableWallMethods,
    ...tableLightMethods,
    ...tableTileMethods,
    handleHandoutAvailable(event) {
      if (Number(event?.detail?.campaignId) !== Number(this.currentCampaignId))
        return;
      this.handoutUnreadCount += 1;
      this.refreshHandoutNotifications();
    },
    async refreshHandoutNotifications(event = null) {
      if (
        event?.detail?.campaignId &&
        Number(event.detail.campaignId) !== Number(this.currentCampaignId)
      ) {
        return;
      }
      try {
        const result = await handoutApiClient.listNotifications(
          this.currentCampaignId,
        );
        this.handoutUnreadCount = Number(result.unreadCount || 0);
      } catch (_error) {
        // Notification access is independent from map loading.
      }
    },
    ensureHudCharacterSelection() {
      const characters = Array.isArray(this.characters) ? this.characters : [];
      if (!this.hudCharacterId || !characters.length) return;
      const selected = characters.find(
        (character) =>
          Number(character.id) === Number(this.hudCharacterId) &&
          Number(character.campaignId) === Number(this.currentCampaignId),
      );
      const userId = Number(authSession.read()?.user?.id) || null;
      const selectionIsAvailable =
        Boolean(selected) &&
        (this.playerHudCanManage || Number(selected.ownerUserId) === userId);
      if (!selectionIsAvailable) this.clearHudCharacterSelection();
    },
    async loadCampaign() {
      this.playerShopRequestSequence += 1;
      this.hidePlayerCharacterStats();
      this.hidePlayerShop();
      this.hudCharacterId = this.readRememberedHudCharacter();
      this.focusedCharacterId = this.hudCharacterId;
      this.playerCharacterStatsId = null;
      this.playerShopOpening = false;
      this.playerShopError = "";
      this.handoutUnreadCount = 0;
      this.activePanelId = this.$route.hash === "#campaign-chat" ? "chat" : "";
      this.settingsOpen = false;
      await ensureVttStoreModule(this.$store);
      this.moduleReady = true;
      this.$store.commit("vtt/SET_CAMPAIGN", this.currentCampaignId);
      await this.$store.dispatch("vtt/initialize").catch(() => {});
      this.refreshHandoutNotifications();
    },
    selectUtility(id) {
      this.settingsOpen = false;
      if (id === "compendium") {
        this.activePanelId = this.activePanelId === id ? "" : id;
        return;
      }
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
      this.fogPreview = { mode: "gm", id: null };
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
    async changeFogPreview(preview) {
      this.fogPreview = preview || { mode: "gm", id: null };
      if (this.fogPreview.mode === "user" && this.fogPreview.id) {
        await this.$store
          .dispatch("vtt/loadFog", this.fogPreview.id)
          .catch(() => {});
      }
    },
    patchFog(patch) {
      if (this.canManage && this.fogPreview.mode !== "user") return;
      const payload = {
        ...patch,
        sceneId: Number(this.state.selectedSceneId),
        ...(this.canManage ? { targetUserId: Number(this.fogPreview.id) } : {}),
      };
      const persist = async () => {
        try {
          const fog = await this.$store.dispatch("vtt/patchFog", payload);
          if (fog?.changed) {
            this.$store.dispatch("realtime/syncFog", fog).catch(() => {});
          }
        } catch (_error) {
          // The store exposes persistence errors in the workspace notice.
        }
      };
      this.fogPatchQueue = (this.fogPatchQueue || Promise.resolve()).then(
        persist,
        persist,
      );
      return this.fogPatchQueue;
    },
    refresh() {
      this.$store.dispatch("vtt/initialize").catch(() => {});
    },
    refreshCampaignContext() {
      Promise.allSettled([
        this.$store.dispatch("campaignContext/refresh"),
        this.$store.dispatch("vtt/loadTokens"),
      ]);
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
    async openPlayerCharacter(characterId) {
      const selectedId = Number(characterId || this.playerHudCharacterId);
      if (
        !Number.isInteger(selectedId) ||
        selectedId < 1 ||
        !this.characters.some(
          (character) => Number(character.id) === selectedId,
        )
      ) {
        return;
      }
      this.focusedCharacterId = selectedId;
      this.playerCharacterStatsId = selectedId;
      await nextTick();
      const modalElement = this.$refs.playerCharacterStatsModal?.$el;
      if (modalElement instanceof HTMLElement) {
        Modal.getOrCreateInstance(modalElement).show();
      }
    },
    hidePlayerCharacterStats() {
      const modalElement = this.$refs.playerCharacterStatsModal?.$el;
      if (modalElement instanceof HTMLElement) {
        Modal.getInstance(modalElement)?.hide();
      }
    },
    selectHudCharacter(characterId) {
      if (
        characterId === null ||
        characterId === undefined ||
        characterId === ""
      ) {
        this.clearHudCharacterSelection();
        return;
      }
      const selectedId = Number(characterId) || null;
      if (!selectedId) return;
      const character = this.characters.find(
        (item) =>
          Number(item.id) === selectedId &&
          Number(item.campaignId) === Number(this.currentCampaignId),
      );
      if (!character) return;
      if (!this.playerHudCanManage) {
        const userId = Number(authSession.read()?.user?.id) || null;
        if (!userId || Number(character?.ownerUserId) !== userId) return;
      }
      this.hudCharacterId = selectedId;
      this.focusedCharacterId = selectedId;
      this.rememberHudCharacter(selectedId);
    },
    clearHudCharacterSelection() {
      this.hudCharacterId = null;
      this.focusedCharacterId = null;
      this.rememberHudCharacter(null);
    },
    rememberedHudCharacterKey() {
      const userId = Number(authSession.read()?.user?.id) || "anonymous";
      return `blatyrpg.table-selected-character.${this.currentCampaignId}.${userId}`;
    },
    readRememberedHudCharacter() {
      try {
        const id = Number(
          window.localStorage.getItem(this.rememberedHudCharacterKey()),
        );
        return Number.isInteger(id) && id > 0 ? id : null;
      } catch (_error) {
        return null;
      }
    },
    rememberHudCharacter(characterId) {
      try {
        if (!characterId) {
          window.localStorage.removeItem(this.rememberedHudCharacterKey());
          return;
        }
        window.localStorage.setItem(
          this.rememberedHudCharacterKey(),
          String(characterId),
        );
      } catch (_error) {
        // A blocked browser storage only disables remembering this preference.
      }
    },
    async openPlayerShop(characterId) {
      if (!this.canOpenShop || this.playerShopOpening) return;
      const selectedId = Number(characterId || this.playerHudCharacterId);
      if (!Number.isInteger(selectedId) || selectedId < 1) return;
      const sequence = ++this.playerShopRequestSequence;
      const campaignId = this.currentCampaignId;
      this.playerShopOpening = true;
      this.playerShopError = "";
      try {
        const [access] = await Promise.all([
          shopApiClient.getAccessOptions({
            campaignId,
          }),
          ensureShopStoreModule(this.$store),
        ]);
        if (
          sequence !== this.playerShopRequestSequence ||
          Number(campaignId) !== Number(this.currentCampaignId)
        ) {
          return;
        }
        const ownerCode = shopOwnerCodeForCharacter(access, selectedId);
        if (!ownerCode) throw new Error("shop_character_unavailable");
        if (access.developmentSelectorEnabled === true) {
          const selectedCharacter = this.characters.find(
            (character) => Number(character.id) === selectedId,
          );
          setShopAccessSession({
            mode: this.playerHudCanManage ? "gm" : "player",
            ownerCode,
            characterId: selectedId,
            name: selectedCharacter?.name || ownerCode,
            playerId: "",
            playerLabel: this.playerHudCanManage ? "GM" : "",
          });
        }
        if (!this.playerShopComponent) {
          const module = await import(
            /* webpackChunkName: "shop-player" */ "@/components/ShopTradeModal.vue"
          );
          if (sequence !== this.playerShopRequestSequence) return;
          this.playerShopComponent = markRaw(module.default);
        }
        this.$store.commit("shop/setCampaignId", campaignId);
        this.$store.commit("shop/enterCharacterShoppingMode");
        this.$store.commit("shop/setShopSession", {
          context: {
            campaignId,
            characterId: selectedId,
            ownerCode,
          },
          actors: access.characters || [],
        });
        const loading = this.$store.dispatch("shop/loadTradingData", {
          campaignId,
          ownerCode,
          viewMode: "character",
          forceReload: true,
        });
        this.playerShopMounted = true;
        await nextTick();
        const modalElement = this.$refs.playerShopModal?.$el;
        if (!(modalElement instanceof HTMLElement)) {
          throw new Error("shop_modal_unavailable");
        }
        Modal.getOrCreateInstance(modalElement).show();
        await loading;
      } catch (_error) {
        if (sequence === this.playerShopRequestSequence) {
          this.playerShopError = this.$t("vtt.table.shop.loadError");
        }
      } finally {
        if (sequence === this.playerShopRequestSequence) {
          this.playerShopOpening = false;
        }
      }
    },
    hidePlayerShop() {
      const modalElement = this.$refs.playerShopModal?.$el;
      if (modalElement instanceof HTMLElement) {
        Modal.getInstance(modalElement)?.hide();
      }
    },
    openPlayerDice() {
      this.$router
        .push({
          name: "dice",
          query: { notation: "1d100", roll: "1" },
        })
        .catch(() => {});
    },
    openPlayerSettings() {
      if (this.canManage) this.openEdit();
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
  },
};
