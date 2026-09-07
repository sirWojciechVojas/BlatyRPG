import { buildGridPattern } from "@/lib/vtt/grid";

export const sceneCanvasComputed = {
  pattern() {
    return this.scene ? buildGridPattern(this.scene) : null;
  },
  mapDimensions() {
    if (!this.scene) return { width: 0, height: 0, padding: 0 };
    const padding = Math.max(0, Number(this.scene.padding) || 0);
    return {
      width: this.scene.width + padding * 2,
      height: this.scene.height + padding * 2,
      padding,
    };
  },
  mapStyle() {
    if (!this.scene) return {};
    return {
      width: `${this.mapDimensions.width}px`,
      height: `${this.mapDimensions.height}px`,
      backgroundColor: this.scene.backgroundColor,
      transform: `translate(${this.camera.x}px, ${this.camera.y}px) scale(${this.camera.scale})`,
    };
  },
  contentStyle() {
    return {
      top: `${this.mapDimensions.padding}px`,
      left: `${this.mapDimensions.padding}px`,
      width: `${this.scene?.width || 0}px`,
      height: `${this.scene?.height || 0}px`,
    };
  },
  tokenAreaSelectionActive() {
    return this.activeTool === "tokens" && this.tokenSelectionMode !== "point";
  },
  displayTokens() {
    if (!this.fogVisibility.constrained) {
      if (this.scene?.fogEnabled === true && !this.canManageScene) {
        return this.tokens.filter((token) => token.capabilities?.canControl);
      }
      return this.tokens;
    }
    const visible = new Set(this.fogVisibility.visibleTokenIds);
    return this.tokens.filter(
      (token) => visible.has(token.id) || token.capabilities?.canControl,
    );
  },
  fogTokens() {
    return this.tokens.map((token) => ({
      ...token,
      ...(this.tokenVisionPreviews[token.id] || {}),
      ...(this.tokenVisionAnglePreviews[token.id] || {}),
    }));
  },
  visionShapeTokens() {
    if (this.canManageScene && this.fogPreview?.mode === "gm")
      return this.fogTokens;
    const ids = new Set(
      this.fogVisibility.visionTokenIds?.length
        ? this.fogVisibility.visionTokenIds.map(String)
        : this.fogTokens
            .filter((token) => token.capabilities?.canControl)
            .map((token) => String(token.id)),
    );
    return this.fogTokens.filter((token) => ids.has(String(token.id)));
  },
  activeMovementTokenId() {
    if (this.movementModeTokenId === null) return null;
    const ids = this.selectedTokenIds.length
      ? this.selectedTokenIds
      : this.selectedTokenId === null
        ? []
        : [this.selectedTokenId];
    if (
      ids.length !== 1 ||
      String(ids[0]) !== String(this.movementModeTokenId) ||
      !["select", "tokens"].includes(this.activeTool)
    )
      return null;
    return this.movementModeTokenId;
  },
};
