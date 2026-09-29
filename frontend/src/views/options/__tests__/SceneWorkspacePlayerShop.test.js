import { beforeEach, describe, expect, it, vi } from "vitest";
import sceneWorkspaceOptions, {
  formatDiceNotation,
} from "../SceneWorkspaceView.options";
import { parseDiceRollMessage } from "@/lib/chat/diceRollMessage";

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
vi.mock("@/components/vtt/scene/SceneDiceOverlay.vue", () => ({
  default: {},
}));
vi.mock("@/components/vtt/scene/SceneLoadingScreen.vue", () => ({
  default: {},
}));
vi.mock("@/components/vtt/scene/SceneSettingsPanel.vue", () => ({
  default: {},
}));
vi.mock("@/components/vtt/scene/SceneToolbar.vue", () => ({ default: {} }));
vi.mock("@/components/vtt/token/TokenCharacterAssignmentDialog.vue", () => ({
  default: {},
}));
vi.mock("@/components/vtt/table/PlayerCharacterHud.vue", () => ({
  default: {},
}));
vi.mock("@/components/vtt/table/PlayerHudComingSoon.vue", () => ({
  default: {},
}));
vi.mock("@/components/vtt/table/PlayerHudModalShell.vue", () => ({
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

  it("exposes token templates only when the user can create tokens", () => {
    const available = sceneWorkspaceOptions.computed.availableUtilityIds;

    expect(
      available.call({ canOpenShop: true, canCreateToken: true }),
    ).toContain("token-templates");
    expect(
      available.call({ canOpenShop: true, canCreateToken: false }),
    ).not.toContain("token-templates");
  });

  it("does not expose a separate sidebar bestiary to players or managers", () => {
    const available = sceneWorkspaceOptions.computed.availableUtilityIds;
    const context = {
      canOpenShop: false,
      canCreateToken: false,
      $store: { state: { calendar: { definition: {} } } },
    };

    expect(available.call({ ...context, canManage: false })).not.toContain(
      "bestiary",
    );
    expect(available.call({ ...context, canManage: true })).not.toContain(
      "bestiary",
    );
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

  it("allows a player to select a character shared with edit access", () => {
    const context = {
      characters: [
        {
          id: 36,
          campaignId: 5,
          ownerUserId: 99,
          capabilities: { canEdit: true },
        },
      ],
      currentCampaignId: 5,
      playerHudCanManage: false,
      hudCharacterId: null,
      focusedCharacterId: null,
      rememberHudCharacter: vi.fn(),
    };

    sceneWorkspaceOptions.methods.selectHudCharacter.call(context, 36);

    expect(context.hudCharacterId).toBe(36);
    expect(context.focusedCharacterId).toBe(36);
    expect(context.rememberHudCharacter).toHaveBeenCalledWith(36);
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
    const store = {
      commit: vi.fn(),
      dispatch: vi.fn().mockResolvedValue({ ok: true }),
    };
    const showPlayerHudModal = vi.fn();
    const context = {
      canOpenShop: true,
      currentCampaignId: 4,
      characters: [{ id: 9, name: "Selected hero" }],
      playerHudCanManage: true,
      playerHudCharacterId: 9,
      playerShopComponent: {},
      playerShopError: "",
      playerShopOpening: false,
      playerShopRequestSequence: 0,
      showPlayerHudModal,
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
    expect(showPlayerHudModal).toHaveBeenCalledWith("shop", {
      characterId: 9,
    });
  });

  it("opens characterStats for the HUD character in the central modal", () => {
    const openUtilityWindow = vi.fn();
    const showPlayerHudModal = vi.fn(() => ({ id: "character" }));
    const context = {
      characters: [{ id: 9, name: "Selected hero" }],
      playerHudCharacterId: 9,
      focusedCharacterId: null,
      openUtilityWindow,
      showPlayerHudModal,
    };

    sceneWorkspaceOptions.methods.openPlayerCharacter.call(context, 9);

    expect(context.focusedCharacterId).toBe(9);
    expect(openUtilityWindow).not.toHaveBeenCalled();
    expect(showPlayerHudModal).toHaveBeenCalledWith("character", {
      characterId: 9,
    });
  });

  it("does not open characterStats for a character outside the campaign context", () => {
    const showPlayerHudModal = vi.fn();
    const context = {
      characters: [{ id: 9 }],
      playerHudCharacterId: 9,
      focusedCharacterId: null,
      showPlayerHudModal,
    };

    sceneWorkspaceOptions.methods.openPlayerCharacter.call(context, 77);

    expect(context.focusedCharacterId).toBeNull();
    expect(showPlayerHudModal).not.toHaveBeenCalled();
  });

  it("protects dirty HUD settings before replacing or closing the modal", () => {
    const methods = sceneWorkspaceOptions.methods;
    const showPlayerHudModal = vi.fn(() => ({ id: "journal" }));
    const context = {
      activePlayerHudModal: { id: "settings", dirty: true },
      canManage: true,
      confirmDiscardOpen: false,
      pendingDiscardWindowId: "window-1",
      pendingDiscardPlayerHudModal: false,
      pendingPlayerHudModalRequest: null,
      showPlayerHudModal,
    };
    context.closePlayerHudModal = () =>
      methods.closePlayerHudModal.call(context);
    context.openPlayerHudModal = (id, options) =>
      methods.openPlayerHudModal.call(context, id, options);

    methods.openPlayerHudModal.call(context, "journal", { characterId: 9 });

    expect(context.confirmDiscardOpen).toBe(true);
    expect(context.pendingDiscardWindowId).toBeNull();
    expect(context.pendingDiscardPlayerHudModal).toBe(true);
    expect(context.pendingPlayerHudModalRequest).toEqual({
      id: "journal",
      options: { characterId: 9 },
    });
    expect(showPlayerHudModal).not.toHaveBeenCalled();

    methods.discardAndCloseSceneSettings.call(context);

    expect(context.activePlayerHudModal).toBeNull();
    expect(context.confirmDiscardOpen).toBe(false);
    expect(showPlayerHudModal).toHaveBeenCalledWith("journal", {
      characterId: 9,
    });
  });

  it("keeps dirty HUD settings open when a close request awaits confirmation", () => {
    const closePlayerHudModal = vi.fn();
    const context = {
      activePlayerHudModal: { id: "settings", dirty: true },
      confirmDiscardOpen: false,
      pendingDiscardWindowId: "window-1",
      pendingDiscardPlayerHudModal: false,
      pendingPlayerHudModalRequest: { id: "journal", options: {} },
      closePlayerHudModal,
    };

    sceneWorkspaceOptions.methods.requestClosePlayerHudModal.call(
      context,
      "escape",
    );

    expect(closePlayerHudModal).not.toHaveBeenCalled();
    expect(context.confirmDiscardOpen).toBe(true);
    expect(context.pendingDiscardPlayerHudModal).toBe(true);
    expect(context.pendingPlayerHudModalRequest).toBeNull();
  });

  it("starts a d100 roll in the scene overlay without using the router", () => {
    const context = {
      diceOverlayMounted: false,
      diceOverlayOpen: false,
      diceOverlayMode: "selector",
      diceRollRequest: 2,
    };

    sceneWorkspaceOptions.methods.openPlayerDice.call(context);

    expect(context).toMatchObject({
      diceOverlayMounted: true,
      diceOverlayOpen: true,
      diceOverlayMode: "quick",
      diceRollRequest: 3,
    });
  });

  it("opens and closes the 3D selector without discarding its mount", () => {
    const context = {
      diceOverlayMounted: false,
      diceOverlayOpen: false,
      diceOverlayMode: "quick",
    };

    sceneWorkspaceOptions.methods.openPlayerDiceSelector.call(context);
    expect(context).toMatchObject({
      diceOverlayMounted: true,
      diceOverlayOpen: true,
      diceOverlayMode: "selector",
    });

    sceneWorkspaceOptions.methods.closePlayerDice.call(context);
    expect(context.diceOverlayOpen).toBe(false);
    expect(context.diceOverlayMounted).toBe(true);
  });

  it("publishes a completed dice result in campaign chat", () => {
    const dispatch = vi.fn(() => "message-nonce");
    const context = {
      $store: { dispatch },
    };

    const response = sceneWorkspaceOptions.methods.publishDiceRoll.call(
      context,
      {
        notation: "1d100+1d10",
        total: 73,
        dice: [
          { type: "d100", display: "70" },
          { type: "d10", display: "3" },
        ],
      },
    );

    expect(dispatch).toHaveBeenCalledWith(
      "realtime/sendChatMessage",
      expect.any(String),
    );
    const body = dispatch.mock.calls[0][1];
    expect(parseDiceRollMessage(body)).toEqual({
      formula: "1d100 + 1d10",
      dice: [
        { type: "d100", value: "70" },
        { type: "d10", value: "3" },
      ],
      total: "73",
    });
    expect(response).toBe("message-nonce");
  });

  it("uses a plain label for symbolic dice chat results", () => {
    const dispatch = vi.fn();
    const context = {
      $store: { dispatch },
    };

    sceneWorkspaceOptions.methods.publishDiceRoll.call(context, {
      notation: "1dc",
      total: "",
      labels: '<span class="diceresult">Heads</span>',
    });

    const body = dispatch.mock.calls[0][1];
    expect(parseDiceRollMessage(body)).toEqual({
      formula: "1dc",
      dice: [],
      total: "Heads",
    });
  });

  it("formats dice notation for chat without losing percentile dice", () => {
    expect(formatDiceNotation("1d100+1d10")).toBe("1d100 + 1d10");
    expect(formatDiceNotation("2d6+1d4-3")).toBe("2d6 + 1d4 - 3");
    expect(formatDiceNotation("1D10+1d10")).toBe("1D10 + 1d10");
  });
});
