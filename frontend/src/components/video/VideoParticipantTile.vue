<template>
  <article
    ref="tile"
    class="video-tile"
    :class="{
      'video-tile--camera-off': !participant.cameraOn,
      'video-tile--expanded': expanded,
      'video-tile--speaking': participant.speaking,
    }"
    @dblclick="$emit('select', participant.identity)"
  >
    <video ref="video" autoplay playsinline></video>

    <div v-if="!participant.cameraOn" class="video-tile__placeholder">
      <img v-if="participant.avatar" :src="participant.avatar" alt="" />
      <span v-else aria-hidden="true">{{
        initials(participant.nickname)
      }}</span>
      <small>{{ $t("audio.video.cameraOff") }}</small>
    </div>

    <div class="video-tile__actions">
      <button
        type="button"
        :aria-label="
          $t(expanded ? 'audio.video.restoreTile' : 'audio.video.expandTile', {
            name: participant.nickname,
          })
        "
        :title="
          $t(expanded ? 'audio.video.restoreTile' : 'audio.video.expandTile', {
            name: participant.nickname,
          })
        "
        @click.stop="$emit('select', participant.identity)"
      >
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M8 3H3v5m13-5h5v5M8 21H3v-5m13 5h5v-5" />
        </svg>
      </button>
      <button
        type="button"
        :aria-label="
          $t('audio.video.fullscreen', { name: participant.nickname })
        "
        :title="$t('audio.video.fullscreen', { name: participant.nickname })"
        @click.stop="openFullscreen"
      >
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M9 4H4v5m11-5h5v5M9 20H4v-5m11 5h5v-5" />
        </svg>
      </button>
    </div>

    <footer class="video-tile__identity">
      <span class="video-tile__speaking" aria-hidden="true"></span>
      <strong>
        {{ participant.nickname }}
        <small v-if="participant.local">{{ $t("audio.video.you") }}</small>
      </strong>
      <span class="video-tile__camera-state">
        {{
          $t(
            participant.cameraOn
              ? "audio.video.cameraOn"
              : "audio.video.cameraOff",
          )
        }}
      </span>
    </footer>
  </article>
</template>

<script>
export default {
  name: "VideoParticipantTile",
  props: {
    participant: { type: Object, required: true },
    expanded: Boolean,
  },
  emits: ["select"],
  watch: {
    "participant.identity"(next, previous) {
      this.detach(previous);
      this.$nextTick(() => this.attach(next));
    },
  },
  mounted() {
    this.attach(this.participant.identity);
  },
  beforeUnmount() {
    this.detach(this.participant.identity);
  },
  methods: {
    attach(identity) {
      if (!this.$refs.video) return;
      this.$store.dispatch("video/attach", {
        identity,
        element: this.$refs.video,
      });
    },
    detach(identity) {
      if (!this.$refs.video) return;
      this.$store.dispatch("video/detach", {
        identity,
        element: this.$refs.video,
      });
    },
    initials(name) {
      return (
        String(name || "?")
          .trim()
          .split(/\s+/u)
          .slice(0, 2)
          .map((part) => part[0])
          .join("")
          .toUpperCase() || "?"
      );
    },
    openFullscreen() {
      const element = this.$refs.tile;
      if (typeof element?.requestFullscreen === "function") {
        void element.requestFullscreen().catch(() => {});
      }
    },
  },
};
</script>
