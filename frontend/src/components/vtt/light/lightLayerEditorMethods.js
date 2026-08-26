import { wallPoint } from "@/lib/vtt/wallGeometry";
import {
  lightCopyDraft,
  lightDraftFromDrag,
  validLightDraft,
} from "@/lib/vtt/lightInteraction";

export const lightLayerEditorMethods = {
  defaultAngle(sourceType) {
    if (sourceType === "cone") return 60;
    if (sourceType === "directional") return 120;
    return 360;
  },
  point(event) {
    return wallPoint(event, this.$refs.editor, this.scene, !event.altKey);
  },
  canvasPointerDown(event) {
    if (event.button !== 0 || this.busy) return;
    this.contextMenu = null;
    if (this.drag?.type === "create") {
      this.finishCreate(event);
      return;
    }
    this.startCreate(event);
  },
  startCreate(event) {
    if (this.busy || event.button !== 0) return;
    const origin = this.point(event);
    this.$emit("select", null);
    this.drag = { type: "create", pointerId: event.pointerId, origin };
    this.preview = lightDraftFromDrag(origin, origin, this.creationType);
    this.$refs.editor.focus();
  },
  startMove(event, light) {
    if (this.busy || event.button !== 0) return;
    if (this.drag?.type === "create") {
      this.finishCreate(event);
      return;
    }
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
        ? lightDraftFromDrag(this.drag.origin, point, this.creationType)
        : point;
  },
  finish(event) {
    if (!this.drag || event.pointerId !== this.drag.pointerId) return;
    if (this.drag.type === "create") return;
    this.move(event);
    const drag = this.drag;
    const point = this.preview;
    this.cancel(event);
    if (point.x !== drag.light.x || point.y !== drag.light.y) {
      this.$emit("update", { light: drag.light, changes: point });
    }
  },
  finishCreate(event) {
    this.move(event);
    const point = this.preview;
    this.cancel();
    if (!validLightDraft(point)) return;
    this.$emit("create", this.lightDraft(point));
  },
  lightDraft(point) {
    return {
      ...point,
      name: `${this.$t("vtt.light.defaultName")} ${this.lights.length + 1}`,
      sourceType: this.creationType,
      angle: this.defaultAngle(this.creationType),
      lumens: 800,
    };
  },
  cancel(event) {
    if (event && this.drag?.type === "move") {
      this.$refs.editor.releasePointerCapture?.(this.drag.pointerId);
    }
    this.drag = null;
    this.preview = null;
  },
  display(light) {
    return this.drag?.light?.id === light.id
      ? { ...light, ...this.preview }
      : light;
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
    this.copyLight(this.selectedLight.id);
  },
  addDefault() {
    if (this.busy) return;
    this.addAt({
      x: Number(this.scene.width) / 2,
      y: Number(this.scene.height) / 2,
    });
  },
  addAt(point) {
    const grid = Math.max(1, Number(this.scene.gridSize) || 100);
    this.$emit("create", {
      ...point,
      brightRadius: grid * 2,
      dimRadius: grid * 4,
      name: `${this.$t("vtt.light.defaultName")} ${this.lights.length + 1}`,
      sourceType: this.creationType,
      angle: this.defaultAngle(this.creationType),
      lumens: 800,
    });
  },
  openContextMenu(event) {
    if (this.drag) {
      this.cancel(event);
      return;
    }
    const bounds = this.$refs.root.getBoundingClientRect();
    this.contextMenu = {
      left: Math.max(
        4,
        Math.min(bounds.width - 184, event.clientX - bounds.left),
      ),
      top: Math.max(
        4,
        Math.min(bounds.height - 230, event.clientY - bounds.top),
      ),
      point: this.point(event),
    };
  },
  closeContext(action) {
    action?.();
    this.contextMenu = null;
  },
  addAtContext() {
    this.closeContext(() => this.addAt(this.contextMenu.point));
  },
  contextSourceType(sourceType) {
    this.closeContext(() => this.setSourceType(sourceType));
  },
  toggleListFromContext() {
    this.closeContext(() => {
      this.listOpen = !this.listOpen;
    });
  },
  updateFromContext(changes) {
    this.closeContext(() => this.updateSelected(changes));
  },
  copyFromContext() {
    this.closeContext(() => this.copySelected());
  },
  editFromContext() {
    this.closeContext(() => this.openProperties());
  },
  deleteFromContext() {
    this.closeContext(() => this.$emit("delete", this.selectedLight));
  },
  setSourceType(sourceType) {
    this.creationType = sourceType;
    if (this.selectedLight) {
      this.updateSelected({
        sourceType,
        angle: this.defaultAngle(sourceType),
      });
    }
  },
  copyLight(lightId) {
    const light = this.lights.find((item) => item.id === lightId);
    if (!light || this.busy) return;
    this.$emit("create", {
      ...lightCopyDraft(light, this.scene),
      name: `${light.name} ${this.$t("vtt.light.copySuffix")}`,
    });
  },
  updateSelected(changes) {
    if (!this.selectedLight || this.busy) return;
    this.$emit("update", { light: this.selectedLight, changes });
  },
  editLight(lightId) {
    this.$emit("select", lightId);
    this.$nextTick(() => this.openProperties());
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
      if (this.drag?.type === "create") this.cancel();
      this.contextMenu = null;
      this.closeProperties();
      this.$emit("select", null);
    }
  },
};
