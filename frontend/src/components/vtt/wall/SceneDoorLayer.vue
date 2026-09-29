<template>
  <svg
    class="scene-door-layer"
    :viewBox="`0 0 ${scene.width} ${scene.height}`"
    aria-label="Doors"
  >
    <g
      v-for="wall in doors"
      :key="wall.id"
      class="scene-door"
      :class="[
        `scene-door--${wall.doorState || 'closed'}`,
        `scene-door--type-${doorType(wall)}`,
        `scene-door--animation-${wall.animationConfig?.type || 'none'}`,
      ]"
      :style="animationStyle(wall)"
    >
      <line
        class="scene-door__segment"
        :x1="wall.x1"
        :y1="wall.y1"
        :x2="wall.x2"
        :y2="wall.y2"
        @dblclick.stop.prevent="edit(wall)"
      />
      <g
        class="scene-door__control"
        :class="{
          'scene-door__control--disabled': interactionBlocked(wall),
          'scene-door__control--secret': doorType(wall) === 'secret',
        }"
        :transform="controlTransform(wall)"
        role="button"
        :tabindex="interactionBlocked(wall) ? -1 : 0"
        :aria-label="controlTooltip(wall)"
        :aria-disabled="interactionBlocked(wall)"
        @pointerdown.stop
        @click.stop.prevent
        @dblclick.stop.prevent="edit(wall)"
        @keydown.enter.stop.prevent="toggle(wall)"
        @keydown.space.stop.prevent="toggle(wall)"
        @contextmenu.stop.prevent="handleContextMenu($event, wall)"
      >
        <title>{{ controlTooltip(wall) }}</title>
        <circle class="scene-door__control-hit" r="22" />
        <circle class="scene-door__control-ring" r="17" />
        <circle class="scene-door__control-face" r="13.5" />
        <path class="scene-door__frame" d="M-7-9H7V9H-7Z" />
        <path
          class="scene-door__leaf"
          :d="
            wall.doorState === 'open'
              ? 'M-6.5-8L3.5-4.8V4.8L-6.5 8Z'
              : 'M-6.5-8H5.5V8H-6.5Z'
          "
        />
        <circle
          class="scene-door__knob"
          :cx="wall.doorState === 'open' ? 0.7 : 2.7"
          cy="0"
          r="1.15"
        />
        <g v-if="wall.doorState === 'locked'" class="scene-door__lock">
          <path d="M-3-1V-3.2A3 3 0 016-3.2V-1" />
          <rect x="-4" y="-1" width="8" height="6.5" rx="1.3" />
        </g>
        <path
          v-if="interactionBlocked(wall)"
          class="scene-door__blocked-mark"
          d="M-10-10L10 10"
        />
        <path
          v-else-if="doorType(wall) === 'secret'"
          class="scene-door__secret-mark"
          d="M-2.8 12.2L0 9.4L2.8 12.2L0 15Z"
        />
      </g>
    </g>
  </svg>
  <Teleport v-if="contextMenu" to="body">
    <div
      class="scene-door-context-menu"
      :style="{ left: `${contextMenu.x}px`, top: `${contextMenu.y}px` }"
      role="menu"
      @pointerdown.stop
      @contextmenu.prevent
    >
      <button type="button" role="menuitem" @click="chooseContext(false)">
        {{
          contextMenu.wall.doorState === "open"
            ? $t("vtt.wall.doorControl.closeNormally")
            : $t("vtt.wall.doorControl.openNormally")
        }}
      </button>
      <button
        v-if="contextMenu.wall.doorState !== 'open'"
        type="button"
        role="menuitem"
        @click="chooseContext(true)"
      >
        {{ $t("vtt.wall.doorControl.openSilently") }}
      </button>
    </div>
  </Teleport>
</template>

<script>
import { wallMidpoint } from "@/lib/vtt/wallGeometry";
import {
  actingTokenIds,
  secretDoorBlockedBySelection,
  wallDoorType,
} from "@/lib/vtt/doorInteraction";

