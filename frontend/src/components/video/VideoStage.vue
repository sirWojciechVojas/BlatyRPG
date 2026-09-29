<template>
  <section class="video-stage" aria-labelledby="video-stage-heading">
    <header class="video-stage__header">
      <h3 id="video-stage-heading">{{ $t("audio.video.title") }}</h3>
      <button
        type="button"
        class="video-stage__camera-toggle"
        :class="{ active: video.cameraEnabled }"
        :disabled="!joined || video.busy"
        :aria-pressed="video.cameraEnabled === true"
        @click="toggleCamera"
      >
        {{
          $t(
            video.cameraEnabled
              ? "audio.video.turnCameraOff"
              : "audio.video.turnCameraOn",
          )
        }}
      </button>
    </header>

    <div class="video-stage__controls">
      <label>
        <span>{{ $t("audio.video.camera") }}</span>
        <select
          :value="video.cameraDeviceId"
          :disabled="!joined || video.busy"
          @change="changeCamera($event.target.value)"
        >
          <option value="">{{ $t("audio.video.defaultCamera") }}</option>
          <option
            v-for="device in video.devices.videoInputs"
            :key="device.deviceId"
            :value="device.deviceId"
          >
            {{ device.label }}
          </option>
        </select>
      </label>

      <label>
        <span>{{ $t("audio.video.qualityLabel") }}</span>
        <select
          :value="video.quality"
          :disabled="!joined || video.busy"
          @change="changeQuality($event.target.value)"
        >
          <option v-for="quality in qualities" :key="quality" :value="quality">
            {{ $t(`audio.video.quality.${quality}`) }}
          </option>
        </select>
      </label>
    </div>

    <p v-if="video.error" class="video-stage__error" role="alert">
      {{ errorLabel }}
    </p>

    <div
      class="video-stage__grid"
      :class="{ 'video-stage__grid--expanded': selectedIdentity }"
    >
      <VideoParticipantTile
        v-for="participant in orderedParticipants"
        :key="participant.identity"
        :participant="participant"
        :expanded="selectedIdentity === participant.identity"
        @select="selectParticipant"
      />
    </div>
  </section>
</template>

<script>
import VideoParticipantTile from "./VideoParticipantTile.vue";
import "./videoStage.css";

export default {
  name: "VideoStage",
  components: { VideoParticipantTile },
  props: {
    joined: Boolean,
  },
  data: () => ({
    qualities: ["auto", "360p", "720p", "1080p"],
    selectedIdentity: "",
  }),
  computed: {
    video() {
      return (
        this.$store.state.video || {
          cameraEnabled: false,
          cameraDeviceId: "",
          quality: "auto",
          busy: false,
          error: null,
          devices: { videoInputs: [] },
          participants: [],
        }
      );
    },
    participants() {
      return Array.isArray(this.video.participants)
        ? this.video.participants
        : [];
    },
    orderedParticipants() {
      return [...this.participants].sort((left, right) => {
        const selected =
          Number(right.identity === this.selectedIdentity) -
          Number(left.identity === this.selectedIdentity);
        if (selected) return selected;
        const camera = Number(right.cameraOn) - Number(left.cameraOn);
        if (camera) return camera;
        return String(left.nickname || "").localeCompare(
          String(right.nickname || ""),
        );
      });
    },
    errorLabel() {
      if (!this.video.error) return "";
      const key = `audio.video.errors.${this.video.error}`;
      return this.$te(key)
        ? this.$t(key)
        : this.$t("audio.video.errors.default");
    },
  },
  watch: {
    joined(value) {
      if (!value) this.selectedIdentity = "";
    },
    participants(value) {
      if (
        this.selectedIdentity &&
        !value.some(
          (participant) => participant.identity === this.selectedIdentity,
        )
      ) {
        this.selectedIdentity = "";
      }
    },
  },
  created() {
    this.$store.dispatch("video/initialize");
  },
  methods: {
    toggleCamera() {
      return this.$store.dispatch("video/toggleCamera").catch(() => {});
    },
    changeCamera(deviceId) {
      return this.$store
        .dispatch("video/changeCamera", deviceId)
        .catch(() => {});
    },
    changeQuality(quality) {
      return this.$store
        .dispatch("video/changeQuality", quality)
        .catch(() => {});
    },
    selectParticipant(identity) {
      this.selectedIdentity =
        this.selectedIdentity === identity ? "" : String(identity || "");
    },
  },
};
</script>
