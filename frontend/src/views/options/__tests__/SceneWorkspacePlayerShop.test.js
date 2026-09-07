import { beforeEach, describe, expect, it, vi } from "vitest";
import sceneWorkspaceOptions from "../SceneWorkspaceView.options";

const mocks = vi.hoisted(() => ({
  getAccessOptions: vi.fn(),
  ensureShopStoreModule: vi.fn(),
  getOrCreateInstance: vi.fn(),
  setShopAccessSession: vi.fn(),
}));

vi.mock("@/lib/trade/shopApiClient", () => ({
  shopApiClient: { getAccessOptions: mocks.getAccessOptions },
}));

vi.mock("@/store/modules/loadShopModule", () => ({
  ensureShopStoreModule: mocks.ensureShopStoreModule,
}));

vi.mock("@/lib/trade/shopAccessSession", () => ({
  setShopAccessSession: mocks.setShopAccessSession,
}));

vi.mock("bootstrap", () => ({
  Modal: {
    getInstance: vi.fn(),
    getOrCreateInstance: mocks.getOrCreateInstance,
  },
}));

vi.mock("@/components/ui/UiConfirmDialog.vue", () => ({ default: {} }));
vi.mock("@/components/characters/PlayerCharacterStatsModal.vue", () => ({
  default: {},
}));
vi.mock("@/components/vtt/scene/SceneCanvas.vue", () => ({ default: {} }));
vi.mock("@/components/vtt/scene/SceneSettingsPanel.vue", () => ({
  default: {},
}));
vi.mock("@/components/vtt/scene/SceneToolbar.vue", () => ({ default: {} }));
vi.mock("@/components/vtt/table/PlayerCharacterHud.vue", () => ({
  default: {},
}));
vi.mock("@/components/vtt/table/TableFloatingWindow.vue", () => ({
  default: {},
}));
vi.mock("@/components/vtt/table/TablePanelContent.vue", () => ({
  default: {},
}));
vi.mock("@/components/vtt/table/TableToolRail.vue", () => ({ default: {} }));
vi.mock("@/components/vtt/table/TableUtilityDrawer.vue", () => ({
  default: {},
}));
vi.mock("@/components/vtt/table/TableUtilityRail.vue", () => ({
  default: {},
}));
vi.mock("@/components/vtt/table/TableWorkspaceHeader.vue", () => ({
  default: {},
}));

