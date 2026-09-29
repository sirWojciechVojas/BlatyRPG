import { defineAsyncComponent, markRaw } from "vue";
import UiConfirmDialog from "@/components/ui/UiConfirmDialog.vue";
import PlayerCharacterStatsModal from "@/components/characters/PlayerCharacterStatsModal.vue";
import SceneCanvas from "@/components/vtt/scene/SceneCanvas.vue";
import SceneDiceOverlay from "@/components/vtt/scene/SceneDiceOverlay.vue";
import SceneLoadingScreen from "@/components/vtt/scene/SceneLoadingScreen.vue";
import SceneSettingsPanel from "@/components/vtt/scene/SceneSettingsPanel.vue";
import SceneToolbar from "@/components/vtt/scene/SceneToolbar.vue";
import TokenCharacterAssignmentDialog from "@/components/vtt/token/TokenCharacterAssignmentDialog.vue";
import PlayerCharacterHud from "@/components/vtt/table/PlayerCharacterHud.vue";
import PlayerHudComingSoon from "@/components/vtt/table/PlayerHudComingSoon.vue";
import PlayerHudModalShell from "@/components/vtt/table/PlayerHudModalShell.vue";
import TableFloatingWindow from "@/components/vtt/table/TableFloatingWindow.vue";
import TablePanelContent from "@/components/vtt/table/TablePanelContent.vue";
import TableToolRail from "@/components/vtt/table/TableToolRail.vue";
import TableUtilityDrawer from "@/components/vtt/table/TableUtilityDrawer.vue";
import TableUtilityRail from "@/components/vtt/table/TableUtilityRail.vue";
import TableWorkspaceHeader from "@/components/vtt/table/TableWorkspaceHeader.vue";
import { toggledSceneTool } from "@/components/vtt/table/tableSceneTools";
import { playerHudModalById } from "@/components/vtt/table/playerHudModalRegistry";
import { tableWindowMethods } from "@/components/vtt/table/tableWindowMethods";
import { tableTokenMethods } from "@/components/vtt/token/tableTokenMethods";
import { tableWallMethods } from "@/components/vtt/wall/tableWallMethods";
import { tableLightMethods } from "@/components/vtt/light/tableLightMethods";
import { tableRegionMethods } from "@/components/vtt/region/tableRegionMethods";
import { tableTileMethods } from "@/components/vtt/tile/tableTileMethods";
import { shopApiClient } from "@/lib/trade/shopApiClient";
import { setShopAccessSession } from "@/lib/trade/shopAccessSession";
import { ensureShopStoreModule } from "@/store/modules/loadShopModule";
import { shopOwnerCodeForCharacter } from "@/components/vtt/table/playerCharacterHudModel";
import { authSession } from "@/lib/auth/authSession";
import { handoutApiClient } from "@/lib/handouts/handoutApiClient";
import { compendiumApiClient } from "@/lib/compendium/compendiumApiClient";
import {
  IMPLEMENTED_TABLE_UTILITIES,
  utilityById,
} from "@/components/vtt/table/tableUtilities";
import { ensureVttStoreModule } from "@/store/modules/loadVttModule";
import { emptySceneWorkspaceState } from "./sceneWorkspaceState";
import { sceneWorkspaceCombat } from "./sceneWorkspaceCombat";
import { createDiceRollMessage } from "@/lib/chat/diceRollMessage";
import { preloadSceneAsset } from "@/components/vtt/scene/sceneAssetPreloader";

const MapBuilder = defineAsyncComponent(
  () =>
    import(
      /* webpackChunkName: "table-map-builder" */ "@/components/vtt/map-builder/MapBuilder.vue"
    ),
);

const HeroSpellbookContent = defineAsyncComponent(
  () =>
    import(
      /* webpackChunkName: "hero-spellbook" */ "@/components/vtt/magic/HeroSpellbookContent.vue"
    ),
);

const MINIMUM_SCENE_LOADER_DURATION = 1600;

export const formatDiceNotation = (value) =>
  String(value || "")
    .trim()
    .replace(/\s+/g, "")
    .replace(
      /(\d*)([dD])(\d+)/g,
      (_match, count, die, sides) => `${count || "1"}${die}${sides}`,
    )
    .replace(/(\d*)(d[cf])/gi, (_match, count, die) => `${count || "1"}${die}`)
    .replace(/([+\-*/%^])/g, " $1 ")
    .replace(/\s+/g, " ")
    .trim();

