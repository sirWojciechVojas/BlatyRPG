<template>
  <form
    ref="panel"
    class="wall-properties"
    :style="panelStyle"
    @submit.prevent="save"
    @pointerdown.stop
  >
    <header
      class="wall-properties__handle"
      :title="$t('vtt.wall.dragProperties')"
      @pointerdown.prevent="startDrag"
    >
      <strong>{{ $t("vtt.wall.properties") }}</strong>
      <button
        type="button"
        :title="$t('vtt.wall.close')"
        @pointerdown.stop
        @click="$emit('close')"
      >
        ×
      </button>
    </header>
    <div class="wall-properties__grid">
      <label class="wall-properties__wide">
        {{ $t("vtt.wall.name") }}
        <input v-model.trim="form.name" maxlength="150" required />
      </label>
      <label>
        {{ $t("vtt.wall.type") }}
        <select v-model="form.type" :disabled="!canChangeType">
          <option v-for="type in types" :key="type" :value="type">
            {{ $t(`vtt.wall.types.${type}`) }}
          </option>
        </select>
      </label>
      <label>
        {{ $t("vtt.wall.doorState") }}
        <select v-model="form.doorState" :disabled="!doorLike">
          <option v-for="state in states" :key="state" :value="state">
            {{ $t(`vtt.wall.states.${state}`) }}
          </option>
        </select>
      </label>
      <label v-for="coordinate in coordinates" :key="coordinate">
        {{ coordinate.toUpperCase() }}
        <input
          v-model.number="form[coordinate]"
          type="number"
          min="0"
          step="0.5"
        />
      </label>
      <label>
        {{ $t("vtt.wall.color") }}
        <input v-model="form.color" type="color" />
      </label>
      <label class="wall-properties__custom-color">
        <input v-model="form.customColor" type="checkbox" />
        {{ $t("vtt.wall.customColor") }}
      </label>
    </div>
    <div class="wall-properties__toggles">
      <label v-for="flag in flags" :key="flag.field">
        <input v-model="form[flag.field]" type="checkbox" />
        {{ $t(flag.label) }}
      </label>
    </div>
    <footer>
      <button type="button" @click="reset">{{ $t("vtt.wall.cancel") }}</button>
      <button type="submit" :disabled="busy">{{ $t("vtt.wall.save") }}</button>
    </footer>
  </form>
</template>

<script>
import { wallColor } from "@/lib/vtt/wallGeometry";

const snapshot = (wall) => ({
  name: wall.name,
  type: wall.type,
  doorState: wall.doorState || "closed",
  x1: wall.x1,
  y1: wall.y1,
  x2: wall.x2,
  y2: wall.y2,
  color: wall.color || wallColor(wall),
  customColor: Boolean(wall.color),
  blocksMovement: wall.blocksMovement,
  blocksSight: wall.blocksSight,
  blocksLight: wall.blocksLight,
  enabled: wall.enabled,
  hidden: wall.hidden,
});

export default {
  name: "WallPropertiesPanel",
  props: {
    wall: { type: Object, required: true },
    busy: { type: Boolean, default: false },
  },
  emits: ["save", "close"],
  data() {
    return {
      form: snapshot(this.wall),
      position: { left: null, top: 132 },
      drag: null,
      states: ["closed", "open", "locked"],
      coordinates: ["x1", "y1", "x2", "y2"],
      flags: [
        { field: "blocksMovement", label: "vtt.wall.movement" },
        { field: "blocksSight", label: "vtt.wall.sight" },
        { field: "blocksLight", label: "vtt.wall.light" },
        { field: "enabled", label: "vtt.wall.enabled" },
        { field: "hidden", label: "vtt.wall.hidden" },
      ],
    };
  },
  watch: {
    "wall.revision": "reset",
  },
  computed: {
    panelStyle() {
      if (this.position.left === null) return {};
      return {
        left: `${this.position.left}px`,
        right: "auto",
        top: `${this.position.top}px`,
      };
    },
    types() {
      return ["door", "secret"].includes(this.wall.type)
        ? ["door", "secret"]
        : [this.wall.type];
    },
    canChangeType() {
      return ["door", "secret"].includes(this.wall.type);
    },
    doorLike() {
      return ["door", "secret"].includes(this.form.type);
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
      this.position = this.bounds(16, 132);
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
    reset() {
      this.form = snapshot(this.wall);
    },
    save() {
      const current = snapshot(this.wall);
      const candidate = {
        ...this.form,
        doorState: this.doorLike ? this.form.doorState : "closed",
        color: this.form.customColor ? this.form.color : null,
      };
      delete candidate.customColor;
      const changes = Object.fromEntries(
        Object.entries(candidate).filter(([key, value]) => {
          const previous = key === "color" ? this.wall.color : current[key];
          return value !== previous;
        }),
      );
      if (Object.keys(changes).length) this.$emit("save", changes);
      else this.$emit("close");
    },
  },
};
</script>
