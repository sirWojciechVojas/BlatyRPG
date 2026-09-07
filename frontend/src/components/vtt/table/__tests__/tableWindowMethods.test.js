import { describe, expect, it, vi } from "vitest";
import { tableWindowMethods } from "../tableWindowMethods";

const context = () => ({
  availableUtilityIds: [
    "chat",
    "characters",
    "scenes",
    "handouts",
    "compendium",
    "shop",
  ],
  activePanelId: "chat",
  settingsOpen: true,
  panelWindows: [],
  nextWindowZ: 400,
  currentCampaignId: 42,
  $router: { push: vi.fn(() => Promise.resolve()) },
  focusUtilityWindow: tableWindowMethods.focusUtilityWindow,
});

describe("table window methods", () => {
  it("opens several utility windows with independent positions", () => {
    const vm = context();

    tableWindowMethods.openUtilityWindow.call(vm, "chat");
    tableWindowMethods.openUtilityWindow.call(vm, "characters");

    expect(vm.panelWindows.map(({ panelId }) => panelId)).toEqual([
      "chat",
      "characters",
    ]);
    expect(vm.panelWindows[1].x).toBeGreaterThan(vm.panelWindows[0].x);
    expect(vm.activePanelId).toBe("");
    expect(vm.settingsOpen).toBe(false);
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
    expect(vm.settingsOpen).toBe(true);
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
});
