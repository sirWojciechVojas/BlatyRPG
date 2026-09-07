import { utilityById } from "./tableUtilities";

export const tableWindowMethods = {
  openUtilityWindow(id) {
    if (!this.availableUtilityIds.includes(id)) return;

    // The GM shop is a separate workspace, not a constrained utility window.
    // Going through the route also preserves a simple, predictable way back to
    // the current campaign table.
    if (id === "shop") {
      this.$router
        .push({
          name: "shop-gm",
          params: { campaignId: this.currentCampaignId },
        })
        .catch(() => {});
      return;
    }

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
    const preferredWidth = utility.fillViewport
      ? window.innerWidth - 64
      : Number(utility.windowWidth) || 380;
    const preferredHeight = utility.fillViewport
      ? window.innerHeight - 80
      : Number(utility.windowHeight) || 560;
    const width = Math.min(
      preferredWidth,
      Math.max(280, window.innerWidth - (utility.fillViewport ? 32 : 96)),
    );
    const height = Math.min(
      preferredHeight,
      Math.max(260, window.innerHeight - (utility.fillViewport ? 48 : 128)),
    );
    this.nextWindowZ += 1;
    this.panelWindows.push({
      id: `utility-${id}`,
      panelId: id,
      labelKey: utility.labelKey,
      icon: utility.icon,
      x: utility.fillViewport
        ? Math.max(0, Math.round((window.innerWidth - width) / 2))
        : Math.min(72 + offset, Math.max(0, window.innerWidth - width)),
      y: utility.fillViewport
        ? Math.max(0, Math.round((window.innerHeight - height) / 2))
        : Math.min(64 + offset, Math.max(0, window.innerHeight - 38)),
      width,
      height,
      minimized: false,
      z: this.nextWindowZ,
    });
  },
  openHandoutWindow(payload = {}) {
    const scope = ["library", "campaign"].includes(payload.scope)
      ? payload.scope
      : "campaign";
    const numericId = Number(payload.handoutId);
    const handoutId =
      Number.isInteger(numericId) && numericId > 0 ? numericId : null;
    if (!handoutId && scope !== "library") return;

    const existing = handoutId
      ? this.panelWindows.find(
          (item) =>
            item.panelId === "handout-document" &&
            item.handoutScope === scope &&
            Number(item.handoutId) === handoutId,
        )
      : null;
    if (existing) {
      existing.minimized = false;
      this.focusUtilityWindow(existing.id);
      return;
    }

    const utility = utilityById("handouts");
    if (!utility) return;
    const detailWindowCount = this.panelWindows.filter(
      (item) => item.panelId === "handout-document",
    ).length;
    const offset = detailWindowCount * 26;
    const width = Math.min(760, Math.max(320, window.innerWidth - 96));
    const height = Math.min(720, Math.max(300, window.innerHeight - 128));
    this.nextWindowZ += 1;
    this.panelWindows.push({
      id: handoutId
        ? `handout-${scope}-${handoutId}`
        : `handout-library-new-${this.nextWindowZ}`,
      panelId: "handout-document",
      labelKey: utility.labelKey,
      title: payload.title || "",
      icon: utility.icon,
      handoutScope: scope,
      handoutId,
      startEditing: payload.startEditing === true,
      x: Math.min(96 + offset, Math.max(0, window.innerWidth - width)),
      y: Math.min(72 + offset, Math.max(0, window.innerHeight - 38)),
      width,
      height,
      minimized: false,
      z: this.nextWindowZ,
    });
  },
  updateHandoutWindow({ windowId, handoutId, title }) {
    const panelWindow = this.panelWindows.find((item) => item.id === windowId);
    if (!panelWindow || panelWindow.panelId !== "handout-document") return;
    const numericId = Number(handoutId);
    if (Number.isInteger(numericId) && numericId > 0) {
      panelWindow.handoutId = numericId;
    }
    panelWindow.startEditing = false;
    if (typeof title === "string" && title.trim()) {
      panelWindow.title = title.trim();
    }
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