export default {
  name: "SceneWorkspaceView",
  components: {
    SceneCanvas,
    SceneDiceOverlay,
    SceneLoadingScreen,
    SceneSettingsPanel,
    SceneToolbar,
    PlayerCharacterHud,
    PlayerCharacterStatsModal,
    PlayerHudComingSoon,
    PlayerHudModalShell,
    TableFloatingWindow,
    TablePanelContent,
    TableToolRail,
    TableUtilityDrawer,
    TableUtilityRail,
    TableWorkspaceHeader,
    TokenCharacterAssignmentDialog,
    UiConfirmDialog,
    MapBuilder,
    HeroSpellbookContent,
  },
  data: () => ({
    moduleReady: false,
    zoomPercent: 100,
    sceneViewCenter: null,
    activeSceneTool: "select",
    activePanelId: "",
    panelWindows: [],
    nextWindowZ: 400,
    confirmDeleteOpen: false,
    pendingDeleteSceneId: null,
    confirmDiscardOpen: false,
    pendingDiscardWindowId: null,
    pendingDiscardPlayerHudModal: false,
    pendingPlayerHudModalRequest: null,
    focusedCharacterId: null,
    hudCharacterId: null,
    activePlayerHudModal: null,
    playerShopComponent: null,
    playerShopOpening: false,
    playerShopError: "",
    diceOverlayMounted: false,
    diceOverlayOpen: false,
    diceOverlayMode: "quick",
    diceRollRequest: 0,
    handoutUnreadCount: 0,
    playerShopRequestSequence: 0,
    fogPreview: { mode: "gm", id: null },
    fogPatchQueue: null,
    voiceSessionGeneration: 0,
    jukeboxSessionGeneration: 0,
    soundEffectsSessionGeneration: 0,
    tokenTemplateBusyId: null,
    tokenCharacterAssignmentToken: null,
    tokenCharacterAssignmentBusy: false,
    tokenCharacterAssignmentError: "",
    tokenCharacterAssignmentCreatedId: null,
    audioSessionMounted: false,
    scenePreloadGeneration: 0,
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
    currentUserId() {
      return Number(authSession.read()?.user?.id) || null;
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
    selectedSceneRegions() {
      return this.$store.getters["vtt/selectedSceneRegions"] || [];
    },
    selectedSceneTiles() {
      return this.$store.getters["vtt/selectedSceneTiles"] || [];
    },
    selectedFogState() {
      return this.$store.getters["vtt/selectedFogState"] || null;
    },
    sceneLoading() {
      const loading = this.state.sceneLoading;
      if (loading?.steps?.length) return loading;
      return { active: !this.moduleReady, steps: [], detail: "" };
    },
    loaderSceneName() {
      return (
        this.selectedScene?.name || this.$t("vtt.scene.loader.unknownScene")
      );
    },
    loaderCampaignName() {
      return String(this.campaign?.name || this.campaign?.title || "");
    },
    loaderCampaignImage() {
      return String(this.campaign?.bannerUrl || "");
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
    canManageRegions() {
      return this.$store.getters["vtt/canManageRegions"] === true;
    },
    canManageTiles() {
      return this.$store.getters["vtt/canManageTiles"] === true;
    },
    canManage() {
      return this.$store.getters["vtt/canManage"] === true;
    },
    isCampaignGameMaster() {
      return (
        String(this.campaign?.campaignRole || "").toLowerCase() === "gm" ||
        (Number(this.campaign?.gameMasterId) > 0 &&
          Number(this.campaign.gameMasterId) === Number(this.currentUserId))
      );
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
    calendarCurrentDate() {
      const calendar = this.$store.state.calendar;
      if (
        Number(calendar?.campaignId) !== Number(this.currentCampaignId) ||
        !calendar?.worldState?.date
      ) {
        return "";
      }
      return calendar.worldState.date.formatted || "";
    },
    calendarCurrentTime() {
      return "";
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
    regionBusy() {
      return ["loading", "saving"].includes(this.state.regionPhase);
    },
    tileBusy() {
      return ["loading", "saving"].includes(this.state.tilePhase);
    },
    fogBusy() {
      return ["loading", "saving"].includes(this.state.fogPhase);
    },
    activeUtility() {
      return utilityById(this.activePanelId);
    },
    drawerOpen() {
      return Boolean(this.activeUtility);
    },
    availableUtilityIds() {
      return IMPLEMENTED_TABLE_UTILITIES.filter(
        (id) =>
          (id !== "shop" || this.canOpenShop) &&
          (utilityById(id)?.gmOnly !== true || this.canManage) &&
          (id !== "token-templates" || this.canCreateToken) &&
          (id !== "calendar" || this.$store?.state?.calendar?.definition),
      );
    },
    playerHudModalDefinition() {
      return playerHudModalById(this.activePlayerHudModal?.id);
    },
    playerHudModalTitle() {
      const definition = this.playerHudModalDefinition;
      if (!definition) return "";
      if (definition.id === "character") {
        const characterId = Number(this.activePlayerHudModal?.characterId);
        return (
          this.characters.find((item) => Number(item.id) === characterId)
            ?.name || this.$t(definition.titleKey)
        );
      }
      return this.$t(definition.titleKey);
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
        const panelByHash = {
          "#campaign-chat": "chat",
          "#table-characters": "characters",
        };
        if (panelByHash[hash]) this.activePanelId = panelByHash[hash];
      },
    },
  },
  created() {
    this.audioSessionMounted = true;
    window.addEventListener(
      "blatyrpg:handout-available",
      this.handleHandoutAvailable,
    );
    window.addEventListener(
      "blatyrpg:handout-refresh",
      this.refreshHandoutNotifications,
    );
    window.addEventListener("keydown", this.handleAudioPttKeyDown);
    window.addEventListener("keydown", this.handleSoundEffectShortcut);
    window.addEventListener("keyup", this.handleAudioPttKeyUp);
    this.loadCampaign();
  },
  beforeUnmount() {
    this.audioSessionMounted = false;
    this.scenePreloadGeneration += 1;
    this.voiceSessionGeneration += 1;
    this.jukeboxSessionGeneration += 1;
    this.soundEffectsSessionGeneration += 1;
    window.removeEventListener(
      "blatyrpg:handout-available",
      this.handleHandoutAvailable,
    );
    window.removeEventListener(
      "blatyrpg:handout-refresh",
      this.refreshHandoutNotifications,
    );
    window.removeEventListener("keydown", this.handleAudioPttKeyDown);
    window.removeEventListener("keydown", this.handleSoundEffectShortcut);
    window.removeEventListener("keyup", this.handleAudioPttKeyUp);
    this.$store.dispatch("voice/pressPushToTalk", false);
    Promise.allSettled([
      this.$store.dispatch("voice/leave"),
      this.$store.dispatch("jukebox/leave"),
      this.$store.dispatch("soundEffects/leave"),
    ]);
    this.activePlayerHudModal = null;
  },
  methods: {
    ...sceneWorkspaceCombat.methods,
    ...tableWindowMethods,
    ...tableTokenMethods,
    ...tableWallMethods,
    ...tableLightMethods,
    ...tableRegionMethods,
    ...tableTileMethods,
    openTokenSyncFromScenes(sceneId = null) {
      if (!this.isCampaignGameMaster) return;
      this.openUtilityWindow("token-sync", { sourceSceneId: sceneId });
    },
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
      const selectionIsAvailable =
        Boolean(selected) &&
        (this.playerHudCanManage || selected.capabilities?.canEdit === true);
      if (!selectionIsAvailable) this.clearHudCharacterSelection();
    },
    async loadCampaign() {
      this.playerShopRequestSequence += 1;
      this.scenePreloadGeneration += 1;
      this.activePlayerHudModal = null;
      this.pendingDiscardPlayerHudModal = false;
      this.pendingPlayerHudModalRequest = null;
      this.confirmDiscardOpen = false;
      this.hudCharacterId = this.readRememberedHudCharacter();
      this.focusedCharacterId = this.hudCharacterId;
      this.playerShopOpening = false;
      this.playerShopError = "";
      this.diceOverlayOpen = false;
      this.handoutUnreadCount = 0;
      this.activePanelId =
        {
          "#campaign-chat": "chat",
          "#table-characters": "characters",
        }[this.$route.hash] || "";
      this.$store.commit("professions/SET_CONTEXT", this.currentCampaignId);
      this.panelWindows = this.panelWindows.filter(
        (item) =>
          item.windowType !== "scene-settings" &&
          (item.panelId !== "calendar" ||
            Number(item.campaignId) === Number(this.currentCampaignId)),
      );
      await ensureVttStoreModule(this.$store);
      this.moduleReady = true;
      this.$store.commit("vtt/SET_CAMPAIGN", this.currentCampaignId);
      void compendiumApiClient.preloadCampaign(this.currentCampaignId);
      await this.$store
        .dispatch("vtt/initialize", { showLoader: true })
        .catch(() => {});
      await this.preloadActiveScene();
      await this.$store
        .dispatch("calendar/initialize", this.currentCampaignId)
        .catch(() => {});
      if (this.isCampaignGameMaster) {
        await this.$store.dispatch("vtt/loadTokenSync").catch(() => {});
      }
      void this.prepareVoiceSession();
      void this.prepareJukeboxSession();
      void this.prepareSoundEffectsSession();
      this.refreshHandoutNotifications();
    },
    sceneAssetsForPreload() {
      const assets = [];
      const scene = this.selectedScene;
      if (scene?.backgroundUrl) {
        assets.push({
          src: scene.backgroundUrl,
          label: this.$t("vtt.scene.loader.items.background"),
        });
      }
      if (scene?.fogExplorationImage) {
        assets.push({
          src: scene.fogExplorationImage,
          label: this.$t("vtt.scene.loader.items.fog"),
        });
      }
      for (const token of this.selectedSceneTokens) {
        if (!token?.imageUrl) continue;
        assets.push({
          src: token.imageUrl,
          label: this.$t("vtt.scene.loader.items.token", {
            name: token.name || this.$t("vtt.scene.loader.items.unnamed"),
          }),
        });
      }
      for (const tile of this.selectedSceneTiles) {
        if (!tile?.assetUrl) continue;
        assets.push({
          src: tile.assetUrl,
          type: tile.mediaType === "video" ? "video" : "image",
          label: this.$t("vtt.scene.loader.items.tile", {
            name: tile.name || this.$t("vtt.scene.loader.items.unnamed"),
          }),
        });
      }

      const seen = new Set();
      return assets.filter((asset) => {
        const key = `${asset.type || "image"}:${asset.src}`;
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
      });
    },
    waitForScenePaint() {
      return new Promise((resolve) => {
        requestAnimationFrame(() => requestAnimationFrame(resolve));
      });
    },
    async preloadActiveScene(sceneId = this.state.selectedSceneId) {
      const generation = ++this.scenePreloadGeneration;
      const startedAt = Date.now();
      const selectedId = Number(sceneId);
      const loading = this.sceneLoading;
      if (
        !loading.active ||
        (!Number.isFinite(selectedId) && this.selectedScene) ||
        (loading.sceneId !== null && Number(loading.sceneId) !== selectedId)
      ) {
        return;
      }

      const isCurrent = () =>
        generation === this.scenePreloadGeneration &&
        Number(this.state.selectedSceneId) === selectedId &&
        this.sceneLoading.active;
      const assets = this.sceneAssetsForPreload();
      let failed = 0;

      this.$store.commit("vtt/SET_SCENE_LOADING_STEP", {
        id: "assets",
        status: "loading",
        detail: "",
      });
      for (const asset of assets) {
        if (!isCurrent()) return;
        this.$store.commit("vtt/SET_SCENE_LOADING_STEP", {
          id: "assets",
          status: "loading",
          detail: asset.label,
        });
        const result = await preloadSceneAsset(asset);
        if (!isCurrent()) return;
        if (result.status === "error") failed += 1;
      }
      if (!isCurrent()) return;

      this.$store.commit("vtt/SET_SCENE_LOADING_STEP", {
        id: "assets",
        status: failed ? "error" : "ready",
        detail: failed
          ? this.$t("vtt.scene.loader.assetsFailed", { count: failed })
          : "",
      });
      this.$store.commit("vtt/SET_SCENE_LOADING_STEP", {
        id: "render",
        status: "loading",
        detail: "",
      });
      await this.$nextTick();
      await this.waitForScenePaint();
      if (!isCurrent()) return;
      const remainingDuration = Math.max(
        0,
        MINIMUM_SCENE_LOADER_DURATION - (Date.now() - startedAt),
      );
      if (remainingDuration) {
        await new Promise((resolve) =>
          window.setTimeout(resolve, remainingDuration),
        );
      }
      if (!isCurrent()) return;
      this.$store.commit("vtt/SET_SCENE_LOADING_STEP", {
        id: "render",
        status: "ready",
        detail: "",
      });
      await new Promise((resolve) => window.setTimeout(resolve, 240));
      if (isCurrent()) this.$store.commit("vtt/FINISH_SCENE_LOADING");
    },
    async prepareVoiceSession() {
      if (!this.audioSessionMounted) return;
      const generation = ++this.voiceSessionGeneration;
      const campaignId = Number(this.currentCampaignId);
      this.$store.dispatch("voice/initialize");

      const voiceCampaignId = Number(this.$store.state.voice?.campaignId);
      if (voiceCampaignId && voiceCampaignId !== campaignId) {
        await this.$store.dispatch("voice/leave").catch(() => {});
      }
      if (
        !this.audioSessionMounted ||
        generation !== this.voiceSessionGeneration
      ) {
        return;
      }
    },

    async prepareJukeboxSession() {
      if (!this.audioSessionMounted) return;
      const generation = ++this.jukeboxSessionGeneration;
      const campaignId = Number(this.currentCampaignId);
      this.$store.dispatch("jukebox/initialize");

      const jukebox = this.$store.state.jukebox || {};
      if (
        Number(jukebox.campaignId) === campaignId &&
        ["loading", "ready"].includes(jukebox.phase)
      ) {
        return;
      }
      if (jukebox.campaignId) {
        await this.$store.dispatch("jukebox/leave").catch(() => {});
      }
      if (
        !this.audioSessionMounted ||
        generation !== this.jukeboxSessionGeneration
      ) {
        return;
      }
      await this.$store.dispatch("jukebox/enter", campaignId).catch(() => {});
    },
    async prepareSoundEffectsSession() {
      if (!this.audioSessionMounted) return;
      const generation = ++this.soundEffectsSessionGeneration;
      const campaignId = Number(this.currentCampaignId);
      const effects = this.$store.state.soundEffects || {};
      if (
        Number(effects.campaignId) === campaignId &&
        ["loading", "ready"].includes(effects.phase)
      ) {
        return;
      }
      if (effects.campaignId) {
        await this.$store.dispatch("soundEffects/leave").catch(() => {});
      }
      if (
        !this.audioSessionMounted ||
        generation !== this.soundEffectsSessionGeneration
      ) {
        return;
      }
      await this.$store
        .dispatch("soundEffects/enter", campaignId)
        .catch(() => {});
    },
    audioPttEditableTarget(target) {
      return (
        ["INPUT", "TEXTAREA", "SELECT"].includes(target?.tagName) ||
        target?.isContentEditable
      );
    },
    handleAudioPttKeyDown(event) {
      const voice = this.$store.state.voice || {};
      if (
        event.code !== "Space" ||
        event.repeat ||
        this.audioPttEditableTarget(event.target) ||
        voice.pushToTalk !== true ||
        !["connected", "reconnecting"].includes(voice.status)
      ) {
        return;
      }
      event.preventDefault();
      this.$store.dispatch("voice/pressPushToTalk", true);
    },
    handleAudioPttKeyUp(event) {
      if (
        event.code === "Space" &&
        this.$store.state.voice?.pushToTalk === true
      ) {
        this.$store.dispatch("voice/pressPushToTalk", false);
      }
    },
    handleSoundEffectShortcut(event) {
      if (
        event.repeat ||
        this.audioPttEditableTarget(event.target) ||
        !this.$store.state.soundEffects?.capabilities?.canControl
      ) {
        return;
      }
      const parts = [];
      if (event.ctrlKey) parts.push("CTRL");
      if (event.altKey) parts.push("ALT");
      if (event.shiftKey) parts.push("SHIFT");
      if (event.metaKey) parts.push("META");
      const key = String(event.key || "").toUpperCase();
      if (!["CONTROL", "ALT", "SHIFT", "META"].includes(key)) {
        parts.push(key);
      }
      const shortcut = parts.join("+");
      const screen = this.$store.getters["soundEffects/activeScreen"];
      const slot = (screen?.slots || []).find(
        (item) => String(item.shortcut || "").toUpperCase() === shortcut,
      );
      if (!slot) return;
      event.preventDefault();
      this.$store.dispatch("soundEffects/toggleSlot", slot).catch(() => {});
    },
    selectUtility(id) {
      if (utilityById(id)?.directWindow === true) {
        this.openUtilityWindow(id);
        return;
      }
      if (id === "sound-effects") {
        this.activePanelId = this.activePanelId === id ? "" : id;
        return;
      }
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
    async selectScene(sceneId) {
      this.fogPreview = { mode: "gm", id: null };
      await this.$store.dispatch("vtt/selectScene", sceneId).catch(() => {});
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
      if (id === "map-builder") {
        this.openMapBuilder();
        return;
      }
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
    async refresh() {
      await this.$store.dispatch("vtt/initialize").catch(() => {});
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
    openPlayerHudModal(id, options = {}) {
      const definition = playerHudModalById(id);
      if (!definition) return null;
      if (definition.requiresManage && !this.canManage) return null;
      if (this.activePlayerHudModal?.id === id && id === "settings") {
        return this.activePlayerHudModal;
      }
      if (
        this.activePlayerHudModal?.id === "settings" &&
        this.activePlayerHudModal.dirty
      ) {
        this.pendingDiscardWindowId = null;
        this.pendingDiscardPlayerHudModal = true;
        this.pendingPlayerHudModalRequest = { id, options };
        this.confirmDiscardOpen = true;
        return null;
      }
      if (id === "shop") {
        return this.openPlayerShop(options?.characterId);
      }
      if (id === "character") {
        return this.openPlayerCharacter(options?.characterId);
      }
      if (id === "settings") {
        return this.openPlayerSettings();
      }
      return this.showPlayerHudModal(id, options);
    },
    showPlayerHudModal(id, options = {}) {
      const definition = playerHudModalById(id);
      if (!definition) return null;
      if (definition.requiresManage && !this.canManage) return null;
      const selectedId = Number(
        options?.characterId || this.playerHudCharacterId,
      );
      if (
        id !== "settings" &&
        (!Number.isInteger(selectedId) ||
          selectedId < 1 ||
          !this.characters.some(
            (character) => Number(character.id) === selectedId,
          ))
      ) {
        return null;
      }
      const descriptor = {
        id,
        campaignId: this.currentCampaignId,
        characterId: id === "settings" ? null : selectedId,
        initialSpellId:
          id === "spells" && Number(options?.spellId) > 0
            ? Number(options.spellId)
            : null,
      };
      if (id === "settings") {
        if (!this.selectedScene) return null;
        Object.assign(descriptor, {
          windowType: "scene-settings",
          sceneId: this.selectedScene.id,
          sceneName: this.selectedScene.name,
          mode: "edit",
          dirty: false,
          status: "saved",
        });
      }
      this.activePlayerHudModal = descriptor;
      return descriptor;
    },
    openPlayerCharacter(characterId) {
      const selectedId = Number(characterId || this.playerHudCharacterId);
      if (
        !Number.isInteger(selectedId) ||
        selectedId < 1 ||
        !this.characters.some(
          (character) => Number(character.id) === selectedId,
        )
      ) {
        return null;
      }
      this.focusedCharacterId = selectedId;
      return this.showPlayerHudModal("character", {
        characterId: selectedId,
      });
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
        if (character?.capabilities?.canEdit !== true) return;
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
        this.showPlayerHudModal("shop", { characterId: selectedId });
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
    requestClosePlayerHudModal() {
      if (
        this.activePlayerHudModal?.id === "settings" &&
        this.activePlayerHudModal.dirty
      ) {
        this.pendingDiscardWindowId = null;
        this.pendingDiscardPlayerHudModal = true;
        this.pendingPlayerHudModalRequest = null;
        this.confirmDiscardOpen = true;
        return;
      }
      this.closePlayerHudModal();
    },
    closePlayerHudModal() {
      this.activePlayerHudModal = null;
    },
    openPlayerDice() {
      this.diceOverlayMounted = true;
      this.diceOverlayMode = "quick";
      this.diceOverlayOpen = true;
      this.diceRollRequest += 1;
    },
    openPlayerDiceSelector() {
      this.diceOverlayMounted = true;
      this.diceOverlayMode = "selector";
      this.diceOverlayOpen = true;
    },
    closePlayerDice() {
      this.diceOverlayOpen = false;
    },
    publishDiceRoll(result) {
      const notation = formatDiceNotation(result?.notation);
      const total =
        result?.total !== undefined &&
        result?.total !== null &&
        String(result.total).trim() !== ""
          ? String(result.total).trim()
          : String(result?.labels || "")
              .replace(/<[^>]*>/g, " ")
              .replace(/\s+/g, " ")
              .trim();
      if (!notation || !total) return null;

      const body = createDiceRollMessage({
        formula: notation,
        dice: result?.dice,
        total,
      });
      if (!body) return null;

      return this.$store.dispatch("realtime/sendChatMessage", body);
    },
    openPlayerSettings() {
      if (!this.canManage || !this.selectedScene) return null;
      return this.showPlayerHudModal("settings");
    },
    openCreate() {
      if (!this.canManage) return;
      this.activePanelId = "";
      this.openSceneSettingsWindow({
        campaignId: this.currentCampaignId,
      });
    },
    openEdit() {
      if (!this.canManage || !this.selectedScene) return;
      this.activePanelId = "";
      this.openSceneSettingsWindow({
        campaignId: this.currentCampaignId,
        sceneId: this.selectedScene.id,
        sceneName: this.selectedScene.name,
      });
    },
    openMapBuilder(mapId = null) {
      if (!this.canManage) return;
      this.activePanelId = "";
      this.openMapBuilderWindow({
        campaignId: this.currentCampaignId,
        sceneId: this.selectedScene?.id || null,
        mapId,
      });
    },
    updateMapBuilderMeta(panelWindow, changes) {
      this.updateMapBuilderWindow(panelWindow.id, {
        ...changes,
        mapName: changes.name ?? panelWindow.mapName,
      });
    },
    async handleMapPublished(payload) {
      await this.$store.dispatch("vtt/initialize").catch(() => {});
      if (Number(payload?.sceneId) > 0) {
        await this.$store
          .dispatch("vtt/selectScene", Number(payload.sceneId))
          .catch(() => {});
      }
      window.dispatchEvent(
        new CustomEvent("blatyrpg:map-published", {
          detail: { campaignId: this.currentCampaignId, ...payload },
        }),
      );
      this.$store
        .dispatch("realtime/sendMapPublished", {
          requestId: window.crypto?.randomUUID?.() || `map-${Date.now()}`,
          mapId: payload.mapId,
          mapRevision: payload.mapRevision,
          sceneId: payload.sceneId,
          sceneRevision: payload.sceneRevision,
        })
        .catch(() => {});
    },
    duplicateScene() {
      if (!this.selectedScene) return;
      const name = this.$t("vtt.scene.actions.copyName", {
        name: this.selectedScene.name,
      });
      this.$store.dispatch("vtt/duplicateSelectedScene", name).catch(() => {});
    },
    sceneForWindow(panelWindow) {
      if (!panelWindow?.sceneId) return null;
      return (
        this.scenes.find(
          (scene) => Number(scene.id) === Number(panelWindow.sceneId),
        ) || null
      );
    },
    sceneSettingsWindowTitle(panelWindow) {
      if (panelWindow.windowType === "map-builder")
        return this.$t("vtt.mapBuilder.title");
      if (panelWindow.windowType === "journal")
        return this.$t("vtt.journal.title");
      if (panelWindow.windowType === "bestiary")
        return this.$t("vtt.bestiary.title");
      if (panelWindow.windowType === "magic")
        return this.$t("vtt.table.playerHud.actions.spells");
      return panelWindow.windowType === "scene-settings"
        ? this.$t("vtt.scene.settings.editTitle")
        : panelWindow.title || this.$t(panelWindow.labelKey);
    },
    sceneSettingsWindowSubtitle(panelWindow) {
      if (panelWindow.windowType === "map-builder") {
        return panelWindow.mapName || this.$t("vtt.mapBuilder.untitled");
      }
      if (["journal", "bestiary", "magic"].includes(panelWindow.windowType)) {
        const characterId = Number(
          panelWindow.characterId || this.playerHudCharacterId,
        );
        return (
          this.characters.find((item) => Number(item.id) === characterId)
            ?.name || this.$t("vtt.journal.noCharacterShort")
        );
      }
      if (panelWindow.windowType !== "scene-settings") return "";
      const name =
        String(panelWindow.sceneName || "").trim() ||
        this.sceneForWindow(panelWindow)?.name;
      return (
        name ||
        this.$t(
          panelWindow.mode === "create"
            ? "vtt.scene.settings.createTitle"
            : "vtt.scene.settings.untitled",
        )
      );
    },
    sceneSettingsWindowStatus(panelWindow) {
      if (panelWindow.windowType === "map-builder") {
        return this.$t(
          `vtt.mapBuilder.status.${panelWindow.status || "saved"}`,
        );
      }
      if (panelWindow.windowType !== "scene-settings") return "";
      return this.$t(
        `vtt.scene.settings.${
          {
            dirty: "unsaved",
            saving: "saving",
            error: "saveFailed",
          }[panelWindow.status] || "saved"
        }`,
      );
    },
    isTopWindow(panelWindow) {
      const topZ = Math.max(
        0,
        ...this.panelWindows.map((item) => Number(item.z) || 0),
      );
      return Number(panelWindow?.z) === topZ;
    },
    async saveSceneSettings(panelWindow, request) {
      const action =
        panelWindow.mode === "create" ? "vtt/createScene" : "vtt/updateScene";
      const payload =
        panelWindow.mode === "create"
          ? request.payload
          : { sceneId: panelWindow.sceneId, changes: request.payload };
      try {
        const scene = await this.$store.dispatch(action, payload);
        if (!scene) throw new Error("scene_save_interrupted");
        this.updateSceneSettingsWindow(panelWindow.id, {
          sceneId: scene.id,
          mode: "edit",
          sceneName: scene.name,
          dirty: false,
          status: "saved",
        });
        request.resolve(scene);
      } catch (error) {
        request.reject(error);
      }
    },
    async savePlayerHudSceneSettings(request) {
      const modal = this.activePlayerHudModal;
      if (modal?.id !== "settings") {
        request.reject(new Error("player_hud_settings_unavailable"));
        return;
      }
      try {
        const scene = await this.$store.dispatch("vtt/updateScene", {
          sceneId: modal.sceneId,
          changes: request.payload,
        });
        if (!scene) throw new Error("scene_save_interrupted");
        this.updatePlayerHudSceneSettings({
          sceneId: scene.id,
          sceneName: scene.name,
          dirty: false,
          status: "saved",
        });
        request.resolve(scene);
      } catch (error) {
        request.reject(error);
      }
    },
    updatePlayerHudSceneSettings(changes = {}) {
      if (this.activePlayerHudModal?.id !== "settings") return;
      const allowed = ["sceneId", "sceneName", "dirty", "status"];
      for (const field of allowed) {
        if (Object.prototype.hasOwnProperty.call(changes, field)) {
          this.activePlayerHudModal[field] = changes[field];
        }
      }
    },
    updateSceneWindowState(panelWindow, changes) {
      this.updateSceneSettingsWindow(panelWindow.id, changes);
    },
    requestCloseUtilityWindow(id) {
      const panelWindow = this.panelWindows.find((item) => item.id === id);
      if (!panelWindow) return;
      if (
        ["scene-settings", "map-builder"].includes(panelWindow.windowType) &&
        panelWindow.dirty
      ) {
        this.pendingDiscardPlayerHudModal = false;
        this.pendingPlayerHudModalRequest = null;
        this.pendingDiscardWindowId = id;
        this.confirmDiscardOpen = true;
        return;
      }
      this.closeUtilityWindow(id);
    },
    discardAndCloseSceneSettings() {
      if (this.pendingDiscardPlayerHudModal) {
        const pendingRequest = this.pendingPlayerHudModalRequest;
        this.confirmDiscardOpen = false;
        this.pendingDiscardPlayerHudModal = false;
        this.pendingPlayerHudModalRequest = null;
        this.pendingDiscardWindowId = null;
        this.closePlayerHudModal();
        if (pendingRequest) {
          this.openPlayerHudModal(pendingRequest.id, pendingRequest.options);
        }
        return;
      }
      const id = this.pendingDiscardWindowId;
      this.confirmDiscardOpen = false;
      this.pendingDiscardWindowId = null;
      if (id) this.closeUtilityWindow(id);
    },
    cancelDiscardSceneSettings() {
      this.confirmDiscardOpen = false;
      this.pendingDiscardWindowId = null;
      this.pendingDiscardPlayerHudModal = false;
      this.pendingPlayerHudModalRequest = null;
    },
    requestDelete(sceneId = null) {
      this.pendingDeleteSceneId = Number(sceneId || this.state.selectedSceneId);
      if (!this.pendingDeleteSceneId) return;
      this.confirmDeleteOpen = true;
    },
    async deleteScene() {
      try {
        const sceneId = this.pendingDeleteSceneId;
        await this.$store.dispatch("vtt/deleteScene", { sceneId });
        this.panelWindows = this.panelWindows.filter(
          (item) =>
            !(
              item.windowType === "scene-settings" &&
              Number(item.sceneId) === Number(sceneId)
            ),
        );
        if (
          this.activePlayerHudModal?.id === "settings" &&
          Number(this.activePlayerHudModal.sceneId) === Number(sceneId)
        ) {
          this.closePlayerHudModal();
        }
        this.confirmDeleteOpen = false;
        this.pendingDeleteSceneId = null;
      } catch (_error) {
        // Store exposes the API error in a visible workspace notice.
      }
    },
    cancelDeleteScene() {
      this.confirmDeleteOpen = false;
      this.pendingDeleteSceneId = null;
    },
    async clearSceneFog(panelWindow, request) {
      const sceneId = Number(panelWindow.sceneId);
      try {
        const fog = await this.$store.dispatch("vtt/resetFog", sceneId);
        if (fog?.changed) {
          await this.$store.dispatch("realtime/syncFog", fog).catch(() => {});
        }
        request.resolve();
      } catch (error) {
        request.reject(error);
      }
    },
    async transitionSceneDarkness(panelWindow, request) {
      try {
        const scene = await this.$store.dispatch("vtt/transitionDarkness", {
          sceneId: Number(panelWindow.sceneId),
          target: Number(request.target),
          duration: Number(request.duration) || 1500,
        });
        request.resolve(scene);
      } catch (error) {
        request.reject(error);
      }
    },
    editWallProperties(wallId) {
      if (!this.canManageWalls) return;
      this.activeSceneTool = "walls";
      this.selectWall(wallId);
      this.$nextTick(() => this.$refs.canvas?.openWallProperties?.(wallId));
    },
    activate() {
      this.$store.dispatch("vtt/activateSelectedScene").catch(() => {});
    },
  },
};
