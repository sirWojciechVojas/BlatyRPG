<template>
  <section class="admin-audio">
    <header class="admin-section-heading">
      <div>
        <p>{{ $t("admin.audio.eyebrow") }}</p>
        <h2>{{ $t("admin.audio.title") }}</h2>
        <span>{{ $t("admin.audio.description") }}</span>
      </div>
      <button type="button" class="admin-secondary" @click="load">
        {{ $t("admin.actions.refresh") }}
      </button>
    </header>

    <p v-if="error" class="admin-alert error" role="alert">{{ error }}</p>
    <div v-if="loading" class="admin-loading">
      <i></i>{{ $t("admin.loading") }}
    </div>

    <template v-else>
      <form
        class="admin-panel admin-audio__create"
        @submit.prevent="createLibrary"
      >
        <h3>{{ $t("admin.audio.createLibrary") }}</h3>
        <label>
          <span>{{ $t("admin.audio.libraryName") }}</span>
          <input v-model.trim="newLibrary.name" maxlength="180" required />
        </label>
        <label>
          <span>{{ $t("admin.audio.setting") }}</span>
          <select
            v-model.number="newLibrary.settingId"
            required
            @change="settingChanged"
          >
            <option disabled value="">
              {{ $t("admin.audio.chooseSetting") }}
            </option>
            <option
              v-for="setting in settings"
              :key="setting.id"
              :value="setting.id"
            >
              {{ setting.name }}
            </option>
          </select>
        </label>
        <label>
          <span>{{ $t("admin.audio.system") }}</span>
          <select v-model.number="newLibrary.systemId" required>
            <option disabled value="">
              {{ $t("admin.audio.chooseSystem") }}
            </option>
            <option
              v-for="system in systemsForSetting"
              :key="system.id"
              :value="system.id"
            >
              {{ system.name }}
            </option>
          </select>
        </label>
        <label class="admin-audio__check">
          <input v-model="newLibrary.isActive" type="checkbox" />
          {{ $t("admin.audio.active") }}
        </label>
        <button class="admin-primary" type="submit" :disabled="busy">
          {{ $t("admin.audio.create") }}
        </button>
      </form>

      <p v-if="!libraries.length" class="admin-panel">
        {{ $t("admin.audio.empty") }}
      </p>

      <article
        v-for="library in libraries"
        :key="library.id"
        class="admin-panel admin-audio__library"
      >
        <header>
          <div>
            <h3>{{ library.name }}</h3>
            <p>{{ library.settingName }} · {{ library.systemName }}</p>
          </div>
          <span class="admin-status" :class="{ inactive: !library.isActive }">
            {{
              library.isActive
                ? $t("admin.audio.active")
                : $t("admin.audio.inactive")
            }}
          </span>
        </header>

        <form
          class="admin-audio__library-metadata"
          @submit.prevent="saveLibrary(library)"
        >
          <label>
            <span>{{ $t("admin.audio.libraryName") }}</span>
            <input
              v-model.trim="libraryDrafts[library.id].name"
              maxlength="180"
              required
            />
          </label>
          <label>
            <span>{{ $t("admin.audio.setting") }}</span>
            <select
              v-model.number="libraryDrafts[library.id].settingId"
              required
              @change="librarySettingChanged(library.id)"
            >
              <option
                v-for="setting in settings"
                :key="setting.id"
                :value="setting.id"
              >
                {{ setting.name }}
              </option>
            </select>
          </label>
          <label>
            <span>{{ $t("admin.audio.system") }}</span>
            <select
              v-model.number="libraryDrafts[library.id].systemId"
              required
            >
              <option
                v-for="system in systemsFor(
                  libraryDrafts[library.id].settingId,
                )"
                :key="system.id"
                :value="system.id"
              >
                {{ system.name }}
              </option>
            </select>
          </label>
          <button type="submit" :disabled="busy">
            {{ $t("admin.audio.saveLibrary") }}
          </button>
        </form>

        <div class="admin-audio__library-actions">
          <button
            type="button"
            @click="toggleLibrary(library)"
            :disabled="busy"
          >
            {{
              library.isActive
                ? $t("admin.audio.disable")
                : $t("admin.audio.enable")
            }}
          </button>
          <button
            type="button"
            @click="deleteLibrary(library)"
            :disabled="busy"
          >
            {{ $t("admin.audio.deleteLibrary") }}
          </button>
        </div>

        <div class="admin-audio__sources">
          <form @submit.prevent="uploadTrack(library, $event)">
            <h4>{{ $t("admin.audio.uploadTrack") }}</h4>
            <input
              name="file"
              type="file"
              accept="audio/*,.mp3,.wav,.ogg,.oga,.webm,.m4a,.mp4,.aac,.flac"
              required
            />
            <input
              v-model.trim="trackForms[library.id].title"
              :placeholder="$t('admin.audio.trackTitle')"
              maxlength="180"
            />
            <select v-model="trackForms[library.id].category">
              <option
                v-for="category in categories"
                :key="category"
                :value="category"
              >
                {{ $t(`audio.jukebox.categoryName.${category}`) }}
              </option>
            </select>
            <input
              v-model.trim="trackForms[library.id].tags"
              :placeholder="$t('admin.audio.tags')"
            />
            <input
              v-model.trim="trackForms[library.id].thumbnail"
              type="url"
              :placeholder="$t('admin.audio.thumbnail')"
            />
            <label class="admin-audio__check">
              <input v-model="trackForms[library.id].loop" type="checkbox" />
              {{ $t("audio.jukebox.loop") }}
            </label>
            <button type="submit" :disabled="busy">
              {{ $t("admin.audio.addTrack") }}
            </button>
          </form>

          <form @submit.prevent="addExternal(library)">
            <h4>{{ $t("admin.audio.externalTrack") }}</h4>
            <input
              v-model.trim="externalForms[library.id].title"
              :placeholder="$t('admin.audio.trackTitle')"
              maxlength="180"
              required
            />
            <input
              v-model.trim="externalForms[library.id].url"
              type="url"
              :placeholder="$t('admin.audio.youtubeUrl')"
              required
            />
            <select v-model="externalForms[library.id].category">
              <option
                v-for="category in categories"
                :key="category"
                :value="category"
              >
                {{ $t(`audio.jukebox.categoryName.${category}`) }}
              </option>
            </select>
            <input
              v-model.trim="externalForms[library.id].tags"
              :placeholder="$t('admin.audio.tags')"
            />
            <input
              v-model.trim="externalForms[library.id].thumbnail"
              type="url"
              :placeholder="$t('admin.audio.thumbnail')"
            />
            <label class="admin-audio__check">
              <input v-model="externalForms[library.id].loop" type="checkbox" />
              {{ $t("audio.jukebox.loop") }}
            </label>
            <button type="submit" :disabled="busy">
              {{ $t("admin.audio.addTrack") }}
            </button>
          </form>
        </div>

        <p v-if="!library.tracks.length" class="admin-audio__empty">
          {{ $t("admin.audio.noTracks") }}
        </p>
        <div v-else class="admin-audio__tracks">
          <form
            v-for="track in library.tracks"
            :key="track.id"
            class="admin-audio__track"
            @submit.prevent="saveTrack(track)"
          >
            <span class="admin-audio__track-icon">♫</span>
            <input
              v-model.trim="trackDrafts[track.id].title"
              maxlength="180"
              required
            />
            <select v-model="trackDrafts[track.id].category">
              <option
                v-for="category in categories"
                :key="category"
                :value="category"
              >
                {{ $t(`audio.jukebox.categoryName.${category}`) }}
              </option>
            </select>
            <input
              v-model.trim="trackDrafts[track.id].tags"
              :placeholder="$t('admin.audio.tags')"
            />
            <input
              v-model.trim="trackDrafts[track.id].thumbnail"
              type="url"
              :placeholder="$t('admin.audio.thumbnail')"
            />
            <label class="admin-audio__check">
              <input v-model="trackDrafts[track.id].loop" type="checkbox" />
              {{ $t("audio.jukebox.loop") }}
            </label>
            <small
              >{{ track.sourceType
              }}<template v-if="track.originalName">
                · {{ track.originalName }}</template
              ></small
            >
            <div>
              <button type="submit" :disabled="busy">
                {{ $t("admin.audio.save") }}
              </button>
              <button
                type="button"
                :disabled="busy"
                @click="deleteTrack(track)"
              >
                {{ $t("admin.audio.deleteTrack") }}
              </button>
            </div>
          </form>
        </div>
      </article>
    </template>
  </section>
