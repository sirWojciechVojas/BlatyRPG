<template>
  <div
    class="jukebox-volume-control"
    :data-open="open"
    @keydown.esc.stop="open = false"
  >
    <button
      type="button"
      class="jukebox-volume-control__toggle"
      :aria-expanded="open"
      :aria-controls="popoverId"
      :aria-label="`${label}: ${percentage}%`"
      :title="`${label}: ${percentage}%`"
      @click.stop="open = !open"
    >
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M4 9h4l5-4v14l-5-4H4V9Z" fill="currentColor" />
        <path
          v-if="muted || normalized === 0"
          d="m16 9 5 6m0-6-5 6"
          fill="none"
          stroke="currentColor"
          stroke-linecap="round"
          stroke-width="2"
        />
        <template v-else>
          <path
            d="M16 9.25a4 4 0 0 1 0 5.5"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-width="1.8"
          />
          <path
            v-if="normalized > maximum / 2"
            d="M18.5 6.75a7.5 7.5 0 0 1 0 10.5"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-width="1.8"
          />
        </template>
      </svg>
    </button>
    <div
      v-if="open"
      :id="popoverId"
      class="jukebox-volume-control__popover"
      :class="{
        'jukebox-volume-control__popover--with-mute': allowMute,
      }"
      role="group"
      :aria-label="label"
      @click.stop
    >
      <output>{{ percentage }}%</output>
      <input
        :value="normalized"
        type="range"
        min="0"
        :max="maximum"
        step="0.01"
        orient="vertical"
        aria-orientation="vertical"
        :aria-label="label"
        @input="preview($event.target.value)"
        @change="commit($event.target.value)"
      />
      <button
        v-if="allowMute"
        type="button"
        class="jukebox-volume-control__mute"
        :class="{ active: muted }"
        :aria-label="muted ? unmuteLabel : muteLabel"
        :aria-pressed="muted"
        @click="$emit('toggle-mute', !muted)"
      >
        {{ muted ? unmuteLabel : muteLabel }}
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: "JukeboxVolumeControl",
  props: {
    controlId: { type: String, required: true },
    value: { type: Number, default: 1 },
    maximum: { type: Number, default: 1 },
    muted: { type: Boolean, default: false },
    label: { type: String, required: true },
    allowMute: { type: Boolean, default: false },
    muteLabel: { type: String, default: "Mute" },
    unmuteLabel: { type: String, default: "Unmute" },
  },
  emits: ["preview", "commit", "toggle-mute"],
  data: () => ({ open: false }),
  computed: {
    normalized() {
      return Math.max(
        0,
        Math.min(
          Math.max(0, Number(this.maximum) || 1),
          Number(this.value) || 0,
        ),
      );
    },
    percentage() {
      return Math.round(this.normalized * 100);
    },
    popoverId() {
      return `jukebox-volume-${this.controlId.replace(/[^a-z0-9_-]/giu, "-")}`;
    },
  },
  mounted() {
    document.addEventListener("pointerdown", this.closeFromOutside);
  },
  beforeUnmount() {
    document.removeEventListener("pointerdown", this.closeFromOutside);
  },
  methods: {
    quantize(value) {
      const maximum = Math.max(0, Number(this.maximum) || 1);
      return (
        Math.round(Math.max(0, Math.min(maximum, Number(value) || 0)) * 100) /
        100
      );
    },
    preview(value) {
      this.$emit("preview", this.quantize(value));
    },
    commit(value) {
      this.$emit("commit", this.quantize(value));
    },
    closeFromOutside(event) {
      if (this.open && !this.$el.contains(event.target)) this.open = false;
    },
  },
};
</script>
