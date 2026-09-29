import { describe, expect, it, vi } from "vitest";
import { implementedSceneTool, TABLE_SCENE_TOOLS } from "../tableSceneTools";
import { tableWindowMethods } from "../tableWindowMethods";

const context = () => ({
  availableUtilityIds: [
    "chat",
    "characters",
    "calendar",
    "scenes",
    "handouts",
    "compendium",
    "shop",
    "voice",
    "jukebox",
    "sound-effects",
  ],
  activePanelId: "chat",
  panelWindows: [],
  nextWindowZ: 400,
  currentCampaignId: 42,
  $router: { push: vi.fn(() => Promise.resolve()) },
  focusUtilityWindow: tableWindowMethods.focusUtilityWindow,
  openUtilityWindow: tableWindowMethods.openUtilityWindow,
});

describe("table window methods", () => {
  it("exposes the GM map builder in the scene toolbox", () => {
    expect(TABLE_SCENE_TOOLS).toContainEqual(
      expect.objectContaining({ id: "map-builder", gmOnly: true }),
    );
    expect(implementedSceneTool("map-builder")).toBe(true);
  });

  it("opens one maximized map builder with campaign, scene and map scope", () => {
    const vm = context();
    const first = tableWindowMethods.openMapBuilderWindow.call(vm, {
      campaignId: 42,
      sceneId: 17,
      mapId: 9,
      mapName: "Karczma pod Dębem",
    });
    tableWindowMethods.openMapBuilderWindow.call(vm, {
      campaignId: 42,
      sceneId: 17,
      mapId: 9,
    });

    expect(vm.panelWindows).toHaveLength(1);
    expect(first).toMatchObject({
      windowType: "map-builder",
      campaignId: 42,
      sceneId: 17,
      mapId: 9,
      maximized: true,
      maximizable: true,
      resizable: true,
      minWidth: expect.any(Number),
      minHeight: expect.any(Number),
    });
    expect(first.maxWidth).toBeGreaterThan(600);
    expect(first.maxHeight).toBeGreaterThan(600);
  });

  it("opens several utility windows with independent positions", () => {
    const vm = context();

    tableWindowMethods.openUtilityWindow.call(vm, "chat");
    tableWindowMethods.openUtilityWindow.call(vm, "characters");

    expect(vm.panelWindows.map(({ panelId }) => panelId)).toEqual([
      "chat",
      "characters",
    ]);
    expect(vm.panelWindows[1].x).not.toBe(vm.panelWindows[0].x);
    expect(vm.activePanelId).toBe("");
  });

  it("opens chat in the center with the chatbox artwork proportions", () => {
    const vm = context();

    tableWindowMethods.openUtilityWindow.call(vm, "chat");

    expect(vm.panelWindows[0]).toMatchObject({
      panelId: "chat",
      windowType: "chat",
      width: 529,
      height: 530,
      aspectRatio: 529 / 530,
      x: Math.round((window.innerWidth - 529) / 2),
      y: Math.round((window.innerHeight - 530) / 2),
    });
  });

  it("opens the jukebox in a floating window on double click", () => {
    const vm = context();

    tableWindowMethods.openUtilityWindow.call(vm, "jukebox");

    expect(vm.activePanelId).toBe("");
    expect(vm.panelWindows).toHaveLength(1);
    expect(vm.panelWindows[0]).toMatchObject({
      panelId: "jukebox",
      width: 380,
    });
    expect(vm.panelWindows[0].height).toBeGreaterThan(500);
  });

  it("opens voice in a separate floating window on double click", () => {
    const vm = context();

    tableWindowMethods.openUtilityWindow.call(vm, "voice");

    expect(vm.activePanelId).toBe("");
    expect(vm.panelWindows).toHaveLength(1);
    expect(vm.panelWindows[0]).toMatchObject({
      panelId: "voice",
      width: 380,
    });
    expect(vm.panelWindows[0].height).toBeGreaterThan(500);
  });

  it("opens and then focuses one resizable sound effects window", () => {
    const vm = context();

    tableWindowMethods.openUtilityWindow.call(vm, "sound-effects");
    const firstZ = vm.panelWindows[0].z;
    tableWindowMethods.openUtilityWindow.call(vm, "sound-effects");

    expect(vm.panelWindows).toHaveLength(1);
    expect(vm.panelWindows[0]).toMatchObject({
      id: "utility-sound-effects",
      panelId: "sound-effects",
      resizable: true,
      minWidth: 860,
      minHeight: 560,
    });
    expect(vm.panelWindows[0].z).toBeGreaterThan(firstZ);
  });

  it("opens the journal for a character with dedicated dimensions", () => {
    const vm = context();

    tableWindowMethods.openUtilityWindow.call(vm, "journal", {
      characterId: 17,
    });

    expect(vm.panelWindows[0]).toMatchObject({
      id: "utility-journal",
      panelId: "journal",
      windowType: "journal",
      characterId: 17,
      minWidth: 760,
      minHeight: 520,
      maxViewportWidthRatio: 0.95,
      maxViewportHeightRatio: 0.9,
      resizable: true,
    });
  });

  it("does not route character-only bestiary through sidebar windows", () => {
    const vm = context();

    tableWindowMethods.openUtilityWindow.call(vm, "bestiary");

    expect(vm.panelWindows).toHaveLength(0);
  });

  it("opens token sync only as a scene-launched window and updates its source scene", () => {
    const vm = context();

    tableWindowMethods.openUtilityWindow.call(vm, "token-sync", {
      sourceSceneId: 17,
    });
    const firstZ = vm.panelWindows[0].z;
    vm.panelWindows[0].minimized = true;

    tableWindowMethods.openUtilityWindow.call(vm, "token-sync", {
      sourceSceneId: 29,
    });

    expect(vm.panelWindows).toHaveLength(1);
    expect(vm.panelWindows[0]).toMatchObject({
      id: "utility-token-sync",
      panelId: "token-sync",
      initialSceneId: 29,
      minWidth: 720,
      minHeight: 520,
      resizable: true,
      minimized: false,
    });
    expect(vm.panelWindows[0].width).toBeGreaterThanOrEqual(720);
    expect(vm.panelWindows[0].width).toBeLessThanOrEqual(980);
    expect(vm.panelWindows[0].height).toBeGreaterThanOrEqual(520);
    expect(vm.panelWindows[0].height).toBeLessThanOrEqual(760);
    expect(vm.panelWindows[0].z).toBeGreaterThan(firstZ);
  });

  it("focuses an existing window instead of duplicating it", () => {
    const vm = context();
    tableWindowMethods.openUtilityWindow.call(vm, "scenes");
    vm.panelWindows[0].minimized = true;
    const previousZ = vm.panelWindows[0].z;

    tableWindowMethods.openUtilityWindow.call(vm, "scenes");

    expect(vm.panelWindows).toHaveLength(1);
    expect(vm.panelWindows[0].minimized).toBe(false);
    expect(vm.panelWindows[0].z).toBeGreaterThan(previousZ);
  });

  it("keeps one calendar window per campaign and inside a 1366 x 768 viewport", () => {
    const previousWidth = window.innerWidth;
    const previousHeight = window.innerHeight;
    Object.defineProperty(window, "innerWidth", {
      value: 1366,
      configurable: true,
    });
    Object.defineProperty(window, "innerHeight", {
      value: 768,
      configurable: true,
    });
    const vm = context();

    tableWindowMethods.openUtilityWindow.call(vm, "calendar");
    const firstZ = vm.panelWindows[0].z;
    tableWindowMethods.openUtilityWindow.call(vm, "calendar");

    expect(vm.panelWindows).toHaveLength(1);
    expect(vm.panelWindows[0]).toMatchObject({
      id: "utility-calendar-42",
      panelId: "calendar",
      campaignId: 42,
      windowType: "calendar",
      maximizable: true,
      resizable: true,
      constrainToViewport: true,
    });
    expect(vm.panelWindows[0].x + vm.panelWindows[0].width).toBeLessThanOrEqual(
      1366,
    );
    expect(
      vm.panelWindows[0].y + vm.panelWindows[0].height,
    ).toBeLessThanOrEqual(768);
    expect(vm.panelWindows[0].z).toBeGreaterThan(firstZ);

    Object.defineProperty(window, "innerWidth", {
      value: previousWidth,
      configurable: true,
    });
    Object.defineProperty(window, "innerHeight", {
      value: previousHeight,
      configurable: true,
    });
  });

  it("opens several handouts without closing the list drawer", () => {
    const vm = context();

    tableWindowMethods.openHandoutWindow.call(vm, {
      scope: "library",
      handoutId: 11,
      title: "Mapa ruin",
    });
    tableWindowMethods.openHandoutWindow.call(vm, {
      scope: "campaign",
      handoutId: 21,
      title: "List gończy",
    });

    expect(vm.panelWindows).toHaveLength(2);
    expect(vm.panelWindows.map(({ panelId }) => panelId)).toEqual([
      "handout-document",
      "handout-document",
    ]);
    expect(vm.panelWindows.map(({ handoutScope }) => handoutScope)).toEqual([
      "library",
      "campaign",
    ]);
    expect(vm.panelWindows[0].id).not.toBe(vm.panelWindows[1].id);
    expect(vm.activePanelId).toBe("chat");
  });

  it("focuses the same handout instead of opening another copy", () => {
    const vm = context();
    const payload = {
      scope: "campaign",
      handoutId: 21,
      title: "List gończy",
    };
    tableWindowMethods.openHandoutWindow.call(vm, payload);
    vm.panelWindows[0].minimized = true;
    const previousZ = vm.panelWindows[0].z;

    tableWindowMethods.openHandoutWindow.call(vm, payload);

    expect(vm.panelWindows).toHaveLength(1);
    expect(vm.panelWindows[0].minimized).toBe(false);
    expect(vm.panelWindows[0].z).toBeGreaterThan(previousZ);
  });

  it("opens one large self-contained compendium workspace", () => {
    const vm = context();

    tableWindowMethods.openUtilityWindow.call(vm, "compendium");
    tableWindowMethods.openUtilityWindow.call(vm, "compendium");

    expect(vm.panelWindows).toHaveLength(1);
    expect(vm.panelWindows[0]).toMatchObject({
      id: "utility-compendium",
      panelId: "compendium",
    });
    expect(vm.panelWindows[0].width).toBeGreaterThan(900);
    expect(tableWindowMethods.openCompendiumWindow).toBeUndefined();
  });

  it("updates a new handout window after its first save", () => {
    const vm = context();
    tableWindowMethods.openHandoutWindow.call(vm, {
      scope: "library",
      handoutId: null,
      title: "Nowy handout",
      startEditing: true,
    });
    const windowId = vm.panelWindows[0].id;

    tableWindowMethods.updateHandoutWindow.call(vm, {
      windowId,
      handoutId: 31,
      title: "Notatki karczmarza",
    });

    expect(vm.panelWindows[0]).toMatchObject({
      handoutId: 31,
      title: "Notatki karczmarza",
      startEditing: false,
    });
  });

  it("does not open unfinished modules", () => {
    const vm = context();
    tableWindowMethods.openUtilityWindow.call(vm, "combat");
    expect(vm.panelWindows).toEqual([]);
  });

  it("opens the GM shop as a full workspace route", () => {
    const vm = context();

    tableWindowMethods.openUtilityWindow.call(vm, "shop");

    expect(vm.$router.push).toHaveBeenCalledWith({
      name: "shop-gm",
      params: { campaignId: 42 },
    });
    expect(vm.panelWindows).toEqual([]);
  });

  it("opens and focuses one scene settings window per scene", () => {
    const vm = context();

    const first = tableWindowMethods.openSceneSettingsWindow.call(vm, {
      sceneId: 7,
      sceneName: "Leśna droga",
    });
    const previousZ = first.z;
    first.minimized = true;
    tableWindowMethods.openSceneSettingsWindow.call(vm, { sceneId: 7 });

    expect(vm.panelWindows).toHaveLength(1);
    expect(vm.panelWindows[0]).toMatchObject({
      campaignId: 42,
      sceneId: 7,
      mode: "edit",
      windowType: "scene-settings",
      width: 920,
      height: 600,
      minWidth: 720,
      minHeight: 520,
      resizable: true,
      maximizable: true,
      constrainToViewport: true,
      minimized: false,
    });
    expect(vm.panelWindows[0].z).toBeGreaterThan(previousZ);
  });

  it("keeps create and edit settings windows independent", () => {
    const vm = context();

    tableWindowMethods.openSceneSettingsWindow.call(vm);
    tableWindowMethods.openSceneSettingsWindow.call(vm, { sceneId: 3 });
    tableWindowMethods.openSceneSettingsWindow.call(vm, { sceneId: 4 });
    tableWindowMethods.openSceneSettingsWindow.call(vm);

    expect(vm.panelWindows).toHaveLength(3);
    expect(vm.panelWindows.map(({ sceneId }) => sceneId)).toEqual([null, 3, 4]);
  });

  it("maximizes and restores an eligible window", () => {
    const vm = context();
    const panelWindow = tableWindowMethods.openSceneSettingsWindow.call(vm, {
      sceneId: 7,
    });
    const bounds = {
      x: panelWindow.x,
      y: panelWindow.y,
      width: panelWindow.width,
      height: panelWindow.height,
    };

    tableWindowMethods.toggleMaximizeUtilityWindow.call(vm, panelWindow.id);
    expect(panelWindow.maximized).toBe(true);
    tableWindowMethods.toggleMaximizeUtilityWindow.call(vm, panelWindow.id);

    expect(panelWindow).toMatchObject({ ...bounds, maximized: false });
  });

  it("moves, resizes, minimizes and closes scene settings independently", () => {
    const vm = context();
    const panelWindow = tableWindowMethods.openSceneSettingsWindow.call(vm, {
      sceneId: 7,
    });

    tableWindowMethods.moveUtilityWindow.call(vm, {
      id: panelWindow.id,
      x: 18,
      y: 24,
    });
    tableWindowMethods.resizeUtilityWindow.call(vm, {
      id: panelWindow.id,
      x: 18,
      y: 24,
      width: 800,
      height: 550,
    });
    tableWindowMethods.toggleUtilityWindow.call(vm, panelWindow.id);

    expect(panelWindow).toMatchObject({
      x: 18,
      y: 24,
      width: 800,
      height: 550,
      minimized: true,
    });

    tableWindowMethods.closeUtilityWindow.call(vm, panelWindow.id);
    expect(vm.panelWindows).toEqual([]);
  });

  it("synchronizes a rendered window layer with its workspace model", () => {
    const vm = context();
    tableWindowMethods.openUtilityWindow.call(vm, "chat");

    tableWindowMethods.syncUtilityWindowLayer.call(vm, {
      id: "utility-chat",
      z: 712,
    });

    expect(vm.panelWindows[0].z).toBe(712);
  });
});
