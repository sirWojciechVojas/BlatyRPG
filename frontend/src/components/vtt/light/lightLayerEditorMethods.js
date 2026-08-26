import { wallPoint } from "@/lib/vtt/wallGeometry";
import {
  lightCopyDraft,
  lightDraftFromDrag,
  validLightDraft,
} from "@/lib/vtt/lightInteraction";

export const lightLayerEditorMethods = {
  point(event) {
    return wallPoint(event, this.$refs.editor, this.scene, !event.altKey);
  },
  startCreate(event) {
    if (this.busy || event.button !== 0) return;
    const origin = this.point(event);
    this.$emit("select", null);
    this.drag = { type: "create", pointerId: event.pointerId, origin };
    this.preview = lightDraftFromDrag(origin, origin);
    this.$refs.editor.focus();
    this.$refs.editor.setPointerCapture?.(event.pointerId);
  },
  startMove(event, light) {
    if (this.busy || event.button !== 0) return;
    this.$emit("select", light.id);
    this.drag = { type: "move", pointerId: event.pointerId, light };
    this.preview = { x: light.x, y: light.y };
    this.$refs.editor.focus();
    this.$refs.editor.setPointerCapture?.(event.pointerId);
  },
  move(event) {
    if (!this.drag || event.pointerId !== this.drag.pointerId) return;
    const point = this.point(event);
    this.preview =
      this.drag.type === "create"
        ? lightDraftFromDrag(this.drag.origin, point)
        : point;
  },
  finish(event) {
    if (!this.drag || event.pointerId !== this.drag.pointerId) return;
    this.move(event);
    const drag = this.drag;
    const point = this.preview;
    this.cancel(event);
    if (drag.type === "create") {
      if (validLightDraft(point)) this.$emit("create", point);
      return;
    }
    if (point.x !== drag.light.x || point.y !== drag.light.y) {
      this.$emit("update", { light: drag.light, changes: point });
    }
  },
  cancel(event) {
    if (event && this.drag) {
      this.$refs.editor.releasePointerCapture?.(this.drag.pointerId);
    }
    this.drag = null;
    this.preview = null;
  },
  display(light) {
    return this.drag?.light?.id === light.id ? this.preview : light;
  },
  lightClasses(light) {
    return [
      "scene-light",
      {
        "scene-light--selected": light.id === this.selectedId,
        "scene-light--disabled": !light.enabled,
      },
    ];
  },
  copySelected() {
    if (!this.selectedLight || this.busy) return;
    this.$emit("create", lightCopyDraft(this.selectedLight, this.scene));
  },
  addDefault() {
    if (this.busy) return;
    const grid = Math.max(1, Number(this.scene.gridSize) || 100);
    this.$emit("create", {
      x: Number(this.scene.width) / 2,
      y: Number(this.scene.height) / 2,
      brightRadius: grid * 2,
      dimRadius: grid * 4,
    });
  },
  updateSelected(changes) {
    if (!this.selectedLight || this.busy) return;
    this.$emit("update", { light: this.selectedLight, changes });
  },
  editLight(lightId) {
    this.$emit("select", lightId);
    this.propertiesOpen = true;
  },
  keyboard(event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "d") {
      event.preventDefault();
      this.copySelected();
    } else if (
      ["Delete", "Backspace"].includes(event.key) &&
      this.selectedLight
    ) {
      event.preventDefault();
      this.$emit("delete", this.selectedLight);
    } else if (event.key === "Escape") {
      this.propertiesOpen = false;
      this.$emit("select", null);
    }
  },
};
