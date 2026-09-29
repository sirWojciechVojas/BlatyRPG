<template>
  <section class="jukebox-tab-view" role="tabpanel">
    <div v-if="canControl" class="jukebox-section-heading">
      <h3>{{ $t("audio.jukebox.channels") }}</h3>
      <span>{{ $t("audio.jukebox.independentChannels") }}</span>
    </div>
    <div v-if="!canControl" class="jukebox-master-volume">
      <span>
        <strong>{{ $t("audio.jukebox.masterVolume") }}</strong>
        <small>{{ $t("audio.jukebox.localOnly") }}</small>
      </span>
      <output>
        {{
          jukebox.masterMuted
            ? $t("audio.jukebox.muted")
            : percent(jukebox.masterVolume)
        }}
      </output>
      <JukeboxVolumeControl
        control-id="master"
        :value="jukebox.masterVolume"
        :maximum="1.4"
        :muted="jukebox.masterMuted"
        :label="$t('audio.jukebox.masterVolume')"
        :mute-label="$t('audio.jukebox.mute')"
        :unmute-label="$t('audio.jukebox.unmute')"
        allow-mute
        @preview="setMasterVolume"
        @commit="setMasterVolume"
        @toggle-mute="setMasterMuted"
      />
    </div>
    <div v-if="canControl" class="jukebox-channel-list">
      <article
        v-for="channelId in channelIds"
        :key="channelId"
        class="jukebox-channel-row"
        :data-active="isActive(channelId)"
      >
        <div class="jukebox-channel-row__main">
          <div class="jukebox-channel-row__identity">
            <span class="jukebox-channel-row__name">{{
              $t(`audio.jukebox.channel.${channelId}`)
            }}</span>
            <JukeboxMarqueeText :text="sourceName(channelId)" />
            <small v-if="isDevice(channelId)">{{
              deviceStatusLabel(channelId)
            }}</small>
            <small v-else>{{ statusLabel(channelId) }}</small>
          </div>
        </div>
        <div class="jukebox-channel-transport">
          <div class="jukebox-icon-actions">
            <JukeboxVolumeControl
              :control-id="channelId"
              :value="volumeValue(channelId)"
              :label="volumeLabel(channelId)"
              @preview="previewVolume(channelId, $event)"
              @commit="commitVolume(channelId, $event)"
            />
            <template v-if="canControl">
              <button
                type="button"
                :disabled="!canToggle(channelId)"
                :aria-label="toggleLabel(channelId)"
                :title="toggleLabel(channelId)"
                @click="toggle(channelId)"
              >
                <svg
                  class="jukebox-playback-control-icon"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                  focusable="false"
                >
                  <path
                    v-if="isPaused(channelId)"
                    d="M7 4.75v14.5L19 12 7 4.75Z"
                    fill="currentColor"
                  />
                  <template v-else>
                    <rect
                      x="6"
                      y="5"
                      width="4.5"
                      height="14"
                      rx="1"
                      fill="currentColor"
                    />
                    <rect
                      x="13.5"
                      y="5"
                      width="4.5"
                      height="14"
                      rx="1"
                      fill="currentColor"
                    />
                  </template>
                </svg>
              </button>
              <button
                type="button"
                :disabled="!hasSource(channelId)"
                :aria-label="$t('audio.jukebox.stop')"
                :title="$t('audio.jukebox.stop')"
                @click="dispatch('stop', channelId)"
              >
                ■
              </button>
            </template>
          </div>
          <div class="jukebox-channel-progress">
            <input
              :value="progressValue(channelId)"
              type="range"
              min="0"
              :max="durationValue(channelId) || 1"
              step="0.1"
              :disabled="!canSeek(channelId)"
              :aria-label="progressLabel(channelId)"
              :style="progressStyle(channelId)"
              @pointerdown="beginSeek(channelId)"
              @input="previewSeek(channelId, $event.target.value)"
              @change="commitSeek(channelId, $event.target.value)"
            />
            <output>
              {{ formatTime(progressValue(channelId)) }} /
              {{
                durationValue(channelId)
                  ? formatTime(durationValue(channelId))
                  : "--:--"
              }}
            </output>
          </div>
        </div>
        <AudioLevelMeter
          :value="channel(channelId).level"
          :available="meterAvailable(channelId)"
          :active="isActive(channelId)"
          :label="meterLabel(channelId)"
          :unavailable-label="$t('audio.jukebox.levelUnavailable')"
        />
        <button
          type="button"
          class="jukebox-queue-toggle"
          :aria-expanded="expanded[channelId] === true"
          @click="toggleQueue(channelId)"
        >
          <span>{{ $t("audio.jukebox.channelQueue") }}</span
          ><span>{{ queueSummary(channelId) }}</span>
        </button>
        <div
          v-if="expanded[channelId]"
          class="jukebox-queue"
          :aria-label="
            $t('audio.jukebox.queueFor', {
              channel: $t(`audio.jukebox.channel.${channelId}`),
            })
          "
        >
          <div v-if="!queue(channelId).length" class="jukebox-queue__empty">
            <span>{{ $t("audio.jukebox.emptyQueue") }}</span>
            <button
              v-if="canControl"
              type="button"
              @click="$emit('select-library', channelId)"
            >
              {{ $t("audio.jukebox.addTrack") }}
            </button>
          </div>
          <ol v-else>
            <li
              v-for="(item, index) in queue(channelId)"
              :key="item.id"
              :class="{ next: index === 0 }"
            >
              <span class="jukebox-queue__order">{{ index + 1 }}</span>
              <span
                ><strong>{{ item.title }}</strong
                ><small v-if="index === 0">{{ $t("audio.jukebox.next") }}</small
                ><small v-else-if="item.playlistName">{{
                  item.playlistName
                }}</small></span
              >
              <span v-if="canControl" class="jukebox-icon-actions">
                <button
                  type="button"
                  :disabled="index === 0"
                  :aria-label="$t('audio.jukebox.moveUp')"
                  @click="move(channelId, item, index - 1)"
                >
                  ↑
                </button>
                <button
                  type="button"
                  :disabled="index === queue(channelId).length - 1"
                  :aria-label="$t('audio.jukebox.moveDown')"
                  @click="move(channelId, item, index + 1)"
                >
                  ↓
                </button>
                <button
                  type="button"
                  :aria-label="$t('audio.jukebox.playNow')"
                  @click="playQueued(channelId, item)"
                >
                  ▶
                </button>
                <button
                  type="button"
                  :aria-label="$t('audio.jukebox.removeFromQueue')"
                  @click="remove(channelId, item)"
                >
                  ×
                </button>
              </span>
            </li>
          </ol>
          <button
            v-if="canControl && queue(channelId).length"
            type="button"
            class="jukebox-text-action"
            @click="clear(channelId)"
          >
            {{ $t("audio.jukebox.clearQueue") }}
          </button>
        </div>
      </article>
    </div>
  </section>