describe("SceneWorkspace player modules", () => {
  beforeEach(() => {
    mocks.getAccessOptions.mockReset().mockResolvedValue({
      developmentSelectorEnabled: true,
      characters: [{ characterId: 9, ownerCode: "hero_9" }],
      players: [],
    });
    mocks.ensureShopStoreModule.mockReset().mockResolvedValue({});
    mocks.getOrCreateInstance.mockReset().mockReturnValue({ show: vi.fn() });
    mocks.setShopAccessSession.mockReset();
  });

  it("keeps the HUD empty when no character was explicitly selected", () => {
    const selectHudCharacter = vi.fn();
    const context = {
      characters: [
        { id: 14, campaignId: 4, name: "First hero" },
        { id: 27, campaignId: 4, name: "Second hero" },
      ],
      currentCampaignId: 4,
      hudCharacterId: null,
      playerHudCanManage: true,
      selectHudCharacter,
    };

    sceneWorkspaceOptions.methods.ensureHudCharacterSelection.call(context);

    expect(selectHudCharacter).not.toHaveBeenCalled();
  });

  it("keeps the remembered HUD character when it remains available", () => {
    const selectHudCharacter = vi.fn();
    const context = {
      characters: [
        { id: 14, campaignId: 4 },
        { id: 27, campaignId: 4 },
      ],
      currentCampaignId: 4,
      hudCharacterId: 27,
      playerHudCanManage: true,
      selectHudCharacter,
    };

    sceneWorkspaceOptions.methods.ensureHudCharacterSelection.call(context);

    expect(selectHudCharacter).not.toHaveBeenCalled();
  });

  it("rejects a remembered global character not assigned to the campaign", () => {
    const clearHudCharacterSelection = vi.fn();
    const context = {
      characters: [
        { id: 36, campaignId: null, name: "Adelinde" },
        { id: 39, campaignId: 5, name: "Campaign hero" },
      ],
      currentCampaignId: 5,
      hudCharacterId: 36,
      playerHudCanManage: true,
      clearHudCharacterSelection,
    };

    sceneWorkspaceOptions.methods.ensureHudCharacterSelection.call(context);

    expect(clearHudCharacterSelection).toHaveBeenCalledOnce();
  });

  it("persists an explicit HUD selection and removes it after clearing", () => {
    const storageKey = "test.hud-character";
    const context = { rememberedHudCharacterKey: () => storageKey };

    sceneWorkspaceOptions.methods.rememberHudCharacter.call(context, 39);
    expect(window.localStorage.getItem(storageKey)).toBe("39");
    expect(
      sceneWorkspaceOptions.methods.readRememberedHudCharacter.call(context),
    ).toBe(39);

    sceneWorkspaceOptions.methods.rememberHudCharacter.call(context, null);
    expect(window.localStorage.getItem(storageKey)).toBeNull();
    expect(
      sceneWorkspaceOptions.methods.readRememberedHudCharacter.call(context),
    ).toBeNull();
  });

  it("opens the selected character in the existing player shopping mode", async () => {
    const modalElement = document.createElement("div");
    const store = {
      commit: vi.fn(),
      dispatch: vi.fn().mockResolvedValue({ ok: true }),
    };
    const context = {
      canOpenShop: true,
      currentCampaignId: 4,
      characters: [{ id: 9, name: "Selected hero" }],
      playerHudCanManage: true,
      playerHudCharacterId: 9,
      playerShopComponent: {},
      playerShopError: "",
      playerShopMounted: false,
      playerShopOpening: false,
      playerShopRequestSequence: 0,
      $refs: { playerShopModal: { $el: modalElement } },
      $store: store,
      $t: (key) => key,
    };

    await sceneWorkspaceOptions.methods.openPlayerShop.call(context, 9);

    expect(store.commit).toHaveBeenCalledWith(
      "shop/enterCharacterShoppingMode",
    );
    expect(store.commit).toHaveBeenCalledWith("shop/setShopSession", {
      context: { campaignId: 4, characterId: 9, ownerCode: "HERO_9" },
      actors: [{ characterId: 9, ownerCode: "hero_9" }],
    });
    expect(store.dispatch).toHaveBeenCalledWith("shop/loadTradingData", {
      campaignId: 4,
      ownerCode: "HERO_9",
      viewMode: "character",
      forceReload: true,
    });
    expect(mocks.setShopAccessSession).toHaveBeenCalledWith({
      mode: "gm",
      ownerCode: "HERO_9",
      characterId: 9,
      name: "Selected hero",
      playerId: "",
      playerLabel: "GM",
    });
    expect(mocks.getOrCreateInstance).toHaveBeenCalledWith(modalElement);
    expect(
      mocks.getOrCreateInstance.mock.results[0].value.show,
    ).toHaveBeenCalled();
  });

  it("opens characterStats for the HUD character without opening the GM character utility", async () => {
    const modalElement = document.createElement("div");
    const openUtilityWindow = vi.fn();
    const context = {
      characters: [{ id: 9, name: "Selected hero" }],
      playerHudCharacterId: 9,
      focusedCharacterId: null,
      playerCharacterStatsId: null,
      openUtilityWindow,
      $refs: { playerCharacterStatsModal: { $el: modalElement } },
    };

    await sceneWorkspaceOptions.methods.openPlayerCharacter.call(context, 9);

    expect(context.focusedCharacterId).toBe(9);
    expect(context.playerCharacterStatsId).toBe(9);
    expect(openUtilityWindow).not.toHaveBeenCalled();
    expect(mocks.getOrCreateInstance).toHaveBeenCalledWith(modalElement);
    expect(
      mocks.getOrCreateInstance.mock.results[0].value.show,
    ).toHaveBeenCalled();
  });

  it("does not open characterStats for a character outside the campaign context", async () => {
    const context = {
      characters: [{ id: 9 }],
      playerHudCharacterId: 9,
      focusedCharacterId: null,
      playerCharacterStatsId: null,
      $refs: {
        playerCharacterStatsModal: { $el: document.createElement("div") },
      },
    };

    await sceneWorkspaceOptions.methods.openPlayerCharacter.call(context, 77);

    expect(context.focusedCharacterId).toBeNull();
    expect(context.playerCharacterStatsId).toBeNull();
    expect(mocks.getOrCreateInstance).not.toHaveBeenCalled();
  });

  it("starts a d100 roll through the existing dice route", () => {
    const push = vi.fn().mockResolvedValue(undefined);

    sceneWorkspaceOptions.methods.openPlayerDice.call({
      $router: { push },
    });

    expect(push).toHaveBeenCalledWith({
      name: "dice",
      query: { notation: "1d100", roll: "1" },
    });
  });
});
