import { utilityById } from "./tableUtilities";
import { focusTableWindow } from "./tableWindowLayers";

export const TABLE_WINDOW_TYPE_CONFIG = Object.freeze({
  sceneSettings: Object.freeze({
    width: 920,
    height: 600,
    minWidth: 720,
    minHeight: 520,
    resizable: true,
    maximizable: true,
    constrainToViewport: true,
  }),
  mapBuilder: Object.freeze({
    width: 1560,
    height: 960,
    minWidth: 900,
    minHeight: 620,
    resizable: true,
    maximizable: true,
    constrainToViewport: true,
  }),
});

const viewportBounds = () => ({
  width: Math.max(1, window.innerWidth - 16),
  height: Math.max(1, window.innerHeight - 16),
});

export const tableWindowMethods = {
  openMapBuilderWindow(payload = {}) {
    const campaignId = Number(payload.campaignId || this.currentCampaignId);
    const sceneId = Number(payload.sceneId || this.selectedScene?.id) || null;
    const mapId = Number(payload.mapId) || null;
    if (!Number.isInteger(campaignId) || campaignId < 1) return null;
    const existing = this.panelWindows.find(
      (item) =>
        item.windowType === "map-builder" &&
        Number(item.campaignId) === campaignId,
    );
    if (existing) {
      existing.sceneId = sceneId;
      if (mapId) existing.mapId = mapId;
      existing.minimized = false;
      this.focusUtilityWindow(existing.id);
      return existing;
    }
    const config = TABLE_WINDOW_TYPE_CONFIG.mapBuilder;
    const viewport = viewportBounds();
    const minWidth = Math.min(config.minWidth, viewport.width);
    const minHeight = Math.min(config.minHeight, viewport.height);
    const width = Math.min(viewport.width, Math.max(minWidth, config.width));
    const height = Math.min(
      viewport.height,
      Math.max(minHeight, config.height),
    );
    this.nextWindowZ += 1;
    const panelWindow = {
      id: `map-builder-${campaignId}`,
      panelId: "map-builder",
      windowType: "map-builder",
      labelKey: "vtt.mapBuilder.title",
      icon: "palette",
      campaignId,
      sceneId,
      mapId,
      mapName: String(payload.mapName || "").trim(),
      dirty: false,
      status: "saved",
      x: Math.max(0, Math.round((window.innerWidth - width) / 2)),
      y: Math.max(0, Math.round((window.innerHeight - height) / 2)),
      width,
      height,
      minWidth,
      minHeight,
      maxWidth: viewport.width,
      maxHeight: viewport.height,
      resizable: config.resizable,
      maximizable: config.maximizable,
      constrainToViewport: config.constrainToViewport,
      minimized: false,
      maximized: true,
      z: this.nextWindowZ,
    };
    this.panelWindows.push(panelWindow);
    return panelWindow;
  },
  updateMapBuilderWindow(id, changes = {}) {
    const panelWindow = this.panelWindows.find(
      (item) => item.id === id && item.windowType === "map-builder",
    );
    if (!panelWindow) return;
    for (const field of ["mapId", "mapName", "dirty", "status"]) {
      if (Object.prototype.hasOwnProperty.call(changes, field))
        panelWindow[field] = changes[field];
    }
  },
  openUtilityWindow(id, options = {}) {
    const windowSource = "sidebar";
    const utility = utilityById(id);
    if (!utility) return;
    if (!this.availableUtilityIds.includes(id) && utility.windowOnly !== true)
      return;

    if (utility.drawerOnly) {
      this.activePanelId = id;
      return;
    }

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

    this.activePanelId = "";
    const campaignId = Number(this.currentCampaignId) || this.currentCampaignId;
    const requestedCharacterId = Number(options?.characterId) || null;
    const existing = this.panelWindows.find(
      (item) =>
        item.panelId === id &&
        (item.windowSource || "sidebar") === windowSource &&
        (id !== "calendar" || Number(item.campaignId) === Number(campaignId)) &&
        (id !== "spells" ||
          (Number(item.campaignId) === Number(campaignId) &&
            Number(item.characterId) === requestedCharacterId)),
    );
    if (existing) {
      if (id === "spells" && Number(options?.spellId) > 0) {
        existing.initialSpellId = Number(options.spellId);
      }
      if (id === "token-sync" && Number(options.sourceSceneId) > 0) {
        existing.initialSceneId = Number(options.sourceSceneId);
      }
      if (
        ["journal", "bestiary"].includes(id) &&
        Number(options?.characterId) > 0
      ) {
        existing.characterId = Number(options.characterId);
      }
      existing.minimized = false;
      this.focusUtilityWindow(existing.id);
      return;
    }
    const offset = this.panelWindows.length * 26;
    const preferredWidth = utility.fillViewport
      ? window.innerWidth - 64
      : Number(utility.windowWidth) || 380;
    const preferredHeight = utility.fillViewport
      ? window.innerHeight - 80
      : Number(utility.windowHeight) || 560;
    const viewportWidth = Math.max(
      280,
      Math.min(
        window.innerWidth - 32,
        Number(utility.maxViewportWidthRatio) > 0
          ? Math.floor(window.innerWidth * utility.maxViewportWidthRatio)
          : window.innerWidth - 32,
      ),
    );
    const viewportHeight = Math.max(
      260,
      Math.min(
        window.innerHeight - 48,
        Number(utility.maxViewportHeightRatio) > 0
          ? Math.floor(window.innerHeight * utility.maxViewportHeightRatio)
          : window.innerHeight - 48,
      ),
    );
    const minWidth = Math.min(
      Number(utility.minWindowWidth) || 280,
      viewportWidth,
    );
    const minHeight = Math.min(
      Number(utility.minWindowHeight) || 260,
      viewportHeight,
    );
    const maxWidth = Math.min(
      Number(utility.maxWindowWidth) || viewportWidth,
      viewportWidth,
    );
    const maxHeight = Math.min(
      Number(utility.maxWindowHeight) || viewportHeight,
      viewportHeight,
    );
    let width = Math.min(
      maxWidth,
      Math.max(
        minWidth,
        Math.min(
          preferredWidth,
          window.innerWidth - (utility.fillViewport ? 32 : 96),
        ),
      ),
    );
    let height = Math.min(
      maxHeight,
      Math.max(
        minHeight,
        Math.min(
          preferredHeight,
          window.innerHeight - (utility.fillViewport ? 48 : 128),
        ),
      ),
    );
    const aspectRatio = Number(utility.aspectRatio);
    if (aspectRatio > 0) {
      if (height * aspectRatio <= width) {
        width = height * aspectRatio;
      } else {
        height = width / aspectRatio;
      }
    }
    this.nextWindowZ += 1;
    this.panelWindows.push({
      id:
        id === "calendar"
          ? `utility-${id}-${campaignId}`
          : id === "spells"
            ? `utility-${id}-${campaignId}-${requestedCharacterId}`
            : `utility-${id}`,
      panelId: id,
      windowType: utility.windowType || null,
      windowSource,
      campaignId,
      labelKey: utility.labelKey,
      icon: utility.icon,
      x:
        utility.fillViewport || utility.centered
          ? Math.max(0, Math.round((window.innerWidth - width) / 2))
          : Math.min(72 + offset, Math.max(0, window.innerWidth - width)),
      y:
        utility.fillViewport || utility.centered
          ? Math.max(0, Math.round((window.innerHeight - height) / 2))
          : Math.min(64 + offset, Math.max(0, window.innerHeight - 38)),
      width,
      height,
      minWidth,
      minHeight,
      maxWidth,
      maxHeight,
      maxViewportWidthRatio: utility.maxViewportWidthRatio || null,
      maxViewportHeightRatio: utility.maxViewportHeightRatio || null,
      aspectRatio: aspectRatio > 0 ? aspectRatio : null,
      resizable: utility.resizable === true,
      maximizable: utility.maximizable === true,
      constrainToViewport: utility.constrainToViewport === true,
      footerText: utility.footerText || "",
      characterId:
        ["journal", "bestiary", "spells"].includes(id) &&
        Number(options?.characterId) > 0
          ? Number(options.characterId)
          : null,
      initialSpellId:
        id === "spells" && Number(options?.spellId) > 0
          ? Number(options.spellId)
          : null,
      initialSceneId:
        id === "token-sync" && Number(options.sourceSceneId) > 0
          ? Number(options.sourceSceneId)
          : null,
      minimized: false,
      maximized: false,
      z: this.nextWindowZ,
    });
  },
  openSceneSettingsWindow(payload = {}) {
    const campaignId = Number(payload.campaignId || this.currentCampaignId);
    const numericSceneId = Number(payload.sceneId);
    const sceneId =
      Number.isInteger(numericSceneId) && numericSceneId > 0
        ? numericSceneId
        : null;
    const mode = sceneId ? "edit" : "create";
    if (!Number.isInteger(campaignId) || campaignId < 1) return;

    const existing = this.panelWindows.find(
      (item) =>
        item.windowType === "scene-settings" &&
        Number(item.campaignId) === campaignId &&
        (sceneId
          ? Number(item.sceneId) === sceneId
          : item.mode === "create" && !item.sceneId),
    );
    if (existing) {
      existing.minimized = false;
      this.focusUtilityWindow(existing.id);
      return existing;
    }

    const config = TABLE_WINDOW_TYPE_CONFIG.sceneSettings;
    const viewport = viewportBounds();
    const minWidth = Math.min(config.minWidth, viewport.width);
    const minHeight = Math.min(config.minHeight, viewport.height);
    const width = Math.min(viewport.width, Math.max(minWidth, config.width));
    const height = Math.min(
      viewport.height,
      Math.max(minHeight, config.height),
    );
    this.nextWindowZ += 1;
    const panelWindow = {
      id: `scene-settings-${campaignId}-${sceneId || `new-${this.nextWindowZ}`}`,
      panelId: "scene-settings",
      windowType: "scene-settings",
      labelKey: "vtt.scene.settings.editTitle",
      icon: "scene",
      campaignId,
      sceneId,
      mode,
      sceneName: String(payload.sceneName || "").trim(),
      dirty: false,
      status: "saved",
      x: Math.max(0, Math.round((window.innerWidth - width) / 2)),
      y: Math.max(0, Math.round((window.innerHeight - height) / 2)),
      width,
      height,
      minWidth,
      minHeight,
      maxWidth: viewport.width,
      maxHeight: viewport.height,
      resizable: config.resizable,
      maximizable: config.maximizable,
      constrainToViewport: config.constrainToViewport,
      minimized: false,
      maximized: false,
      z: this.nextWindowZ,
    };
    this.panelWindows.push(panelWindow);
    return panelWindow;
  },
  updateSceneSettingsWindow(id, changes = {}) {
    const panelWindow = this.panelWindows.find(
      (item) => item.id === id && item.windowType === "scene-settings",
    );
    if (!panelWindow) return;
    const allowed = ["sceneId", "mode", "sceneName", "dirty", "status"];
    for (const field of allowed) {
      if (Object.prototype.hasOwnProperty.call(changes, field)) {
        panelWindow[field] = changes[field];
      }
    }
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
    if (focusTableWindow(id)) return;
    this.nextWindowZ += 1;
    panelWindow.z = this.nextWindowZ;
  },
  syncUtilityWindowLayer({ id, z }) {
    const panelWindow = this.panelWindows.find((item) => item.id === id);
    if (panelWindow) panelWindow.z = z;
  },
  moveUtilityWindow({ id, x, y }) {
    const panelWindow = this.panelWindows.find((item) => item.id === id);
    if (panelWindow && !panelWindow.maximized)
      Object.assign(panelWindow, { x, y });
  },
  resizeUtilityWindow({ id, width, height, x, y }) {
    const panelWindow = this.panelWindows.find((item) => item.id === id);
    if (!panelWindow || panelWindow.resizable !== true || panelWindow.maximized)
      return;
    Object.assign(panelWindow, { width, height, x, y });
  },
  toggleUtilityWindow(id) {
    const panelWindow = this.panelWindows.find((item) => item.id === id);
    if (!panelWindow) return;
    panelWindow.minimized = !panelWindow.minimized;
    this.focusUtilityWindow(id);
  },
  toggleMaximizeUtilityWindow(id) {
    const panelWindow = this.panelWindows.find((item) => item.id === id);
    if (!panelWindow || panelWindow.maximizable !== true) return;
    if (!panelWindow.maximized) {
      panelWindow.restoreBounds = {
        x: panelWindow.x,
        y: panelWindow.y,
        width: panelWindow.width,
        height: panelWindow.height,
      };
      panelWindow.maximized = true;
      panelWindow.minimized = false;
    } else {
      Object.assign(panelWindow, panelWindow.restoreBounds || {});
      panelWindow.maximized = false;
      delete panelWindow.restoreBounds;
    }
    this.focusUtilityWindow(id);
  },
  closeUtilityWindow(id) {
    this.panelWindows = this.panelWindows.filter((item) => item.id !== id);
  },
};
