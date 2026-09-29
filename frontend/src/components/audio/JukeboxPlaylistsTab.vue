<template>
  <section class="jukebox-tab-view" role="tabpanel">
    <div class="jukebox-section-heading">
      <h3>{{ $t("audio.jukebox.playlists") }}</h3>
      <button
        v-if="canManage && !creating"
        type="button"
        class="jukebox-text-action"
        @click="creating = true"
      >
        ＋ {{ $t("audio.jukebox.newPlaylist") }}
      </button>
    </div>
    <form v-if="creating" class="jukebox-inline-form" @submit.prevent="create">
      <input
        v-model.trim="newName"
        :placeholder="$t('audio.jukebox.playlistName')"
        maxlength="180"
        required
      />
      <button type="submit">{{ $t("audio.jukebox.create") }}</button>
      <button type="button" @click="creating = false">
        {{ $t("audio.jukebox.cancel") }}
      </button>
    </form>
    <p v-if="!jukebox.playlists.length" class="jukebox-empty">
      {{ $t("audio.jukebox.emptyPlaylists") }}
    </p>
    <div class="jukebox-playlist-list">
      <article
        v-for="playlist in jukebox.playlists"
        :key="playlist.id"
        class="jukebox-playlist-row"
      >
        <header>
          <span>
            <strong v-if="editing !== playlist.id">{{ playlist.name }}</strong>
            <input
              v-else
              v-model.trim="editName"
              maxlength="180"
              :aria-label="$t('audio.jukebox.playlistName')"
              @keyup.enter="rename(playlist)"
            />
            <small>{{
              $t("audio.jukebox.playlistTrackCount", {
                count: playlist.items.length,
              })
            }}</small>
          </span>
          <span v-if="canManage" class="jukebox-icon-actions">
            <button
              type="button"
              :aria-label="$t('audio.jukebox.rename')"
              @click="toggleEdit(playlist)"
            >
              ✎
            </button>
            <button
              type="button"
              :aria-label="$t('audio.jukebox.deletePlaylist')"
              @click="removePlaylist(playlist)"
            >
              ×
            </button>
          </span>
        </header>
        <label class="jukebox-field jukebox-field--inline">
          <span>{{ $t("audio.jukebox.targetChannel") }}</span>
          <select
            :value="targetFor(playlist.id)"
            @change="setTarget(playlist.id, $event.target.value)"
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
        <div v-if="canManage" class="jukebox-playlist-actions">
          <button
            type="button"
            :disabled="!playlist.items.length"
            @click="start(playlist)"
          >
            ▶ {{ $t("audio.jukebox.startFromBeginning") }}
          </button>
          <button
            type="button"
            :disabled="!playlist.items.length"
            @click="enqueue(playlist)"
          >
            ＋ {{ $t("audio.jukebox.addPlaylistToQueue") }}
          </button>
        </div>
        <button
          type="button"
          class="jukebox-queue-toggle"
          :aria-expanded="expanded[playlist.id] === true"
          @click="toggleExpanded(playlist.id)"
        >
          <span>{{ $t("audio.jukebox.tracks") }}</span
          ><span>{{ expanded[playlist.id] ? "▴" : "▾" }}</span>
        </button>
        <div v-if="expanded[playlist.id]" class="jukebox-playlist-editor">
          <ol>
            <li v-for="(item, index) in playlist.items" :key="item.id">
              <span>{{ index + 1 }}</span
              ><strong>{{ item.title }}</strong>
              <span v-if="canManage" class="jukebox-icon-actions">
                <button
                  type="button"
                  :disabled="index === 0"
                  :aria-label="$t('audio.jukebox.moveUp')"
                  @click="move(playlist, item, index - 1)"
                >
                  ↑
                </button>
                <button
                  type="button"
                  :disabled="index === playlist.items.length - 1"
                  :aria-label="$t('audio.jukebox.moveDown')"
                  @click="move(playlist, item, index + 1)"
                >
                  ↓
                </button>
                <button
                  type="button"
                  :aria-label="$t('audio.jukebox.remove')"
                  @click="removeItem(playlist, item)"
                >
                  ×
                </button>
              </span>
            </li>
          </ol>
          <div v-if="canManage" class="jukebox-inline-form">
            <select
              :value="trackFor(playlist.id)"
              :aria-label="$t('audio.jukebox.addTrack')"
              @change="setTrack(playlist.id, $event.target.value)"
            >
              <option value="">{{ $t("audio.jukebox.select") }}</option>
              <option
                v-for="track in availableTracks(playlist)"
                :key="track.id"
                :value="track.id"
              >
                {{ track.title }}
              </option>
            </select>
            <button
              type="button"
              :disabled="!trackFor(playlist.id)"
              @click="addItem(playlist)"
            >
              ＋ {{ $t("audio.jukebox.add") }}
            </button>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>

