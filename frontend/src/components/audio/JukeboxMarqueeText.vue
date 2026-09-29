<template>
  <strong
    ref="viewport"
    class="jukebox-track-marquee"
    :class="{ 'jukebox-track-marquee--overflowing': overflowing }"
    :title="text"
    :aria-label="text"
  >
    <span
      class="jukebox-track-marquee__track"
      :style="{ '--jukebox-marquee-duration': `${duration}s` }"
    >
      <span ref="primary">{{ text }}</span>
      <span v-if="overflowing" aria-hidden="true">{{ text }}</span>
    </span>
  </strong>
</template>

<script>
export default {
  name: "JukeboxMarqueeText",
  props: { text: { type: String, required: true } },
  data: () => ({ overflowing: false, duration: 8, observer: null }),
  watch: {
    text() {
      this.$nextTick(this.measure);
    },
  },
  mounted() {
    this.$nextTick(this.measure);
    if (typeof ResizeObserver === "function") {
      this.observer = new ResizeObserver(this.measure);
      this.observer.observe(this.$refs.viewport);
      this.observer.observe(this.$refs.primary);
    } else {
      window.addEventListener("resize", this.measure);
    }
  },
  beforeUnmount() {
    this.observer?.disconnect();
    window.removeEventListener("resize", this.measure);
  },
  methods: {
    measure() {
      const viewport = this.$refs.viewport;
      const primary = this.$refs.primary;
      if (!viewport || !primary) return;
      const width = primary.scrollWidth;
      this.overflowing = width > viewport.clientWidth + 1;
      this.duration = Math.max(7, Math.round((width / 28) * 10) / 10);
    },
  },
};
</script>