</template>

<script>
import AudioLevelMeter from "./AudioLevelMeter.vue";
import JukeboxMarqueeText from "./JukeboxMarqueeText.vue";
import JukeboxVolumeControl from "./JukeboxVolumeControl.vue";

export default {
  name: "JukeboxPlaybackTab",
  components: {
    AudioLevelMeter,
    JukeboxMarqueeText,
    JukeboxVolumeControl,
  },
  props: {
    jukebox: { type: Object, required: true },
    channelIds: { type: Array, required: true },
    canControl: Boolean,
  },
  emits: ["select-library", "notice"],
  data: () => ({
    expanded: {},
    seekDraft: {},
    seekBeforeEdit: {},
    volumeBeforeEdit: {},
  }),
  methods: {
    channel(id) {
      return this.jukebox.channels[id] || {};
    },
    queue(id) {
      return this.jukebox.queues[id] || [];
    },
    isDevice(id) {
      return (
        this.channel(id).sourceType === "external-input" &&
        Boolean(this.channel(id).title)
      );
    },
    hasSource(id) {
      return Boolean(this.channel(id).trackId || this.channel(id).title);
    },
    canToggle(id) {
      const value = this.channel(id);
      return (
        this.hasSource(id) &&
        !value.sourceUnavailable &&
        (!this.isDevice(id) || value.status === "playing")
      );
    },
    isActive(id) {
      const value = this.channel(id);
      return value.status === "playing" && !value.muted;
    },
    isPaused(id) {
      const value = this.channel(id);
      return value.status !== "playing" || value.muted;
    },
    sourceName(id) {
      const value = this.channel(id);
      if (/^soundpad:\d+$/.test(String(value.sourceLabel || ""))) {
        return value.title || this.$t("audio.jukebox.emptyChannel");
      }
      return (
        value.sourceLabel ||
        value.title ||
        this.$t("audio.jukebox.emptyChannel")
      );
    },
    statusLabel(id) {
      const value = this.channel(id);
      if (!this.hasSource(id)) return this.$t("audio.jukebox.status.empty");
      if (value.blocked) return this.$t("audio.jukebox.status.blocked");
      if (value.playbackError) {
        const key = `audio.jukebox.errors.${value.playbackError}`;
        return this.$te(key)
          ? this.$t(key)
          : this.$t("audio.jukebox.errors.youtube_player_error");
      }
      if (value.status === "loading")
        return this.$t("audio.jukebox.status.loading");
      if (value.status === "playing")
        return this.$t("audio.jukebox.status.playing");
      if (value.status === "paused")
        return this.$t("audio.jukebox.status.paused");
      return this.$t("audio.jukebox.status.ready");
    },
    deviceStatusLabel(id) {
      return this.$t(
        this.channel(id).sourceUnavailable
          ? "audio.jukebox.status.signalLost"
          : "audio.jukebox.deviceSource",
      );
    },
    toggleLabel(id) {
      return this.$t(
        this.isPaused(id) ? "audio.jukebox.resume" : "audio.jukebox.pause",
      );
    },
    toggle(id) {
      if (this.isDevice(id))
        return this.dispatch("mute", {
          channelId: id,
          muted: !this.channel(id).muted,
        });
      return this.dispatch(this.isPaused(id) ? "play" : "pause", id);
    },
    dispatch(action, payload) {
      return this.$store.dispatch(`jukebox/${action}`, payload).catch(() => {
        this.$emit("notice", {
          kind: "error",
          text: this.$t("audio.jukebox.errors.default"),
        });
        return null;
      });
    },
    volumeValue(id) {
      const value = this.channel(id);
      return this.canControl ? (value.volume ?? 1) : (value.localVolume ?? 1);
    },
    volumeLabel(id) {
      return this.$t(
        this.canControl
          ? "audio.jukebox.tableChannelVolume"
          : "audio.jukebox.localChannelVolume",
        { channel: this.$t(`audio.jukebox.channel.${id}`) },
      );
    },
    previewVolume(id, value) {
      if (!Object.prototype.hasOwnProperty.call(this.volumeBeforeEdit, id)) {
        this.volumeBeforeEdit = {
          ...this.volumeBeforeEdit,
          [id]: this.volumeValue(id),
        };
      }
      return this.$store.dispatch(
        this.canControl
          ? "jukebox/previewChannelVolume"
          : "jukebox/setLocalChannelVolume",
        { channelId: id, volume: Number(value) },
      );
    },
    async commitVolume(id, value) {
      const volume = Number(value);
      const previous = this.volumeBeforeEdit[id];
      const result = this.canControl
        ? await this.dispatch("volume", { channelId: id, volume })
        : await this.$store.dispatch("jukebox/setLocalChannelVolume", {
            channelId: id,
            volume,
          });
      if (this.canControl && result === null && previous !== undefined) {
        await this.$store.dispatch("jukebox/previewChannelVolume", {
          channelId: id,
          volume: previous,
        });
      }
      const remaining = { ...this.volumeBeforeEdit };
      delete remaining[id];
      this.volumeBeforeEdit = remaining;
      return result;
    },
    setMasterVolume(volume) {
      return this.$store.dispatch("jukebox/setMasterVolume", Number(volume));
    },
    setMasterMuted(muted) {
      return this.$store.dispatch("jukebox/setMasterMuted", Boolean(muted));
    },
    percent(value) {
      return `${Math.round((Number(value) || 0) * 100)}%`;
    },
    durationValue(id) {
      return Math.max(0, Number(this.channel(id).duration) || 0);
    },
    canSeek(id) {
      return (
        this.canControl &&
        this.hasSource(id) &&
        !this.isDevice(id) &&
        this.durationValue(id) > 0
      );
    },
    progressValue(id) {
      const duration = this.durationValue(id);
      const value = Object.prototype.hasOwnProperty.call(this.seekDraft, id)
        ? this.seekDraft[id]
        : Number(this.channel(id).currentTime) || 0;
      return Math.max(0, Math.min(duration || value, value));
    },
    progressLabel(id) {
      return this.$t("audio.jukebox.playbackPosition", {
        channel: this.$t(`audio.jukebox.channel.${id}`),
      });
    },
    progressStyle(id) {
      const duration = this.durationValue(id);
      const percentage = duration
        ? (this.progressValue(id) / duration) * 100
        : 0;
      return { "--jukebox-progress": `${percentage}%` };
    },
    beginSeek(id) {
      this.seekBeforeEdit = {
        ...this.seekBeforeEdit,
        [id]: this.progressValue(id),
      };
    },
    previewSeek(id, value) {
      if (!this.canSeek(id)) return;
      const position = Math.round(Number(value) * 10) / 10;
      this.seekDraft = { ...this.seekDraft, [id]: position };
    },
    async commitSeek(id, value) {
      if (!this.canSeek(id)) return null;
      const position = Math.round(Number(value) * 10) / 10;
      const previous = this.seekBeforeEdit[id] ?? this.channel(id).currentTime;
      await this.$store.dispatch("jukebox/previewSeek", {
        channelId: id,
        position,
      });
      const result = await this.dispatch("seek", { channelId: id, position });
      if (result === null) {
        await this.$store.dispatch("jukebox/previewSeek", {
          channelId: id,
          position: previous,
        });
      }
      const remainingDrafts = { ...this.seekDraft };
      const remainingStarts = { ...this.seekBeforeEdit };
      delete remainingDrafts[id];
      delete remainingStarts[id];
      this.seekDraft = remainingDrafts;
      this.seekBeforeEdit = remainingStarts;
      return result;
    },
    formatTime(value) {
      const total = Math.max(0, Math.floor(Number(value) || 0));
      const hours = Math.floor(total / 3600);
      const minutes = Math.floor((total % 3600) / 60);
      const seconds = String(total % 60).padStart(2, "0");
      return hours
        ? `${hours}:${String(minutes).padStart(2, "0")}:${seconds}`
        : `${minutes}:${seconds}`;
    },
    meterAvailable(id) {
      const value = this.channel(id);
      return value.meterAvailable !== false || !this.hasSource(id);
    },
    meterLabel(id) {
      return this.$t(
        this.meterAvailable(id)
          ? "audio.jukebox.level"
          : "audio.jukebox.externalActivity",
      );
    },
    queueSummary(id) {
      const count = this.queue(id).length;
      return count
        ? this.$t("audio.jukebox.queueCount", { count })
        : this.$t("audio.jukebox.queueEmptyShort");
    },
    toggleQueue(id) {
      this.expanded = { ...this.expanded, [id]: !this.expanded[id] };
    },
    move(channelId, item, position) {
      return this.dispatch("moveQueueItem", {
        channelId,
        itemId: item.id,
        position,
      });
    },
    remove(channelId, item) {
      return this.dispatch("removeQueueItem", { channelId, itemId: item.id });
    },
    clear(channelId) {
      return this.dispatch("clearQueue", channelId);
    },
    async playQueued(channelId, item) {
      const result = await this.dispatch("playQueueItem", { channelId, item });
      if (result) {
        this.$emit("notice", {
          kind: "success",
          text: this.$t("audio.jukebox.nowPlaying", { title: item.title }),
        });
      }
    },
  },
};
</script>
