<template>
  <section class="jukebox-tab-view" role="tabpanel">
    <div class="jukebox-section-heading">
      <h3>{{ $t("audio.jukebox.library") }}</h3>
      <span>{{ $t("audio.jukebox.libraryHint") }}</span>
    </div>
    <label class="jukebox-field">
      <span>{{ $t("audio.jukebox.targetChannel") }}</span>
      <select
        :value="selectedChannel"
        @change="$emit('update:selected-channel', $event.target.value)"
      >
        <option
          v-for="channelId in channelIds"
          :key="channelId"
          :value="channelId"
        >
          {{ $t(`audio.jukebox.channel.${channelId}`) }}
        </option>
      </select>
    </label>
    <div
      class="jukebox-source-switch"
      :aria-label="$t('audio.jukebox.sourceType')"
    >
      <button
        type="button"
        :aria-pressed="sourceMode === 'library'"
        @click="sourceMode = 'library'"
      >
        {{ $t("audio.jukebox.tabs.library") }}
      </button>
      <button
        type="button"
        :aria-pressed="sourceMode === 'device'"
        @click="sourceMode = 'device'"
      >
        {{ $t("audio.jukebox.device") }}
      </button>
    </div>

    <section v-if="sourceMode === 'device'" class="jukebox-device-picker">
      <p>{{ $t("audio.jukebox.deviceConfiguredInSettings") }}</p>
      <div
        v-for="slot in deviceSlots"
        :key="slot"
        class="jukebox-device-option"
      >
        <span
          ><strong>{{ $t(`audio.voice.source.${slot}`) }}</strong
          ><small>{{ deviceName(slot) }}</small></span
        >
        <span class="jukebox-device-option__status">{{
          deviceStatus(slot)
        }}</span>
        <button
          type="button"
          :disabled="!deviceAvailable(slot) || !canControl"
          @click="activateDevice(slot)"
        >
          {{ $t("audio.jukebox.startDevice") }}
        </button>
      </div>
      <button
        type="button"
        class="jukebox-text-action"
        @click="$emit('open-settings')"
      >
        {{ $t("audio.jukebox.openAudioSettings") }}
      </button>
    </section>

    <template v-else>
      <label class="jukebox-search">
        <span class="sr-only">{{ $t("audio.jukebox.search") }}</span>
        <input
          v-model.trim="query"
          type="search"
          :placeholder="$t('audio.jukebox.search')"
        />
      </label>
      <div class="jukebox-filter-row">
        <button
          v-for="category in categories"
          :key="category"
          type="button"
          :aria-pressed="selectedCategory === category"
          @click="selectedCategory = category"
        >
          {{
            category
              ? $t(`audio.jukebox.categoryName.${category}`)
              : $t("audio.jukebox.all")
          }}
        </button>
      </div>
      <div class="jukebox-filter-row jukebox-filter-row--library">
        <button
          v-for="scope in scopes"
          :key="scope"
          type="button"
          :aria-pressed="selectedScope === scope"
          @click="selectedScope = scope"
        >
          {{ $t(`audio.jukebox.scope.${scope || "all"}`) }}
        </button>
      </div>

      <p v-if="!filteredTracks.length" class="jukebox-empty">
        {{ $t("audio.jukebox.emptyLibrary") }}
      </p>
      <div v-else class="jukebox-track-list">
        <article
          v-for="track in filteredTracks"
          :key="track.id"
          class="jukebox-track-row"
        >
          <span class="jukebox-track-row__info">
            <strong :title="track.title">{{ track.title }}</strong>
            <small>{{ trackMeta(track) }}</small>
            <small v-if="activeChannelFor(track)" class="jukebox-in-use">
              {{
                $t("audio.jukebox.playingOn", {
                  channel: $t(
                    `audio.jukebox.channel.${activeChannelFor(track)}`,
                  ),
                })
              }}
            </small>
          </span>
          <span
            v-if="canControl || canEditTrack(track)"
            class="jukebox-track-row__actions"
          >
            <template v-if="canControl">
              <button
                type="button"
                :aria-label="playLabel(track)"
                :title="playLabel(track)"
                @click="playNow(track)"
              >
                ▶
              </button>
              <button
                type="button"
                :aria-label="$t('audio.jukebox.addToQueue')"
                :title="$t('audio.jukebox.addToQueue')"
                @click="addQueue(track)"
              >
                ＋
              </button>
            </template>
            <button
              v-if="canEditTrack(track)"
              type="button"
              data-track-edit
              :aria-label="$t('audio.jukebox.editTrack')"
              :title="$t('audio.jukebox.editTrack')"
              @click="beginEdit(track)"
            >
              <svg
                class="jukebox-playback-control-icon"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  fill="currentColor"
                  d="m4 16.5-.75 4.25L7.5 20 18.6 8.9l-3.5-3.5L4 16.5Zm16.3-9.3c.4-.4.4-1 0-1.4l-2.1-2.1a1 1 0 0 0-1.4 0l-1.2 1.2 3.5 3.5 1.2-1.2Z"
                />
              </svg>
            </button>
          </span>
          <form
            v-if="editingTrackId === track.id"
            class="jukebox-track-edit"
            data-track-edit-form
            @submit.prevent="saveEdit(track)"
          >
            <div class="jukebox-track-edit__fields">
              <label class="jukebox-field">
                <span>{{ $t("audio.jukebox.titleField") }}</span>
                <input
                  v-model.trim="editTitle"
                  data-track-edit-title
                  maxlength="180"
                  required
                />
              </label>
              <label
                v-if="track.sourceType === 'external'"
                class="jukebox-field"
              >
                <span>{{ $t("audio.jukebox.url") }}</span>
                <input
                  v-model.trim="editUrl"
                  data-track-edit-url
                  type="url"
                  required
                />
              </label>
            </div>
            <div class="jukebox-track-edit__actions">
              <button type="submit" :disabled="editSaving">
                {{ $t("audio.jukebox.saveChanges") }}
              </button>
              <button type="button" :disabled="editSaving" @click="cancelEdit">
                {{ $t("audio.jukebox.cancel") }}
              </button>
            </div>
          </form>
        </article>
      </div>

      <details v-if="canManage" class="jukebox-library-manager">
        <summary>{{ $t("audio.jukebox.managePersonalLibrary") }}</summary>
        <form @submit.prevent="upload">
          <input
            ref="upload"
            type="file"
            accept="audio/*,.mp3,.wav,.ogg,.oga,.webm,.m4a,.mp4,.aac,.flac"
            required
          />
          <input
            v-model.trim="uploadTitle"
            :placeholder="$t('audio.jukebox.titleField')"
            maxlength="180"
          />
          <select v-model="uploadCategory">
            <option
              v-for="category in categories.slice(1)"
              :key="category"
              :value="category"
            >
              {{ $t(`audio.jukebox.categoryName.${category}`) }}
            </option>
          </select>
          <button type="submit" :disabled="submitting">
            {{ $t("audio.jukebox.upload") }}
          </button>
        </form>
        <form @submit.prevent="addExternal">
          <input
            v-model.trim="externalTitle"
            :placeholder="$t('audio.jukebox.titleField')"
            maxlength="180"
            required
          />
          <input
            v-model.trim="externalUrl"
            :placeholder="$t('audio.jukebox.url')"
            type="url"
            required
          />
          <select v-model="externalCategory">
            <option
              v-for="category in categories.slice(1)"
              :key="category"
              :value="category"
            >
              {{ $t(`audio.jukebox.categoryName.${category}`) }}
            </option>
          </select>
          <button type="submit" :disabled="submitting">
            {{ $t("audio.jukebox.addExternalShort") }}
          </button>
        </form>
      </details>
    </template>
  </section>
