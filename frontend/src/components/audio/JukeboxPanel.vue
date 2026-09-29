<template>
  <section class="audio-panel jukebox-panel jukebox-compact">
    <header class="jukebox-compact__header">
      <div>
        <small>{{ $t("audio.jukebox.tablePanel") }}</small>
        <h2>{{ $t("audio.jukebox.title") }}</h2>
      </div>
      <span class="audio-status" :data-status="realtimeStatus">
        {{ realtimeLabel }}
      </span>
    </header>

    <p
      v-if="notice"
      class="jukebox-notice"
      :data-kind="notice.kind"
      aria-live="polite"
    >
      {{ notice.text }}
    </p>
    <p v-if="errorLabel" class="audio-error" role="alert">{{ errorLabel }}</p>
    <p
      v-if="jukebox.phase === 'loading'"
      class="jukebox-empty"
      aria-live="polite"
    >
      {{ $t("audio.jukebox.loading") }}
    </p>
    <button
      v-if="jukebox.audioContextState === 'suspended' || jukebox.audioBlocked"
      type="button"
      class="audio-primary jukebox-unlock"
      @click="unlock"
    >
      {{ $t("audio.jukebox.unlock") }}
    </button>

    <nav class="jukebox-tabs" :aria-label="$t('audio.jukebox.sections')">
      <button
        v-for="tab in tabs"
        :key="tab"
        type="button"
        role="tab"
        :aria-selected="activeTab === tab"
        :class="{ active: activeTab === tab }"
        @click="activeTab = tab"
      >
        {{ $t(`audio.jukebox.tabs.${tab}`) }}
      </button>
    </nav>

    <JukeboxPlaybackTab
      v-if="activeTab === 'playback'"
      :jukebox="jukebox"
      :channel-ids="channelIds"
      :can-control="canControl"
      @select-library="openLibrary"
      @notice="showNotice"
    />
    <JukeboxLibraryTab
      v-else-if="activeTab === 'library'"
      :jukebox="jukebox"
      :voice="voice"
      :channel-ids="channelIds"
      :can-manage="canManage"
      :can-control="canControl"
      :selected-channel="selectedChannel"
      @update:selected-channel="selectedChannel = $event"
      @open-settings="$emit('open-settings')"
      @notice="showNotice"
    />
    <JukeboxPlaylistsTab
      v-else
      :jukebox="jukebox"
      :channel-ids="channelIds"
      :can-manage="canManage"
      @notice="showNotice"
    />
  </section>
</template>

<script>
import JukeboxLibraryTab from "./JukeboxLibraryTab.vue";
import JukeboxPlaybackTab from "./JukeboxPlaybackTab.vue";
import JukeboxPlaylistsTab from "./JukeboxPlaylistsTab.vue";
import "./audioDock.css";
import "./jukeboxCompact.css";

export default {
  name: "JukeboxPanel",
  components: { JukeboxLibraryTab, JukeboxPlaybackTab, JukeboxPlaylistsTab },
  props: { campaignId: { type: [Number, String], required: true } },
  emits: ["open-settings"],
  data: () => ({
    tabs: ["playback", "library", "playlists"],
    activeTab: "playback",
    selectedChannel: "music",
    channelIds: [
      "music",
      "ambient-1",
      "ambient-2",
      "sfx",
      "external-1",
      "external-2",
    ],
    notice: null,
    noticeTimer: null,
  }),
  computed: {
    jukebox() {
      return this.$store.state.jukebox;
    },
    voice() {
      return this.$store.state.voice;
    },
    canManage() {
      return this.jukebox.capabilities.canManage === true;
    },
    canControl() {
      return this.jukebox.capabilities.canControl === true;
    },
    realtimeStatus() {
      const status = this.$store.state.realtime?.status || "disconnected";
      if (["ready", "connected", "authenticated"].includes(status)) {
        return "connected";
      }
      if (
        [
          "ticketing",
          "connecting",
          "authenticating",
          "syncing",
          "reconnecting",
        ].includes(status)
      ) {
        return "reconnecting";
      }
      return "disconnected";
    },
    realtimeLabel() {
      return this.$t(
        this.realtimeStatus === "connected"
          ? "audio.jukebox.realtimeConnected"
          : "audio.jukebox.realtimeDisconnected",
      );
    },
    errorLabel() {
      if (!this.jukebox.error) return "";
      const key = `audio.jukebox.errors.${this.jukebox.error}`;
      return this.$te(key)
        ? this.$t(key)
        : this.$t("audio.jukebox.errors.default");
    },
  },
  watch: {
    campaignId: {
      immediate: true,
      handler(value) {
        if (
          (Number(this.jukebox.campaignId) === Number(value) &&
            this.jukebox.phase === "ready") ||
          this.jukebox.phase === "loading"
        )
          return;
        this.$store.dispatch("jukebox/enter", value).catch(() => {});
      },
    },
  },
  beforeUnmount() {
    clearTimeout(this.noticeTimer);
  },
  methods: {
    openLibrary(channelId) {
      this.selectedChannel = channelId;
      this.activeTab = "library";
    },
    showNotice(payload) {
      clearTimeout(this.noticeTimer);
      this.notice = payload;
      this.noticeTimer = setTimeout(() => {
        this.notice = null;
      }, 4500);
    },
    unlock() {
      return this.$store.dispatch("jukebox/unlockAudio").catch(() => {});
    },
  },
};
</script>