<script>
export default {
  name: "JukeboxPlaylistsTab",
  props: {
    jukebox: { type: Object, required: true },
    channelIds: { type: Array, required: true },
    canManage: Boolean,
  },
  emits: ["notice"],
  data: () => ({
    creating: false,
    newName: "",
    editing: null,
    editName: "",
    expanded: {},
    targets: {},
    selectedTracks: {},
  }),
  methods: {
    targetFor(id) {
      return this.targets[id] || "music";
    },
    setTarget(id, channelId) {
      this.targets = { ...this.targets, [id]: channelId };
    },
    trackFor(id) {
      return this.selectedTracks[id] || "";
    },
    setTrack(id, trackId) {
      this.selectedTracks = { ...this.selectedTracks, [id]: trackId };
    },
    toggleExpanded(id) {
      this.expanded = { ...this.expanded, [id]: !this.expanded[id] };
    },
    availableTracks(playlist) {
      const ids = new Set(playlist.items.map((item) => Number(item.trackId)));
      return this.jukebox.tracks.filter((track) => !ids.has(Number(track.id)));
    },
    async create() {
      const playlist = await this.run("jukebox/createPlaylist", this.newName);
      if (!playlist) return;
      this.newName = "";
      this.creating = false;
      this.expanded = { ...this.expanded, [playlist.id]: true };
    },
    toggleEdit(playlist) {
      if (this.editing === playlist.id) return this.rename(playlist);
      this.editing = playlist.id;
      this.editName = playlist.name;
    },
    async rename(playlist) {
      if (!this.editName) return;
      const result = await this.run("jukebox/renamePlaylist", {
        playlistId: playlist.id,
        name: this.editName,
      });
      if (result) this.editing = null;
    },
    async removePlaylist(playlist) {
      if (
        !window.confirm(
          this.$t("audio.jukebox.deletePlaylistConfirmation", {
            name: playlist.name,
          }),
        )
      )
        return;
      await this.run("jukebox/deletePlaylist", playlist.id);
    },
    async addItem(playlist) {
      const trackId = Number(this.trackFor(playlist.id));
      if (!trackId) return;
      const result = await this.run("jukebox/addPlaylistItem", {
        playlistId: playlist.id,
        trackId,
      });
      if (result) this.setTrack(playlist.id, "");
    },
    move(playlist, item, position) {
      return this.run("jukebox/movePlaylistItem", {
        playlistId: playlist.id,
        itemId: item.id,
        position,
      });
    },
    removeItem(playlist, item) {
      return this.run("jukebox/removePlaylistItem", {
        playlistId: playlist.id,
        itemId: item.id,
      });
    },
    async start(playlist) {
      const result = await this.run("jukebox/startPlaylist", {
        channelId: this.targetFor(playlist.id),
        playlistId: playlist.id,
      });
      if (result)
        this.$emit("notice", {
          kind: "success",
          text: this.$t("audio.jukebox.playlistStarted", {
            name: playlist.name,
          }),
        });
    },
    async enqueue(playlist) {
      const result = await this.run("jukebox/addPlaylistToQueue", {
        channelId: this.targetFor(playlist.id),
        playlistId: playlist.id,
      });
      if (result)
        this.$emit("notice", {
          kind: "success",
          text: this.$t("audio.jukebox.playlistQueued", {
            name: playlist.name,
          }),
        });
    },
    async run(action, payload) {
      try {
        return await this.$store.dispatch(action, payload);
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