</template>

<script>
import { adminApiClient } from "@/lib/admin/adminApiClient";

const sourceForm = () => ({
  title: "",
  category: "music",
  tags: "",
  loop: false,
  url: "",
  thumbnail: "",
});

export default {
  name: "AdminAudioTab",
  emits: ["loaded"],
  data: () => ({
    loading: true,
    busy: false,
    error: "",
    libraries: [],
    systems: [],
    settings: [],
    upload: null,
    categories: ["music", "ambient", "sfx"],
    newLibrary: { name: "", systemId: "", settingId: "", isActive: true },
    trackForms: {},
    externalForms: {},
    trackDrafts: {},
    libraryDrafts: {},
  }),
  computed: {
    systemsForSetting() {
      return this.systemsFor(this.newLibrary.settingId);
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    message(error) {
      if (error?.network) return this.$t("admin.errors.network");
      return String(
        error?.code || error?.message || this.$t("admin.audio.error"),
      );
    },
    async load() {
      this.loading = true;
      this.error = "";
      try {
        const catalog = await adminApiClient.audioOverview();
        this.libraries = catalog.libraries;
        this.systems = catalog.systems;
        this.settings = catalog.settings;
        this.upload = catalog.upload;
        this.initializeForms();
        this.$emit("loaded", {
          libraries: this.libraries.length,
          tracks: this.libraries.reduce(
            (total, library) => total + library.tracks.length,
            0,
          ),
        });
      } catch (error) {
        this.error = this.message(error);
      } finally {
        this.loading = false;
      }
    },
    initializeForms() {
      this.libraries.forEach((library) => {
        this.libraryDrafts[library.id] = {
          name: library.name,
          systemId: library.systemId,
          settingId: library.settingId,
          isActive: library.isActive,
        };
        this.trackForms[library.id] ||= sourceForm();
        this.externalForms[library.id] ||= sourceForm();
        library.tracks.forEach((track) => {
          this.trackDrafts[track.id] = {
            title: track.title,
            category: track.category,
            tags: track.tags.join(", "),
            loop: track.loop,
            thumbnail: track.thumbnail || "",
          };
        });
      });
    },
    settingChanged() {
      const setting = this.settings.find(
        (item) => item.id === Number(this.newLibrary.settingId),
      );
      this.newLibrary.systemId =
        setting?.defaultSystemId || setting?.systemIds?.[0] || "";
    },
    systemsFor(settingId) {
      const setting = this.settings.find(
        (item) => item.id === Number(settingId),
      );
      if (!setting) return this.systems;
      return this.systems.filter((system) =>
        setting.systemIds.includes(system.id),
      );
    },
    librarySettingChanged(libraryId) {
      const draft = this.libraryDrafts[libraryId];
      const setting = this.settings.find(
        (item) => item.id === Number(draft.settingId),
      );
      draft.systemId =
        setting?.defaultSystemId || setting?.systemIds?.[0] || "";
    },
    tags(value) {
      return String(value || "")
        .split(",")
        .map((tag) => tag.trim())
        .filter(Boolean);
    },
    async createLibrary() {
      await this.perform(async () => {
        await adminApiClient.createAudioLibrary({ ...this.newLibrary });
        this.newLibrary = {
          name: "",
          systemId: "",
          settingId: "",
          isActive: true,
        };
        await this.load();
      });
    },
    async toggleLibrary(library) {
      await this.perform(async () => {
        await adminApiClient.updateAudioLibrary(library.id, {
          ...this.libraryDrafts[library.id],
          isActive: !library.isActive,
        });
        await this.load();
      });
    },
    async saveLibrary(library) {
      await this.perform(async () => {
        await adminApiClient.updateAudioLibrary(
          library.id,
          this.libraryDrafts[library.id],
        );
        await this.load();
      });
    },
    async deleteLibrary(library) {
      if (!window.confirm(this.$t("admin.audio.deleteLibraryConfirm"))) return;
      await this.perform(async () => {
        await adminApiClient.deleteAudioLibrary(library.id);
        await this.load();
      });
    },
    async uploadTrack(library, event) {
      const form = this.trackForms[library.id];
      const formElement = event.currentTarget;
      const file = formElement.elements.file.files[0];
      if (!file) return;
      await this.perform(async () => {
        await adminApiClient.uploadAudioTrack(library.id, file, {
          title: form.title || file.name.replace(/\.[^.]+$/u, ""),
          category: form.category,
          tags: this.tags(form.tags),
          loop: form.loop,
          thumbnail: form.thumbnail,
        });
        formElement.reset();
        this.trackForms[library.id] = sourceForm();
        await this.load();
      });
    },
    async addExternal(library) {
      const form = this.externalForms[library.id];
      await this.perform(async () => {
        await adminApiClient.createExternalAudioTrack(library.id, {
          title: form.title,
          url: form.url,
          category: form.category,
          tags: this.tags(form.tags),
          loop: form.loop,
          thumbnail: form.thumbnail,
        });
        this.externalForms[library.id] = sourceForm();
        await this.load();
      });
    },
    async saveTrack(track) {
      const draft = this.trackDrafts[track.id];
      await this.perform(async () => {
        await adminApiClient.updateAudioTrack(track.id, {
          ...draft,
          tags: this.tags(draft.tags),
        });
        await this.load();
      });
    },
    async deleteTrack(track) {
      if (!window.confirm(this.$t("admin.audio.deleteTrackConfirm"))) return;
      await this.perform(async () => {
        await adminApiClient.deleteAudioTrack(track.id);
        await this.load();
      });
    },
    async perform(operation) {
      if (this.busy) return;
      this.busy = true;
      this.error = "";
      try {
        await operation();
      } catch (error) {
        this.error = this.message(error);
      } finally {
        this.busy = false;
      }
    },
  },
};
</script>
