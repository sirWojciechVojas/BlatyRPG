<template>
  <aside
    ref="panel"
    class="wall-management"
    :style="panelStyle"
    @pointerdown.stop
  >
    <header
      class="wall-management__handle"
      :title="$t('vtt.wall.dragManager')"
      @pointerdown.prevent="startDrag"
    >
      <strong
        ><span aria-hidden="true">⠿</span>
        {{ $t("vtt.wall.managerTitle") }}</strong
      >
      <button
        type="button"
        :disabled="busy"
        @pointerdown.stop
        @click="$emit('add')"
      >
        ＋ {{ $t("vtt.wall.add") }}
      </button>
    </header>
    <div class="wall-management__global">
      <button
        type="button"
        :class="{ active: allEnabled }"
        :disabled="busy || walls.length === 0"
        @click="$emit('toggle-all-enabled')"
      >
        {{ allEnabled ? $t("vtt.wall.disableAll") : $t("vtt.wall.enableAll") }}
      </button>
      <button
        type="button"
        :class="{ active: allVisible }"
        :disabled="busy || walls.length === 0"
        @click="$emit('toggle-all-visible')"
      >
        {{ allVisible ? $t("vtt.wall.hideAll") : $t("vtt.wall.showAll") }}
      </button>
    </div>
    <p v-if="!walls.length" class="wall-management__empty">
      {{ $t("vtt.wall.empty") }}
    </p>
    <ol v-else>
      <li
        v-for="wall in walls"
        :key="wall.id"
        :class="{
          selected: wall.id === selectedId,
          disabled: !wall.enabled,
          hidden: wall.hidden,
        }"
        @click="$emit('select', wall.id)"
      >
        <input
          type="color"
          class="wall-management__swatch"
          :value="color(wall)"
          :title="$t('vtt.wall.color')"
          :disabled="busy"
          @click.stop
          @change.stop="update(wall, { color: $event.target.value })"
        />
        <button type="button" class="wall-management__name">
          {{ wall.name }}
        </button>
        <span>{{ $t(`vtt.wall.types.${wall.type}`) }}</span>
        <div class="wall-management__actions">
          <button
            v-for="flag in flags"
            :key="flag.field"
            type="button"
            :class="{ active: wall[flag.field] }"
            :title="$t(flag.label)"
            :disabled="busy"
            @click.stop="update(wall, { [flag.field]: !wall[flag.field] })"
          >
            {{ flag.symbol }}
          </button>
          <template
            v-if="
              ['door', 'secret', 'window'].includes(wall.doorType || wall.type)
            "
          >
            <button
              type="button"
              :class="{ active: wall.doorState === 'open' }"
              :title="$t('vtt.wall.toggleDoor')"
              :disabled="busy"
              @click.stop="toggleDoor(wall)"
            >
              ◇
            </button>
            <button
              type="button"
              :class="{ active: wall.doorState === 'locked' }"
              :title="$t('vtt.wall.toggleLock')"
              :disabled="busy"
              @click.stop="toggleLock(wall)"
            >
              ⌑
            </button>
            <button
              type="button"
              :class="{ active: wall.type === 'secret' }"
              :title="$t('vtt.wall.toggleSecret')"
              :disabled="busy"
              @click.stop="
                update(wall, {
                  type: wall.type === 'secret' ? 'door' : 'secret',
                })
              "
            >
              S
            </button>
          </template>
          <button
            type="button"
            :class="{ active: wall.enabled }"
            :title="$t('vtt.wall.enabled')"
            :disabled="busy"
            @click.stop="update(wall, { enabled: !wall.enabled })"
          >
            ◉
          </button>
          <button
            type="button"
            :class="{ active: !wall.hidden }"
            :title="$t('vtt.wall.visible')"
            :disabled="busy"
            @click.stop="update(wall, { hidden: !wall.hidden })"
          >
            ◐
          </button>
          <button
            type="button"
            :title="$t('vtt.wall.focus')"
            @click.stop="$emit('focus', wall.id)"
          >
            ⊙
          </button>
          <button
            type="button"
            :title="$t('vtt.wall.edit')"
            :disabled="busy"
            @click.stop="$emit('edit', wall.id)"
          >
            ⚙
          </button>
          <button
            type="button"
            class="wall-management__danger"
            :title="$t('vtt.wall.delete')"
            :disabled="busy"
            @click.stop="$emit('delete', wall)"
          >
            ×
          </button>
        </div>
      </li>
    </ol>
  </aside>