</template>

<script>
import { audioInputAvailability } from "@/services/audioDeviceService";

export default {
  name: "JukeboxLibraryTab",
  props: {
    jukebox: { type: Object, required: true },
    voice: { type: Object, required: true },
    channelIds: { type: Array, required: true },
    selectedChannel: { type: String, required: true },
    canManage: Boolean,
    canControl: Boolean,
  },
  emits: ["update:selected-channel", "open-settings", "notice"],
  data: () => ({
    sourceMode: "library",
    query: "",
    selectedCategory: "",
    selectedScope: "",
    categories: ["", "music", "ambient", "sfx"],
    scopes: ["", "system", "personal"],
    deviceSlots: ["external-1", "external-2"],
    uploadTitle: "",
    uploadCategory: "music",
    externalTitle: "",
    externalUrl: "",
    externalCategory: "music",
    submitting: false,
    editingTrackId: null,
    editTitle: "",
    editUrl: "",
    editSaving: false,
  }),
  computed: {
    filteredTracks() {
      const query = this.query.toLocaleLowerCase();
      return (this.jukebox.tracks || []).filter((track) => {
        if (this.selectedCategory && track.category !== this.selectedCategory)
          return false;
        if (this.selectedScope && track.library?.scope !== this.selectedScope)
          return false;
        if (!query) return true;
        return [track.title, track.library?.name, ...(track.tags || [])].some(
          (value) =>
            String(value || "")
              .toLocaleLowerCase()
              .includes(query),
        );
      });
    },
    selectedChannelState() {
      return this.jukebox.channels[this.selectedChannel] || {};
    },
  },
  methods: {
    trackMeta(track) {
      const scope = this.$t(
        `audio.jukebox.scope.${track.library?.scope || "personal"}`,
      );
      const duration = track.duration
        ? ` · ${this.formatTime(track.duration)}`
        : "";
      return `${this.$t(`audio.jukebox.categoryName.${track.category}`)} · ${scope}${duration}`;
    },
    formatTime(value) {
      const seconds = Math.max(0, Math.round(Number(value) || 0));
      return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, "0")}`;
    },
    occupied() {
      const value = this.selectedChannelState;
      return Boolean(
        (value.trackId || value.title) && value.status !== "stopped",
      );
    },
    activeChannelFor(track) {
      return (
        Object.entries(this.jukebox.channels).find(
          ([, channel]) =>
            Number(channel.trackId) === Number(track.id) &&
            channel.status === "playing",
        )?.[0] || ""
      );
    },
    playLabel(track) {
      if (
        this.activeChannelFor(track) &&
        this.activeChannelFor(track) !== this.selectedChannel
      )
        return this.$t("audio.jukebox.moveAndPlay");
      return this.$t(
        this.occupied()
          ? "audio.jukebox.replaceAndPlay"
          : "audio.jukebox.playNow",
      );
    },
    async playNow(track) {
      const result = await this.run(
        this.$store.dispatch("jukebox/playTrackNow", {
          channelId: this.selectedChannel,
          trackId: track.id,
        }),
      );
      if (result) {
        this.$emit("notice", {
          kind: "success",
          text: this.$t("audio.jukebox.nowPlaying", { title: track.title }),
        });
      }
    },
    async addQueue(track) {
      const result = await this.run(
        this.$store.dispatch("jukebox/addQueueTrack", {
          channelId: this.selectedChannel,
          trackId: track.id,
        }),
      );
      if (result) {
        this.$emit("notice", {
          kind: "success",
          text: this.$t("audio.jukebox.addedToQueue", { title: track.title }),
        });
      }
    },
    canEditTrack(track) {
      return this.canManage && track.library?.scope === "personal";
    },
    beginEdit(track) {
      this.editingTrackId = track.id;
      this.editTitle = track.title || "";
      this.editUrl = track.sourceType === "external" ? track.url || "" : "";
    },
    cancelEdit() {
      this.editingTrackId = null;
      this.editTitle = "";
      this.editUrl = "";
    },
    async saveEdit(track) {
      if (this.editSaving) return;
      this.editSaving = true;
      try {
        const payload = {
          trackId: track.id,
          title: this.editTitle,
        };
        if (track.sourceType === "external") payload.url = this.editUrl;
        const updated = await this.run(
          this.$store.dispatch("jukebox/updatePersonal", payload),
        );
        if (!updated) return;
        this.cancelEdit();
        this.$emit("notice", {
          kind: "success",
          text: this.$t("audio.jukebox.trackUpdated", {
            title: updated.title || payload.title,
          }),
        });
      } finally {
        this.editSaving = false;
      }
    },
    deviceId(slot) {
      return this.voice.externalDeviceIds?.[slot] || "";
    },
    deviceAvailability(slot) {
      return audioInputAvailability(
        this.deviceId(slot),
        this.voice.devices.inputs,
      );
    },
    device(slot) {
      return this.deviceAvailability(slot).device;
    },
    deviceAvailable(slot) {
      return this.deviceAvailability(slot).status === "available";
    },
    deviceName(slot) {
      return (
        this.device(slot)?.label ||
        (this.deviceId(slot)
          ? this.$t("audio.jukebox.deviceUnavailable")
          : this.$t("audio.jukebox.deviceNotConfigured"))
      );
    },
    deviceStatus(slot) {
      return this.$t(
        this.deviceAvailable(slot)
          ? "audio.jukebox.available"
          : this.deviceId(slot)
            ? "audio.jukebox.unavailable"
            : "audio.jukebox.notConfigured",
      );
    },
    async activateDevice(slot) {
      const device = this.device(slot);
      if (!device) return;
      const result = await this.run(
        this.$store.dispatch("jukebox/activateDevice", {
          channelId: this.selectedChannel,
          deviceSlot: slot,
          deviceId: device.deviceId,
          label: device.label,
        }),
      );
      if (result) {
        this.$emit("notice", {
          kind: "success",
          text: this.$t("audio.jukebox.deviceStarted", {
            device: device.label,
            channel: this.$t(`audio.jukebox.channel.${this.selectedChannel}`),
          }),
        });
      }
    },
    async upload() {
      const file = this.$refs.upload?.files?.[0];
      if (!file || this.submitting) return;
      this.submitting = true;
      try {
        const result = await this.run(
          this.$store.dispatch("jukebox/upload", {
            file,
            metadata: {
              title: this.uploadTitle || file.name.replace(/\.[^.]+$/u, ""),
              category: this.uploadCategory,
            },
          }),
        );
        if (!result) return;
        this.uploadTitle = "";
        this.$refs.upload.value = "";
      } finally {
        this.submitting = false;
      }
    },
    async addExternal() {
      if (this.submitting) return;
      this.submitting = true;
      try {
        const result = await this.run(
          this.$store.dispatch("jukebox/addExternal", {
            title: this.externalTitle,
            url: this.externalUrl,
            category: this.externalCategory,
          }),
        );
        if (!result) return;
        this.externalTitle = "";
        this.externalUrl = "";
      } finally {
        this.submitting = false;
      }
    },
    async run(operation) {
      try {
        return await operation;
      } catch (_error) {
        this.$emit("notice", {
          kind: "error",
          text: this.$t("audio.jukebox.errors.default"),
        });
        return null;
      }
    },
  },
};
</script>
