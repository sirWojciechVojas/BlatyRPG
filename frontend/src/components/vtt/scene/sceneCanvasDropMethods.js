import { canvasDropPosition, readDroppedActor } from "@/lib/vtt/tokenDrop";
import { readDroppedTileAsset, tileDraftFromAsset } from "@/lib/vtt/tileDrop";

export const sceneCanvasDropMethods = {
  dropContent(event) {
    if (!this.scene) return;
    const position = canvasDropPosition(
      event,
      this.$refs.viewport,
      this.camera,
      this.mapDimensions.padding,
    );
    const actor = readDroppedActor(event.dataTransfer);
    if (actor && this.canCreateToken) {
      this.$emit("token-create", { actor, ...position });
      return;
    }
    const asset = readDroppedTileAsset(event.dataTransfer);
    if (asset && this.canManageTiles) {
      this.$emit(
        "tile-create",
        tileDraftFromAsset(asset, position, this.scene),
      );
    }
  },
};
