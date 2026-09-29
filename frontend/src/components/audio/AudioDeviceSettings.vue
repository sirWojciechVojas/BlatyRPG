<template>
  <section class="audio-device-settings">
    <header class="audio-device-settings__header">
      <div>
        <h3>{{ $t("audio.voice.devicesTitle") }}</h3>
        <p>{{ $t("audio.voice.devicesDescription") }}</p>
      </div>
      <span class="audio-status" :data-status="voice.status">
        {{ statusLabel }}
      </span>
    </header>

    <p v-if="voice.error" class="audio-error">{{ errorLabel }}</p>

    <label>
      <span>{{ $t("audio.voice.microphone") }}</span>
      <select
        :value="voice.microphoneDeviceId"
        @change="changeMicrophone($event.target.value)"
      >
        <option value="">{{ $t("audio.voice.defaultDevice") }}</option>
        <option
          v-for="device in voice.devices.inputs"
          :key="device.deviceId"
          :value="device.deviceId"
        >
          {{ device.label }}
        </option>
      </select>
    </label>

    <label v-if="voice.outputSelectionSupported">
      <span>{{ $t("audio.voice.output") }}</span>
      <select
        :value="voice.outputDeviceId"
        @change="setOutput($event.target.value)"
      >
        <option value="">{{ $t("audio.voice.defaultDevice") }}</option>
        <option
          v-for="device in voice.devices.outputs"
          :key="device.deviceId"
          :value="device.deviceId"
        >
          {{ device.label }}
        </option>
      </select>
    </label>
    <p v-else class="audio-muted">
      {{ $t("audio.voice.outputUnsupported") }}
    </p>

    <div class="audio-action-row">
      <button type="button" @click="refreshDevices">
        {{ $t("audio.voice.refreshDevices") }}
      </button>
      <button
        v-if="!joined"
        type="button"
        class="audio-primary"
        :disabled="voice.joining"
        @click="join"
      >
        {{ $t(voice.error ? "audio.voice.retry" : "audio.voice.join") }}
      </button>
    </div>
    <small
      v-if="
        ['loading', 'saving', 'saved', 'error'].includes(
          voice.deviceSettingsStatus,
        )
      "
      class="audio-device-settings__save-status"
      :data-status="voice.deviceSettingsStatus"
    >
      {{ deviceSettingsLabel }}
    </small>
    <small v-if="!voice.devices.inputs.length" class="audio-muted">
      {{ $t("audio.voice.permissionHint") }}
    </small>

    <section v-if="canManage" class="external-inputs">
      <h3>{{ $t("audio.voice.sourcesTitle") }}</h3>
      <p class="audio-muted">{{ $t("audio.voice.sourcesDescription") }}</p>
      <div
        v-for="channelId in externalChannels"
        :key="channelId"
        class="external-input-row"
      >
        <div class="external-input-row__head">
          <strong>{{ $t("audio.voice.source." + channelId) }}</strong>
          <span :data-available="sourceAvailable(channelId)">
            {{ sourceStatus(channelId) }}
          </span>
        </div>
        <div class="external-input-row__controls">
          <select
            :value="voice.externalDeviceIds[channelId]"
            :aria-label="$t('audio.voice.source.' + channelId)"
            @change="setExternalDevice(channelId, $event.target.value)"
          >
            <option value="">{{ $t("audio.voice.selectSource") }}</option>
            <option
              v-if="missingDevice(channelId)"
              :value="voice.externalDeviceIds[channelId]"
            >
              {{ $t("audio.voice.unavailableSavedDevice") }}
            </option>
            <option
              v-for="device in voice.devices.inputs"
              :key="device.deviceId"
              :value="device.deviceId"
            >
              {{ device.label }}
            </option>
          </select>
          <button
            type="button"
            :disabled="!sourceAvailable(channelId)"
            @click="togglePreview(channelId)"
          >
            {{
              previewing === channelId
                ? $t("audio.voice.stopPreview")
                : $t("audio.voice.preview")
            }}
          </button>
        </div>
        <AudioLevelMeter
          :value="previewing === channelId ? previewLevel : 0"
          :available="sourceAvailable(channelId)"
          :label="$t('audio.voice.sourceLevel')"
          :unavailable-label="$t('audio.voice.sourceUnavailable')"
        />
      </div>
      <small v-if="previewError" class="audio-error" aria-live="polite">
        {{ previewErrorLabel }}
      </small>
    </section>
  </section>
