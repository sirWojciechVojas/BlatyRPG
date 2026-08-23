import { describe, expect, it } from "vitest";
import { tableWindowMethods } from "../tableWindowMethods";

const context = () => ({
  availableUtilityIds: ["chat", "characters", "scenes"],
  activePanelId: "chat",
  settingsOpen: true,
  panelWindows: [],
  nextWindowZ: 400,
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

  it("does not open unfinished modules", () => {
    const vm = context();
    tableWindowMethods.openUtilityWindow.call(vm, "combat");
    expect(vm.panelWindows).toEqual([]);
  });
});
