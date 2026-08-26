import { snapTokenPosition } from "@/lib/vtt/grid";
import { tokenMovementPreview } from "@/lib/vtt/tokenMovement";

const pointerValue = (event, field) => {
  const value = Number(event?.[field]);
  return Number.isFinite(value) ? value : 0;
};

const hasNoMovementPoints = (token) => {
  const remaining = Number(token?.movementPoints);
  return (
    token?.capabilities?.canManage !== true &&
    Number.isFinite(remaining) &&
    remaining <= 0
  );
};

const removeListeners = (vm) => {
  window.removeEventListener("pointermove", vm.moveDrag);
  window.removeEventListener("pointerup", vm.finishDrag);
  window.removeEventListener("pointercancel", vm.cancelDrag);
  window.removeEventListener("keydown", vm.modifyDragRoute);
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
    if (hasNoMovementPoints(token)) {
      this.$emit("movement-depleted", token);
      return;
    }
    this.cancelDrag();
    this.drag = {
      id: event.pointerId,
      token,
      target: event.currentTarget,
      clientX: pointerValue(event, "clientX"),
      clientY: pointerValue(event, "clientY"),
      waypoints: [],
      movement: null,
    };
    event.currentTarget.setPointerCapture?.(event.pointerId);
    window.addEventListener("pointermove", this.moveDrag);
    window.addEventListener("pointerup", this.finishDrag);
    window.addEventListener("pointercancel", this.cancelDrag);
    window.addEventListener("keydown", this.modifyDragRoute);
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
    this.drag.movement = tokenMovementPreview(
      this.scene,
      this.drag.token,
      position,
      this.drag.waypoints,
    );
  },
  modifyDragRoute(event) {
    if (
      !this.drag ||
      ![" ", "Backspace", "Delete", "Escape"].includes(event.key)
    ) {
      return;
    }
    event.preventDefault();
    if (event.key === "Escape") {
      this.cancelDrag();
      return;
    }
    if (event.key === " ") {
      const position = this.preview[this.drag.token.id];
      const previous = this.drag.waypoints.at(-1);
      if (
        position &&
        (!previous || previous.x !== position.x || previous.y !== position.y)
      ) {
        this.drag.waypoints.push({ ...position });
      }
    } else {
      this.drag.waypoints.pop();
    }
    const position = this.preview[this.drag.token.id] || this.drag.token;
    this.drag.movement = tokenMovementPreview(
      this.scene,
      this.drag.token,
      position,
      this.drag.waypoints,
    );
  },
  finishDrag(event) {
    if (!this.drag || event.pointerId !== this.drag.id) return;
    this.moveDrag(event);
    const { token, waypoints, movement } = this.drag;
    const position = this.preview[token.id];
    this.clearDrag();
    if (position && (position.x !== token.x || position.y !== token.y)) {
      if (movement?.exceeded && !token.capabilities?.canManage) {
        this.$emit("movement-limit", { token, position, waypoints, movement });
        return;
      }
      this.holdTokenPosition?.(token, position);
      this.$emit("move", {
        token,
        x: position.x,
        y: position.y,
        waypoints,
        cost: movement?.cost || 0,
      });
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
