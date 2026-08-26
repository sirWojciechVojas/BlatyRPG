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
};
