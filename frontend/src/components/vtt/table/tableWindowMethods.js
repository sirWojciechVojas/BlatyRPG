import { utilityById } from "./tableUtilities";

export const tableWindowMethods = {
  openUtilityWindow(id) {
    if (!this.availableUtilityIds.includes(id)) return;
    this.settingsOpen = false;
    this.activePanelId = "";
    const existing = this.panelWindows.find((item) => item.panelId === id);
    if (existing) {
      existing.minimized = false;
      this.focusUtilityWindow(existing.id);
      return;
    }
    const utility = utilityById(id);
    if (!utility) return;
    const offset = this.panelWindows.length * 26;
    const preferredWidth = Number(utility.windowWidth) || 380;
    const preferredHeight = Number(utility.windowHeight) || 560;
    const width = Math.min(
      preferredWidth,
      Math.max(280, window.innerWidth - 96),
    );
    const height = Math.min(
      preferredHeight,
      Math.max(260, window.innerHeight - 128),
    );
    this.nextWindowZ += 1;
    this.panelWindows.push({
      id: `utility-${id}`,
      panelId: id,
      labelKey: utility.labelKey,
      icon: utility.icon,
      x: Math.min(72 + offset, Math.max(0, window.innerWidth - width)),
      y: Math.min(64 + offset, Math.max(0, window.innerHeight - 38)),
      width,
      height,
      minimized: false,
      z: this.nextWindowZ,
    });
  },
  focusUtilityWindow(id) {
    const panelWindow = this.panelWindows.find((item) => item.id === id);
    if (!panelWindow) return;
    this.nextWindowZ += 1;
    panelWindow.z = this.nextWindowZ;
  },
  moveUtilityWindow({ id, x, y }) {
    const panelWindow = this.panelWindows.find((item) => item.id === id);
    if (panelWindow) Object.assign(panelWindow, { x, y });
  },
  toggleUtilityWindow(id) {
    const panelWindow = this.panelWindows.find((item) => item.id === id);
    if (!panelWindow) return;
    panelWindow.minimized = !panelWindow.minimized;
    this.focusUtilityWindow(id);
  },
  closeUtilityWindow(id) {
    this.panelWindows = this.panelWindows.filter((item) => item.id !== id);
  },
};
