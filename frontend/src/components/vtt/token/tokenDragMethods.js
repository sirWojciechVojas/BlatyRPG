import { snapTokenPosition } from "@/lib/vtt/grid";
import {
  exceededGroupTokens,
  tokenDragGroup,
  tokenGroupBlockers,
  tokenGroupMovementPreviews,
} from "@/lib/vtt/tokenGroupMovement";

const pointerValue = (event, field) => {
  const value = Number(event?.[field]);
  return Number.isFinite(value) ? value : 0;
};

const exceedsDragThreshold = (drag, event) =>
  Math.hypot(
    pointerValue(event, "clientX") - drag.clientX,
    pointerValue(event, "clientY") - drag.clientY,
  ) >= 5;

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
    this.cancelDrag();
    const group = tokenDragGroup(this.tokens, this.effectiveSelectedIds, token);
    const blockedTokens = tokenGroupBlockers(group);
    this.drag = {
      id: event.pointerId,
      token,
      target: event.currentTarget,
      clientX: pointerValue(event, "clientX"),
      clientY: pointerValue(event, "clientY"),
      waypoints: [],
      movement: null,
      group,
      projections: [],
      blockedTokens,
      blocked: blockedTokens.length > 0,
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
    if (this.drag.blocked) {
      if (!exceedsDragThreshold(this.drag, event)) return;
      const payload =
        this.drag.group.length > 1
          ? {
              group: true,
              tokens: this.drag.blockedTokens,
            }
          : this.drag.token;
      this.clearDrag();
      this.$emit("movement-depleted", payload);
      return;
    }
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
    this.updateDragPreviews(position);
  },
  updateDragPreviews(position) {
    const projections = tokenGroupMovementPreviews(
      this.scene,
      this.drag.group,
      this.drag.token,
      position,
      this.drag.waypoints,
    );
    this.drag.projections = projections;
    this.drag.movement = projections.find(
      ({ token }) => token.id === this.drag.token.id,
    )?.movement;
    this.preview = Object.fromEntries(
      projections.map(({ token, position: target }) => [token.id, target]),
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
    this.updateDragPreviews(position);
  },
  finishDrag(event) {
    if (!this.drag || event.pointerId !== this.drag.id) return;
    this.moveDrag(event);
    if (!this.drag) return;
    const { token, waypoints, movement, group, projections } = this.drag;
    const position = this.preview[token.id];
    this.clearDrag();
    if (position && (position.x !== token.x || position.y !== token.y)) {
      if (group.length > 1) {
        const exceeded = exceededGroupTokens(projections);
        if (exceeded.length) {
          this.$emit("movement-depleted", {
            group: true,
            tokens: exceeded,
          });
          return;
        }
        projections.forEach(({ token: member, position: target }) =>
          this.holdTokenPosition?.(member, target),
        );
        this.$emit("move-group", {
          moves: projections.map(
            ({
              token: member,
              position: target,
              waypoints: route,
              movement,
            }) => ({
              token: member,
              x: target.x,
              y: target.y,
              waypoints: route,
              cost: movement.cost,
            }),
          ),
        });
        return;
      }
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
  isDraggingToken(tokenId) {
    return (this.drag?.group || []).some((token) => token.id === tokenId);
  },
};
