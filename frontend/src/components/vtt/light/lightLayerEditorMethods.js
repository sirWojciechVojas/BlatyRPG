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
    return wallPoint(event, this.$refs.editor, this.scene, !event.shiftKey);
  },
  canvasPointerDown(event) {
    if (event.button !== 0 || this.busy) return;
    this.contextMenu = null;
    if (this.drag?.type === "create") {
      this.finishCreate(event);
      return;
    }
    if (event.ctrlKey || event.metaKey) {
      const origin = wallPoint(event, this.$refs.editor, this.scene, false);
      this.drag = {
        type: "marquee",
        pointerId: event.pointerId,
        origin,
        additive: true,
      };
      this.selectionBox = { x: origin.x, y: origin.y, width: 0, height: 0 };
      this.$refs.editor.setPointerCapture?.(event.pointerId);
      this.$refs.editor.focus();
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
    if (event.ctrlKey || event.metaKey) {
      this.toggleSelection(light);
      return;
    }
    if (!this.selectedIds.map(Number).includes(Number(light.id))) {
      this.selectedIds = [light.id];
      this.$emit("select", light.id);
    }
    const group =
      this.selectedLights.length > 1 ? this.selectedLights : [light];
    this.drag = {
      type: group.length > 1 ? "group" : "move",
      pointerId: event.pointerId,
      light,
      lights: group,
      origin: this.point(event),
    };
    this.preview = { x: light.x, y: light.y };
    this.$refs.editor.focus();
    this.$refs.editor.setPointerCapture?.(event.pointerId);
  },
  move(event) {
    if (!this.drag || event.pointerId !== this.drag.pointerId) return;
    if (this.drag.type === "marquee") {
      const point = wallPoint(event, this.$refs.editor, this.scene, false);
      this.selectionBox = {
        x: Math.min(this.drag.origin.x, point.x),
        y: Math.min(this.drag.origin.y, point.y),
        width: Math.abs(point.x - this.drag.origin.x),
        height: Math.abs(point.y - this.drag.origin.y),
      };
      return;
    }
    const point = this.point(event);
    if (this.drag.type === "group") {
      const dx = point.x - this.drag.origin.x;
      const dy = point.y - this.drag.origin.y;
      this.groupPreview = Object.fromEntries(
        this.drag.lights.map((light) => [
          light.id,
          {
            x: Math.min(
              Number(this.scene.width),
              Math.max(0, Number(light.x) + dx),
            ),
            y: Math.min(
              Number(this.scene.height),
              Math.max(0, Number(light.y) + dy),
            ),
          },
        ]),
      );
      return;
    }
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
    const box = this.selectionBox;
    const groupPreview = this.groupPreview;
    this.cancel(event);
    if (drag.type === "marquee") {
      this.finishMarquee(box, drag.additive);
      return;
    }
    if (drag.type === "group") {
      drag.lights.forEach((light) => {
        const changes = groupPreview[light.id];
        if (changes && (changes.x !== light.x || changes.y !== light.y)) {
          this.$emit("update", { light, changes });
        }
      });
      return;
    }
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
      ...this.creationTemplate,
      name: `${this.$t("vtt.light.defaultName")} ${this.lights.length + 1}`,
      sourceType: this.creationType,
      angle: this.defaultAngle(this.creationType),
      lumens: 800,
      clarity: 0,
    };
  },
  cancel(event) {
    if (event && this.drag && this.drag.type !== "create") {
      this.$refs.editor.releasePointerCapture?.(this.drag.pointerId);
    }
    this.drag = null;
    this.preview = null;
    this.groupPreview = {};
    this.selectionBox = null;
  },
  display(light) {
    if (this.groupPreview[light.id]) {
      return { ...light, ...this.groupPreview[light.id] };
    }
    return this.drag?.light?.id === light.id
      ? { ...light, ...this.preview }
      : light;
  },
  lightClasses(light) {
    return [
      "scene-light",
      {
        "scene-light--selected": this.selectedIds
          .map(Number)
          .includes(Number(light.id)),
        "scene-light--disabled": !light.enabled,
      },
    ];
  },
  copySelected() {
    if (!this.selectedLights.length || this.busy) return;
    this.selectedLights.forEach((light) => this.copyLight(light.id));
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
      clarity: 0,
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
    if (!this.selectedLights.length || this.busy) return;
    this.selectedLights.forEach((light) =>
      this.$emit("update", { light, changes }),
    );
  },
  editLight(lightId) {
    this.$emit("select", lightId);
    this.$nextTick(() => this.openProperties());
  },
  keyboard(event) {
    if (
      (event.ctrlKey || event.metaKey) &&
      event.key.toLowerCase() === "c" &&
      this.selectedLight
    ) {
      event.preventDefault();
      this.clipboard = this.selectedLights.map((light) => ({ ...light }));
    } else if (
      (event.ctrlKey || event.metaKey) &&
      event.key.toLowerCase() === "v" &&
      this.clipboard?.length
    ) {
      event.preventDefault();
      const offset = Math.max(4, Number(this.scene.gridSize) / 4 || 25);
      this.clipboard.forEach((light) =>
        this.$emit("create", {
          ...lightCopyDraft(light, this.scene),
          name: `${light.name} ${this.$t("vtt.light.copySuffix")}`,
          x: Math.min(this.scene.width, Number(light.x) + offset),
          y: Math.min(this.scene.height, Number(light.y) + offset),
        }),
      );
    } else if (
      ["Delete", "Backspace"].includes(event.key) &&
      this.selectedLights.length
    ) {
      event.preventDefault();
      this.deleteSelected();
    } else if (event.key === "Escape") {
      if (this.drag?.type === "create") this.cancel();
      this.contextMenu = null;
      this.closeProperties();
      this.$emit("select", null);
    }
  },
  toggleSelection(light) {
    const id = Number(light.id);
    const ids = this.selectedIds.map(Number);
    this.selectedIds = ids.includes(id)
      ? ids.filter((value) => value !== id)
      : [...ids, id];
    this.$emit("select", this.selectedIds.at(-1) || null);
  },
  finishMarquee(box, additive) {
    if (!box) return;
    const selected = this.lights
      .filter(
        (light) =>
          Number(light.x) >= box.x &&
          Number(light.x) <= box.x + box.width &&
          Number(light.y) >= box.y &&
          Number(light.y) <= box.y + box.height,
      )
      .map((light) => light.id);
    this.selectedIds = additive
      ? [...new Set([...this.selectedIds, ...selected])]
      : selected;
    this.$emit("select", this.selectedIds.at(-1) || null);
  },
  deleteSelected() {
    if (!this.selectedLights.length) return;
    if (!window.confirm(this.$t("vtt.light.deleteConfirm"))) return;
    this.selectedLights.forEach((light) =>
      this.$emit("delete", { light, confirmed: true }),
    );
    this.selectedIds = [];
    this.$emit("select", null);
  },
};
