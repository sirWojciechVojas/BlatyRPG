import { canvasDropPosition, readDroppedActor } from "@/lib/vtt/tokenDrop";
import { readDroppedTileAsset, tileDraftFromAsset } from "@/lib/vtt/tileDrop";
import { currentActorDrag, endActorDrag } from "@/lib/vtt/actorDragSession";
import {
  currentTokenTemplateDrag,
  endTokenTemplateDrag,
  readDroppedTokenTemplate,
} from "@/lib/vtt/tokenTemplateDragSession";

const dropPosition = (vm, event) =>
  canvasDropPosition(
    event,
    vm.$refs.viewport,
    vm.camera,
    vm.mapDimensions.padding,
  );

export const sceneCanvasDropMethods = {
  previewDrop(event) {
    const template = currentTokenTemplateDrag();
    const actor = currentActorDrag();
    const source = template || actor;
    if (!source || !this.scene || !this.canCreateToken) return;
    const position = dropPosition(this, event);
    const start = this.actorDropPreview?.start || position;
    this.actorDropPreview = {
      ...(template ? { template } : { actor }),
      start,
      ...position,
    };
    if (event.dataTransfer) event.dataTransfer.dropEffect = "copy";
  },
  leaveDropPreview(event) {
    if (this.$refs.viewport?.contains(event.relatedTarget)) return;
    this.clearDropPreview();
  },
  clearDropPreview() {
    this.actorDropPreview = null;
  },
  dropContent(event) {
    if (!this.scene) return;
    const position = dropPosition(this, event);
    const template =
      readDroppedTokenTemplate(event.dataTransfer) ||
      currentTokenTemplateDrag();
    const actor = readDroppedActor(event.dataTransfer) || currentActorDrag();
    this.clearDropPreview();
    endActorDrag();
    endTokenTemplateDrag();
    if (template && this.canCreateToken) {
      this.$emit("token-template-create", { template, ...position });
      return;
    }
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
