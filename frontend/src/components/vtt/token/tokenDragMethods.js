import { snapTokenPosition } from "@/lib/vtt/grid";

const pointerValue = (event, field) => {
  const value = Number(event?.[field]);
  return Number.isFinite(value) ? value : 0;
};

const removeListeners = (vm) => {
  window.removeEventListener("pointermove", vm.moveDrag);
  window.removeEventListener("pointerup", vm.finishDrag);
  window.removeEventListener("pointercancel", vm.cancelDrag);
};

const releasePointer = (drag) => {
  if (!drag?.target?.releasePointerCapture) return;
  try {
    if (
      !drag.target.hasPointerCapture ||
      drag.target.hasPointerCapture(drag.id)
    ) {
      drag.target.releasePointerCapture(drag.id);
    }
  } catch (_error) {
    // The browser may already have released capture after pointerup/cancel.
  }
};

export const tokenDragMethods = {
  startDrag(event, token) {
    if (
      this.busy ||
      token.disabled ||
      !token.capabilities?.canControl ||
      token.locked ||
      event.button !== 0
    ) {
      return;
    }
    this.cancelDrag();
    this.drag = {
      id: event.pointerId,
      token,
      target: event.currentTarget,
      clientX: pointerValue(event, "clientX"),
      clientY: pointerValue(event, "clientY"),
    };
    event.currentTarget.setPointerCapture?.(event.pointerId);
    window.addEventListener("pointermove", this.moveDrag);
    window.addEventListener("pointerup", this.finishDrag);
    window.addEventListener("pointercancel", this.cancelDrag);
    event.preventDefault();
  },
  moveDrag(event) {
    if (!this.drag || event.pointerId !== this.drag.id) return;
    const scale = Math.max(0.05, Number(this.scale) || 1);
    const position = snapTokenPosition(
      this.scene,
      {
        x:
          this.drag.token.x +
          (pointerValue(event, "clientX") - this.drag.clientX) / scale,
        y:
          this.drag.token.y +
          (pointerValue(event, "clientY") - this.drag.clientY) / scale,
      },
      this.drag.token,
    );
    this.preview = {
      ...this.preview,
      [this.drag.token.id]: position,
    };
  },
  finishDrag(event) {
    if (!this.drag || event.pointerId !== this.drag.id) return;
    this.moveDrag(event);
    const { token } = this.drag;
    const position = this.preview[token.id];
    this.clearDrag();
    if (position && (position.x !== token.x || position.y !== token.y)) {
      this.holdTokenPosition?.(token, position);
      this.$emit("move", { token, x: position.x, y: position.y });
    }
  },
  cancelDrag(event) {
    if (event && this.drag && event.pointerId !== this.drag.id) return;
    this.clearDrag();
  },
  clearDrag() {
    const drag = this.drag;
    removeListeners(this);
    releasePointer(drag);
    this.drag = null;
    this.preview = {};
  },
};