</template>

<script>
import {
  audioDeviceService,
  audioInputAvailability,
} from "@/services/audioDeviceService";
import AudioLevelMeter from "./AudioLevelMeter.vue";
import "./audioDock.css";

export default {
  name: "AudioDeviceSettings",
  components: { AudioLevelMeter },
  props: {
    campaignId: { type: [Number, String], required: true },
    canManage: Boolean,
  },
  data: () => ({
    externalChannels: ["external-1", "external-2"],
    previewing: null,
    previewLevel: 0,
    previewError: null,
    stopPreview: null,
  }),
  computed: {
    voice() {
      return this.$store.state.voice;
    },
    joined() {
      return ["connected", "reconnecting"].includes(this.voice.status);
    },
    statusLabel() {
      if (
        this.voice.status === "error" &&
        this.voice.error === "microphone_permission_denied"
      ) {
        return this.$t("audio.voice.status.permissionDenied");
      }
      const key = [
        "connected",
        "connecting",
        "reconnecting",
        "disconnected",
        "error",
      ].includes(this.voice.status)
        ? this.voice.status
        : "connecting";
      return this.$t(`audio.voice.status.${key}`);
    },
    errorLabel() {
      const key = `audio.voice.errors.${this.voice.error}`;
      return this.$te(key) ? this.$t(key) : this.voice.error;
    },
    deviceSettingsLabel() {
      const key = `audio.voice.deviceSettings.${this.voice.deviceSettingsStatus}`;
      return this.$te(key) ? this.$t(key) : this.voice.deviceSettingsError;
    },
    previewErrorLabel() {
      const key = `audio.voice.errors.${this.previewError}`;
      return this.$te(key)
        ? this.$t(key)
        : this.$t("audio.voice.previewFailed");
    },
  },
  created() {
    this.$store.dispatch("voice/initialize");
  },
  beforeUnmount() {
    this.stopSourcePreview();
  },
  methods: {
    join() {
      return this.$store
        .dispatch("voice/join", this.campaignId)
        .catch(() => {});
    },
    changeMicrophone(value) {
      return this.$store
        .dispatch("voice/changeMicrophone", value)
        .catch(() => {});
    },
    setOutput(value) {
      return this.$store
        .dispatch("voice/setOutputDevice", value)
        .catch(() => {});
    },
    setExternalDevice(channelId, deviceId) {
      if (this.previewing === channelId) this.stopSourcePreview();
      return this.$store.dispatch("voice/setExternalInputDevice", {
        channelId,
        deviceId,
      });
    },
    refreshDevices() {
      return this.$store.dispatch("voice/refreshDevices").catch(() => {});
    },
    sourceAvailable(channelId) {
      return (
        audioInputAvailability(
          this.voice.externalDeviceIds[channelId],
          this.voice.devices.inputs,
        ).status === "available"
      );
    },
    missingDevice(channelId) {
      return Boolean(
        this.voice.externalDeviceIds[channelId] &&
        !this.sourceAvailable(channelId),
      );
    },
    sourceStatus(channelId) {
      if (!this.voice.externalDeviceIds[channelId])
        return this.$t("audio.voice.sourceNotConfigured");
      return this.sourceAvailable(channelId)
        ? this.$t("audio.voice.sourceAvailable")
        : this.$t("audio.voice.sourceUnavailable");
    },
    stopSourcePreview() {
      this.stopPreview?.();
      this.stopPreview = null;
      this.previewing = null;
      this.previewLevel = 0;
    },
    async togglePreview(channelId) {
      if (this.previewing === channelId) {
        this.stopSourcePreview();
        return;
      }
      this.stopSourcePreview();
      this.previewError = null;
      try {
        const stop = await audioDeviceService.monitorExternalInput(
          this.voice.externalDeviceIds[channelId],
          (level) => {
            if (this.previewing === channelId) this.previewLevel = level;
          },
        );
        this.previewing = channelId;
        this.stopPreview = stop;
      } catch (error) {
        this.previewError = String(
          error?.name === "NotAllowedError"
            ? "microphone_permission_denied"
            : error?.message || "preview_failed",
        );
      }
    },
  },
};
</script>
