import { canvasDropPosition } from "@/lib/vtt/tokenDrop";
import {
  clampTokenSelectionPoint,
  selectableTokensInArea,
} from "@/lib/vtt/tokenAreaSelection";

export const TOKEN_SELECTION_MODES = Object.freeze([
  "point",
  "rectangle",
  "circle",
  "polygon",
]);

const additive = (event) =>
  event.ctrlKey === true || event.metaKey === true || event.shiftKey === true;

const samePoint = (left, right) =>
  left &&
  right &&
  Math.abs(left.x - right.x) < 0.001 &&
  Math.abs(left.y - right.y) < 0.001;

const selectionPoint = (vm, event) =>
  clampTokenSelectionPoint(
    canvasDropPosition(
      event,
      vm.$refs.viewport,
      vm.camera,
      vm.mapDimensions.padding,
    ),
    vm.scene,
  );

const areaModeActive = (vm) =>
  vm.activeTool === "tokens" && vm.tokenSelectionMode !== "point";

const releasePointer = (vm, pointerId) => {
  try {
    vm.$refs.viewport?.releasePointerCapture?.(pointerId);
  } catch (_error) {
    // Pointer capture may already be released after pointercancel.
  }
};

export const tokenAreaSelectionMethods = {
  setTokenSelectionMode(mode) {
    this.cancelTokenAreaSelection();
    this.tokenSelectionMode = TOKEN_SELECTION_MODES.includes(mode)
      ? mode
      : "point";
    this.$refs.viewport?.focus?.();
  },
  startTokenAreaSelection(event) {
    if (!areaModeActive(this) || event.button !== 0) return false;
    if (this.tokenSelectionMode === "polygon") return true;
    const point = selectionPoint(this, event);
    this.tokenSelection = {
      type: this.tokenSelectionMode,
      start: point,
      current: point,
      pointerId: event.pointerId,
      additive: additive(event),
    };
    event.currentTarget.setPointerCapture?.(event.pointerId);
    event.preventDefault?.();
    return true;
  },
  moveTokenAreaSelection(event) {
    if (!areaModeActive(this)) return false;
    if (this.tokenSelectionMode === "polygon") {
      if (this.dragging) return false;
      if (this.tokenSelection?.points?.length) {
        this.tokenSelection = {
          ...this.tokenSelection,
          current: selectionPoint(this, event),
        };
      }
      return true;
    }
    if (this.tokenSelection?.pointerId !== event.pointerId) return false;
    this.tokenSelection = {
      ...this.tokenSelection,
      current: selectionPoint(this, event),
    };
    return true;
  },
  endTokenAreaSelection(event) {
    if (!areaModeActive(this)) return false;
    if (this.tokenSelectionMode === "polygon") {
      return !this.dragging && event.button === 0;
    }
    if (this.tokenSelection?.pointerId !== event.pointerId) return false;
    if (event.type === "pointercancel") {
      this.cancelTokenAreaSelection();
      return true;
    }
    this.tokenSelection = {
      ...this.tokenSelection,
      current: selectionPoint(this, event),
    };
    releasePointer(this, event.pointerId);
    this.commitTokenAreaSelection();
    return true;
  },
  addTokenPolygonPoint(event) {
    if (
      !areaModeActive(this) ||
      this.tokenSelectionMode !== "polygon" ||
      event.button !== 0
    ) {
      return;
    }
    const point = selectionPoint(this, event);
    const points = [...(this.tokenSelection?.points || [])];
    if (!samePoint(points.at(-1), point)) points.push(point);
    this.tokenSelection = {
      type: "polygon",
      points,
      current: point,
      additive: this.tokenSelection?.additive ?? additive(event),
    };
  },
  finishTokenPolygonSelection(event) {
    if (!areaModeActive(this) || this.tokenSelectionMode !== "polygon") return;
    event?.preventDefault?.();
    if ((this.tokenSelection?.points?.length || 0) >= 3) {
      this.commitTokenAreaSelection();
    }
  },
  commitTokenAreaSelection() {
    if (!this.tokenSelection) return;
    const tokenIds = selectableTokensInArea(
      this.tokens,
      this.tokenSelection,
    ).map(({ id }) => id);
    this.$emit("token-select", {
      tokenIds,
      additive: this.tokenSelection.additive === true,
    });
    this.tokenSelection = null;
  },
  cancelTokenAreaSelection() {
    releasePointer(this, this.tokenSelection?.pointerId);
    this.tokenSelection = null;
  },
  handleTokenAreaSelectionKey(event) {
    if (!areaModeActive(this)) return false;
    if (event.key === "Escape") {
      this.cancelTokenAreaSelection();
    } else if (event.key === "Enter" && this.tokenSelectionMode === "polygon") {
      this.finishTokenPolygonSelection(event);
    } else if (
      ["Backspace", "Delete"].includes(event.key) &&
      this.tokenSelectionMode === "polygon" &&
      this.tokenSelection?.points?.length
    ) {
      const points = this.tokenSelection.points.slice(0, -1);
      this.tokenSelection = points.length
        ? { ...this.tokenSelection, points }
        : null;
    } else {
      return false;
    }
    event.preventDefault();
    return true;
  },
};