</template>

<script>
import { wallColor } from "@/lib/vtt/wallGeometry";

export default {
  name: "WallManagementPanel",
  props: {
    walls: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
    allEnabled: { type: Boolean, default: true },
    allVisible: { type: Boolean, default: true },
    busy: { type: Boolean, default: false },
  },
  emits: [
    "select",
    "focus",
    "add",
    "update",
    "edit",
    "delete",
    "toggle-all-enabled",
    "toggle-all-visible",
  ],
  data: () => ({
    position: { left: null, top: 132 },
    drag: null,
    flags: [
      { field: "blocksMovement", symbol: "M", label: "vtt.wall.movement" },
      { field: "blocksSight", symbol: "V", label: "vtt.wall.sight" },
      { field: "blocksLight", symbol: "L", label: "vtt.wall.light" },
    ],
  }),
  computed: {
    panelStyle() {
      if (this.position.left === null) return {};
      return {
        left: `${this.position.left}px`,
        right: "auto",
        top: `${this.position.top}px`,
      };
    },
  },
  mounted() {
    this.$nextTick(this.placeInitially);
    window.addEventListener("resize", this.keepInViewport);
  },
  beforeUnmount() {
    this.stopDrag();
    window.removeEventListener("resize", this.keepInViewport);
  },
  methods: {
    color: wallColor,
    bounds(left, top) {
      const panel = this.$refs.panel;
      const width = panel?.offsetWidth || 0;
      const height = panel?.offsetHeight || 0;
      return {
        left: Math.max(0, Math.min(left, window.innerWidth - width)),
        top: Math.max(0, Math.min(top, window.innerHeight - height)),
      };
    },
    placeInitially() {
      const panel = this.$refs.panel;
      if (!panel) return;
      this.position = this.bounds(
        window.innerWidth - panel.offsetWidth - 16,
        132,
      );
    },
    startDrag(event) {
      if (event.button !== 0 || event.target.closest("button")) return;
      const panel = this.$refs.panel;
      if (!panel) return;
      const rect = panel.getBoundingClientRect();
      this.position = { left: rect.left, top: rect.top };
      this.drag = {
        pointerId: event.pointerId,
        offsetX: event.clientX - rect.left,
        offsetY: event.clientY - rect.top,
      };
      window.addEventListener("pointermove", this.moveDrag);
      window.addEventListener("pointerup", this.stopDrag);
      window.addEventListener("pointercancel", this.stopDrag);
    },
    moveDrag(event) {
      if (!this.drag || event.pointerId !== this.drag.pointerId) return;
      this.position = this.bounds(
        event.clientX - this.drag.offsetX,
        event.clientY - this.drag.offsetY,
      );
    },
    stopDrag(event) {
      if (event && this.drag && event.pointerId !== this.drag.pointerId) return;
      this.drag = null;
      window.removeEventListener("pointermove", this.moveDrag);
      window.removeEventListener("pointerup", this.stopDrag);
      window.removeEventListener("pointercancel", this.stopDrag);
    },
    keepInViewport() {
      if (this.position.left === null) return this.placeInitially();
      this.position = this.bounds(this.position.left, this.position.top);
    },
    update(wall, changes) {
      this.$emit("update", { wall, changes });
    },
    toggleDoor(wall) {
      this.update(wall, {
        doorState: wall.doorState === "open" ? "closed" : "open",
      });
    },
    toggleLock(wall) {
      this.update(wall, {
        doorState: wall.doorState === "locked" ? "closed" : "locked",
      });
    },
  },
};
</script>