export default {
  name: "SceneDoorLayer",
  props: {
    scene: { type: Object, required: true },
    walls: { type: Array, default: () => [] },
    scale: { type: Number, default: 1 },
    canManage: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
    selectedTokens: { type: Array, default: () => [] },
    characters: { type: Array, default: () => [] },
    currentUserId: { type: [Number, String], default: null },
  },
  emits: ["interact", "edit"],
  data: () => ({ contextMenu: null }),
  computed: {
    doors() {
      return this.walls.filter(
        (wall) =>
          wall.enabled !== false &&
          ["door", "secret", "window"].includes(wallDoorType(wall)),
      );
    },
  },
  mounted() {
    window.addEventListener("pointerdown", this.closeContext);
    window.addEventListener("keydown", this.handleWindowKeydown);
  },
  beforeUnmount() {
    window.removeEventListener("pointerdown", this.closeContext);
    window.removeEventListener("keydown", this.handleWindowKeydown);
  },
  methods: {
    midpoint: wallMidpoint,
    doorType: wallDoorType,
    interactionBlocked(wall) {
      return (
        (!this.canManage &&
          (wallDoorType(wall) === "secret" || wall.playerOperable === false)) ||
        secretDoorBlockedBySelection(
          wall,
          this.selectedTokens,
          this.characters,
          this.currentUserId,
        )
      );
    },
    controlTransform(wall) {
      const point = wallMidpoint(wall);
      const inverseScale = 1 / Math.max(0.05, Number(this.scale) || 1);
      return `translate(${point.x} ${point.y}) scale(${inverseScale})`;
    },
    controlTooltip(wall) {
      if (!this.canManage && wall.playerOperable === false) {
        return this.$t("vtt.wall.doorControl.playerForbidden");
      }
      if (this.interactionBlocked(wall)) {
        return this.$t("vtt.wall.doorControl.secretPlayerTokenBlocked");
      }
      const action =
        wall.doorState === "open"
          ? this.$t("vtt.wall.doorControl.close")
          : wall.doorState === "locked" && !this.canManage
            ? this.$t("vtt.wall.doorControl.lockedAttempt")
            : this.$t("vtt.wall.doorControl.open");
      return this.canManage
        ? `${action}. ${this.$t("vtt.wall.doorControl.gmModifier")}`
        : action;
    },
    toggle(wall, silent = false) {
      if (this.busy || this.interactionBlocked(wall)) return;
      this.$emit("interact", {
        wall,
        doorState: wall.doorState === "open" ? "closed" : "open",
        actingTokenIds: actingTokenIds(this.selectedTokens),
        silent: Boolean(silent),
      });
    },
    edit(wall) {
      if (this.canManage) this.$emit("edit", wall.id);
    },
    handleContextMenu(event, wall) {
      this.closeContext();
      if (event.shiftKey && this.canManage) {
        this.lock(wall);
        return;
      }
      if (
        this.canManage &&
        wallDoorType(wall) === "secret" &&
        !this.interactionBlocked(wall)
      ) {
        this.contextMenu = {
          x: Math.min(event.clientX, window.innerWidth - 220),
          y: Math.min(event.clientY, window.innerHeight - 110),
          wall,
        };
        return;
      }
      this.toggle(wall);
    },
    chooseContext(silent) {
      const wall = this.contextMenu?.wall;
      this.closeContext();
      if (wall) this.toggle(wall, silent);
    },
    closeContext() {
      this.contextMenu = null;
    },
    handleWindowKeydown(event) {
      if (event.key === "Escape") this.closeContext();
    },
    lock(wall) {
      if (this.busy || !this.canManage || this.interactionBlocked(wall)) return;
      this.$emit("interact", {
        wall,
        doorState: wall.doorState === "locked" ? "closed" : "locked",
        actingTokenIds: actingTokenIds(this.selectedTokens),
      });
    },
    animationStyle(wall) {
      const config = wall.animationConfig || {};
      return {
        "--door-duration": `${Math.max(50, Number(config.duration) || 250)}ms`,
        "--door-strength": Math.max(
          0,
          Math.min(2, Number(config.strength) || 1),
        ),
        "--door-direction": config.direction === "reverse" ? -1 : 1,
      };
    },
  },
};
</script>
