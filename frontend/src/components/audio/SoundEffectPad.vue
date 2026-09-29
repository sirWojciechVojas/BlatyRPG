<template>
  <button
    type="button"
    class="sound-effect-pad"
    :class="[
      `sound-effect-pad--${status}`,
      { 'sound-effect-pad--empty': !pad },
    ]"
    :style="padStyle"
    :aria-label="ariaLabel"
    :aria-pressed="playing"
    @click="$emit('trigger', position)"
    @dblclick.stop
  >
    <template v-if="pad">
      <span class="sound-effect-pad__icon" aria-hidden="true">{{ icon }}</span>
      <strong :title="pad.name">{{ pad.name }}</strong>
      <span class="sound-effect-pad__meta">
        <small>{{ duration }}</small>
        <kbd v-if="pad.shortcut">{{ pad.shortcut }}</kbd>
      </span>
      <span class="sound-effect-pad__state">{{ stateLabel }}</span>
    </template>
    <template v-else>
      <span class="sound-effect-pad__plus" aria-hidden="true">+</span>
      <strong>{{ $t("audio.soundEffects.assign") }}</strong>
    </template>
  </button>
</template>

<script>
const ICONS = Object.freeze({
  waveform: "≋",
  swords: "⚔",
  monster: "♞",
  explosion: "✹",
  weather: "☁",
  magic: "✦",
  bell: "♢",
  music: "♫",
  fire: "♨",
  door: "▥",
});

export default {
  name: "SoundEffectPad",
  props: {
    pad: { type: Object, default: null },
    instance: { type: Object, default: null },
    position: { type: Number, required: true },
    textLines: { type: Number, default: 1 },
    loading: Boolean,
  },
  emits: ["trigger"],
  computed: {
    padStyle() {
      return {
        "--sound-effect-text-lines": Math.max(
          1,
          Math.min(5, Number(this.textLines) || 1),
        ),
        ...(this.pad ? { "--sound-effect-color": this.pad.color } : {}),
      };
    },
    icon() {
      return ICONS[this.pad?.icon] || ICONS.waveform;
    },
    playing() {
      return ["playing", "stopping"].includes(this.instance?.status);
    },
    status() {
      if (!this.pad) return "empty";
      if (this.pad.audio?.available === false) return "error";
      if (this.loading) return "loading";
      if (this.playing) return this.pad.loop ? "loop" : "playing";
      return "ready";
    },
    duration() {
      const seconds = Math.max(
        0,
        Math.round(Number(this.pad?.audio?.duration)),
      );
      if (!seconds) return "—:—";
      return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, "0")}`;
    },
    stateLabel() {
      return this.$t(`audio.soundEffects.states.${this.status}`);
    },
    ariaLabel() {
      return this.pad
        ? `${this.pad.name}, ${this.stateLabel}`
        : this.$t("audio.soundEffects.emptySlot", {
            number: this.position + 1,
          });
    },
  },
};
</script>
