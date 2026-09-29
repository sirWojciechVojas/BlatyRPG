<template>
  <section
    class="sound-effects"
    :class="{ 'sound-effects--compact': compact }"
    :aria-label="$t('audio.soundEffects.title')"
  >
    <div
      v-if="effects.phase === 'loading'"
      class="sound-effects__state"
      role="status"
    >
      {{ $t("audio.soundEffects.loading") }}
    </div>
    <div
      v-else-if="effects.phase === 'error'"
      class="sound-effects__state sound-effects__state--error"
      role="alert"
    >
      <span>{{ $t("audio.soundEffects.errors.load") }}</span>
      <button type="button" @click="load">
        {{ $t("audio.soundEffects.retry") }}
      </button>
    </div>

    <template v-else-if="compact">
      <header class="sound-effects__summary">
        <div>
          <strong>{{ $t("audio.soundEffects.title") }}</strong>
          <span :class="`sound-effects__sync--${syncStatus}`">{{
            syncLabel
          }}</span>
        </div>
        <button
          v-if="audioLocked"
          type="button"
          class="sound-effects__unlock"
          @click="unlock"
        >
          {{ $t("audio.soundEffects.unlock") }}
        </button>
      </header>

      <div class="sound-effects__now" aria-live="polite">
        <span>
          <strong>{{
            nowPlaying?.title || $t("audio.soundEffects.nothingPlaying")
          }}</strong>
          <small>{{ nowState }}</small>
        </span>
        <span
          class="sound-effects__meter"
          :class="{ 'sound-effects__meter--active': nowPlaying }"
          :style="{ '--sound-level': nowLevel }"
          :aria-label="$t('audio.soundEffects.level')"
        >
          <i v-for="bar in 5" :key="bar" />
        </span>
      </div>

      <div class="sound-effects__page-head">
        <span>{{ $t("audio.soundEffects.screens") }}</span>
        <small>{{ screenStatus }}</small>
      </div>
      <div class="sound-effects__page-strip">
        <nav :aria-label="$t('audio.soundEffects.screens')">
          <button
            v-for="screen in screens"
            :key="screen.id"
            type="button"
            :aria-pressed="Number(screen.id) === Number(activeScreen?.id)"
            :title="
              screen.name ||
              `${$t('audio.soundEffects.screen')} ${screen.label}`
            "
            @click="selectScreen(screen.id)"
          >
            {{ screen.label }}
          </button>
        </nav>
        <button
          type="button"
          class="sound-effects__add-page"
          :disabled="!canManage || busy"
          :aria-label="$t('audio.soundEffects.addScreen')"
          @click="addScreen"
        >
          +
        </button>
      </div>

      <div
        class="sound-effects__grid sound-effects__grid--compact"
        :class="screenGridClass"
        :style="screenGridStyle"
        :aria-label="$t('audio.soundEffects.slots')"
      >
        <SoundEffectPad
          v-for="cell in cells"
          :key="cell.position"
          :position="cell.position"
          :pad="cell.slot"
          :instance="instanceFor(cell.slot)"
          :loading="loadingFor(cell.slot)"
          :text-lines="screenLayout.textLines"
          @trigger="triggerCompact"
        />
      </div>

      <div v-if="notice" class="sound-effects__notice" role="status">
        {{ notice }}
      </div>
      <SoundEffectsMasterControl
        :volume="effects.masterVolume"
        :can-control="canControl"
        @volume="setMasterVolume"
        @stop="stopAll"
      />
      <p class="sound-effects__help">
        {{ $t("audio.soundEffects.compactHelp") }}
      </p>
    </template>

    <template v-else>
      <div
        class="sound-effects__full"
        :class="{ 'sound-effects__full--focus': focusMode }"
      >
        <aside class="sound-effects__screens-column">
          <header>
            <h3>{{ $t("audio.soundEffects.screens") }}</h3>
            <button
              type="button"
              :disabled="!canManage || busy"
              @click="addScreen"
            >
              +
            </button>
          </header>
          <div class="sound-effects__screen-list">
            <article
              v-for="(screen, index) in screens"
              :key="screen.id"
              :class="{
                active: Number(screen.id) === Number(activeScreen?.id),
              }"
            >
              <button
                type="button"
                class="sound-effects__screen-select"
                @click="selectScreen(screen.id)"
              >
                <strong>{{ screen.label }}</strong>
                <span>{{
                  screen.name ||
                  `${$t("audio.soundEffects.screen")} ${screen.label}`
                }}</span>
                <small>{{
                  $t("audio.soundEffects.assignedCount", {
                    count: visibleSlotCount(screen),
                    total: screenCapacity(screen),
                  })
                }}</small>
              </button>
              <div v-if="canManage" class="sound-effects__screen-actions">
                <button
                  type="button"
                  :aria-label="$t('audio.soundEffects.rename')"
                  @click="renameScreen(screen)"
                >
                  ✎
                </button>
                <button
                  type="button"
                  :aria-label="$t('audio.soundEffects.screenSettings.open')"
                  @click="openScreenSettings(screen)"
                >
                  ⚙
                </button>
                <button
                  type="button"
                  :disabled="index === 0"
                  :aria-label="$t('audio.soundEffects.moveUp')"
                  @click="moveScreen(screen, index - 1)"
                >
                  ↑
                </button>
                <button
                  type="button"
                  :disabled="index === screens.length - 1"
                  :aria-label="$t('audio.soundEffects.moveDown')"
                  @click="moveScreen(screen, index + 1)"
                >
                  ↓
                </button>
                <button
                  type="button"
                  :aria-label="$t('audio.soundEffects.duplicate')"
                  @click="duplicateScreen(screen)"
                >
                  ⧉
                </button>
                <button
                  type="button"
                  :disabled="screens.length <= 1"
                  :aria-label="$t('audio.soundEffects.delete')"
                  @click="deleteScreen(screen)"
                >
                  ×
                </button>
              </div>
            </article>
          </div>
        </aside>

        <main class="sound-effects__grid-column">
          <header class="sound-effects__full-heading">
            <span>
              <small>{{ activeScreen?.label }}</small>
              <h3>{{ activeScreenName }}</h3>
            </span>
            <div class="sound-effects__full-heading-actions">
              <span :class="`sound-effects__sync--${syncStatus}`">{{
                syncLabel
              }}</span>
              <button
                type="button"
                class="sound-effects__focus-toggle"
                :aria-pressed="focusMode"
                :aria-label="focusModeLabel"
                :title="focusModeLabel"
                @click="toggleFocusMode"
              >
                <span aria-hidden="true">{{ focusMode ? "▥" : "⛶" }}</span>
                <span>{{
                  $t(
                    focusMode
                      ? "audio.soundEffects.focusMode.showPanels"
                      : "audio.soundEffects.focusMode.hidePanels",
                  )
                }}</span>
              </button>
            </div>
          </header>
          <button
            v-if="audioLocked"
            type="button"
            class="sound-effects__unlock"
            @click="unlock"
          >
            {{ $t("audio.soundEffects.unlock") }}
          </button>
          <div
            class="sound-effects__grid sound-effects__grid--full"
            :class="screenGridClass"
            :style="screenGridStyle"
            :aria-label="$t('audio.soundEffects.slots')"
          >
            <div
              v-for="cell in cells"
              :key="cell.position"
              class="sound-effects__drop-cell"
              :class="{ selected: cell.position === selectedPosition }"
              @dragover.prevent
              @drop.prevent="dropTrack($event, cell.position)"
            >
              <SoundEffectPad
                :position="cell.position"
                :pad="cell.slot"
                :instance="instanceFor(cell.slot)"
                :loading="loadingFor(cell.slot)"
                :text-lines="screenLayout.textLines"
                @trigger="triggerFull"
              />
              <button
                type="button"
                class="sound-effects__edit-slot"
                :aria-label="
                  $t('audio.soundEffects.editSlot', {
                    number: cell.position + 1,
                  })
                "
                @click.stop="selectPosition(cell.position)"
              >
                ⚙
              </button>
            </div>
          </div>
          <div v-if="notice" class="sound-effects__notice" role="status">
            {{ notice }}
          </div>
          <SoundEffectsMasterControl
            :volume="effects.masterVolume"
            :can-control="canControl"
            @volume="setMasterVolume"
            @stop="stopAll"
          />
        </main>

        <aside class="sound-effects__library-column">
          <section class="sound-effects__library">
            <h3>{{ $t("audio.soundEffects.library") }}</h3>
            <input
              v-model.trim="query"
              type="search"
              :placeholder="$t('audio.soundEffects.search')"
              :aria-label="$t('audio.soundEffects.search')"
            />
            <div class="sound-effects__filters">
              <select
                v-model="category"
                :aria-label="$t('audio.soundEffects.category')"
              >
                <option value="">
                  {{ $t("audio.soundEffects.allCategories") }}
                </option>
                <option value="sfx">SFX</option>
                <option value="ambient">Ambient</option>
                <option value="music">
                  {{ $t("audio.soundEffects.music") }}
                </option>
              </select>
              <select
                v-model="libraryScope"
                :aria-label="$t('audio.soundEffects.libraryScope')"
              >
                <option value="">
                  {{ $t("audio.soundEffects.allLibraries") }}
                </option>
                <option value="setting">
                  {{ $t("audio.soundEffects.settingLibrary") }}
                </option>
                <option value="campaign">
                  {{ $t("audio.soundEffects.campaignLibrary") }}
                </option>
                <option value="personal">
                  {{ $t("audio.soundEffects.gmLibrary") }}
                </option>
              </select>
            </div>
            <p v-if="!filteredTracks.length" class="sound-effects__empty">
              {{ $t("audio.soundEffects.emptyLibrary") }}
            </p>
            <div v-else class="sound-effects__track-list">
              <article
                v-for="track in filteredTracks"
                :key="track.id"
                draggable="true"
                @dragstart="dragTrack($event, track)"
              >
                <button
                  type="button"
                  :aria-label="
                    $t('audio.soundEffects.preview', { title: track.title })
                  "
                  @click="preview(track)"
                >
                  ▶
                </button>
                <span
                  ><strong>{{ track.title }}</strong
                  ><small>{{ trackMeta(track) }}</small></span
                >
                <button
                  type="button"
                  :disabled="!canManage"
                  @click="assignTrack(track)"
                >
                  {{ $t("audio.soundEffects.choose") }}
                </button>
              </article>
            </div>
          </section>

          <form class="sound-effects__editor" @submit.prevent="saveEditor">
            <header>
              <h3>
                {{
                  $t("audio.soundEffects.selectedSlot", {
                    number: selectedPosition + 1,
                  })
                }}
              </h3>
              <button
                v-if="selectedSlot && canManage"
                type="button"
                @click="removeAssignment"
              >
                {{ $t("audio.soundEffects.removeAssignment") }}
              </button>
            </header>
            <p v-if="!draft.audioTrackId" class="sound-effects__empty">
              {{ $t("audio.soundEffects.chooseTrackHint") }}
            </p>
            <template v-else>
              <label
                ><span>{{ $t("audio.soundEffects.name") }}</span
                ><input v-model.trim="draft.name" maxlength="80" required
              /></label>
              <div class="sound-effects__editor-row">
                <label
                  ><span>{{ $t("audio.soundEffects.icon") }}</span
                  ><select v-model="draft.icon">
                    <option v-for="item in icons" :key="item" :value="item">
                      {{ item }}
                    </option>
                  </select></label
                >
                <label
                  ><span>{{ $t("audio.soundEffects.color") }}</span
                  ><input v-model="draft.color" type="color"
                /></label>
                <label
                  ><span>{{ $t("audio.soundEffects.shortcut") }}</span
                  ><input
                    v-model.trim="draft.shortcut"
                    maxlength="24"
                    placeholder="SHIFT+1"
                /></label>
              </div>
              <label
                ><span
                  >{{ $t("audio.soundEffects.slotVolume") }} ·
                  {{ Math.round(draft.volume * 100) }}%</span
                ><input
                  v-model.number="draft.volume"
                  type="range"
                  min="0"
                  max="1"
                  step="0.01"
              /></label>
              <div class="sound-effects__editor-row">
                <label
                  ><span>{{ $t("audio.soundEffects.fadeIn") }}</span
                  ><input
                    v-model.number="draft.fadeInMs"
                    type="number"
                    min="0"
                    max="60000"
                /></label>
                <label
                  ><span>{{ $t("audio.soundEffects.fadeOut") }}</span
                  ><input
                    v-model.number="draft.fadeOutMs"
                    type="number"
                    min="0"
                    max="60000"
                /></label>
              </div>
              <label class="sound-effects__check"
                ><input v-model="draft.loop" type="checkbox" />{{
                  $t("audio.soundEffects.loop")
                }}</label
              >
              <label class="sound-effects__check"
                ><input v-model="draft.stopOthers" type="checkbox" />{{
                  $t("audio.soundEffects.stopOthers")
                }}</label
              >
              <label
                ><span>{{ $t("audio.soundEffects.audience") }}</span
                ><select v-model="draft.audienceScope">
                  <option value="all">
                    {{ $t("audio.soundEffects.everyone") }}
                  </option>
                  <option value="gm">
                    {{ $t("audio.soundEffects.gmOnly") }}
                  </option>
                  <option value="selected">
                    {{ $t("audio.soundEffects.selectedParticipants") }}
                  </option>
                </select></label
              >
              <div
                v-if="draft.audienceScope === 'selected'"
                class="sound-effects__recipients"
              >
                <label v-for="member in members" :key="memberUserId(member)"
                  ><input
                    v-model="draft.recipientUserIds"
                    type="checkbox"
                    :value="memberUserId(member)"
                  />{{ memberName(member) }}</label
                >
              </div>
              <button
                type="submit"
                class="sound-effects__primary"
                :disabled="!canManage || busy"
              >
                {{ $t("audio.soundEffects.save") }}
              </button>
            </template>
          </form>
        </aside>
      </div>
    </template>

    <SoundEffectScreenSettingsDialog
      :open="Boolean(settingsScreen)"
      :screen="settingsScreen"
      :busy="busy"
      @save="saveScreenSettings"
      @cancel="closeScreenSettings"
    />
  </section>
</template>

<script>
import SoundEffectPad from "./SoundEffectPad.vue";
import SoundEffectScreenSettingsDialog from "./SoundEffectScreenSettingsDialog.vue";
import SoundEffectsMasterControl from "./SoundEffectsMasterControl.vue";
import {
  normalizeSoundEffectScreenLayout,
  soundEffectScreenCapacity,
  soundEffectScreenGridStyle,
  visibleSoundEffectSlotCount,
} from "@/lib/audio/soundEffectScreenLayout";
import "./soundEffects.css";

const emptyDraft = () => ({
  audioTrackId: null,
  name: "",
  icon: "waveform",
  color: "#b98a45",
  shortcut: "",
  volume: 1,
  loop: false,
  fadeInMs: 0,
  fadeOutMs: 0,
  stopOthers: false,
  audienceScope: "all",
  recipientUserIds: [],
});

const SOUNDPAD_SOURCE_PREFIX = "soundpad:";

const soundpadSlotId = (channel) => {
  const sourceLabel = String(channel?.sourceLabel || "");
  if (!sourceLabel.startsWith(SOUNDPAD_SOURCE_PREFIX)) return null;
  const slotId = Number(sourceLabel.slice(SOUNDPAD_SOURCE_PREFIX.length));
  return Number.isInteger(slotId) && slotId > 0 ? slotId : null;
};

export default {
  name: "SoundEffectsPanel",
  components: {
    SoundEffectPad,
    SoundEffectScreenSettingsDialog,
    SoundEffectsMasterControl,
  },
  props: {
    campaignId: { type: [Number, String], required: true },
    members: { type: Array, default: () => [] },
    compact: Boolean,
  },
  data: () => ({
    query: "",
    category: "",
    libraryScope: "",
    selectedPosition: 0,
    settingsScreen: null,
    focusMode: false,
    draft: emptyDraft(),
    busy: false,
    notice: "",
    noticeTimer: null,
    icons: [
      "waveform",
      "swords",
      "monster",
      "explosion",
      "weather",
      "magic",
      "bell",
      "music",
      "fire",
      "door",
    ],
  }),
  computed: {
    effects() {
      return this.$store.state.soundEffects;
    },
    screens() {
      return this.effects.screens || [];
    },
    activeScreen() {
      return this.$store.getters["soundEffects/activeScreen"];
    },
    activeScreenName() {
      if (!this.activeScreen) return this.$t("audio.soundEffects.title");
      return (
        this.activeScreen.name ||
        `${this.$t("audio.soundEffects.screen")} ${this.activeScreen.label}`
      );
    },
    screenLayout() {
      return normalizeSoundEffectScreenLayout(this.activeScreen || {});
    },
    activeScreenCapacity() {
      return soundEffectScreenCapacity(this.activeScreen || {});
    },
    screenGridClass() {
      return [
        `sound-effects__grid--style-${this.screenLayout.padStyle}`,
        {
          "sound-effects__grid--dense":
            this.screenLayout.columns > 8 || this.screenLayout.rows > 6,
        },
      ];
    },
    screenGridStyle() {
      return soundEffectScreenGridStyle(this.activeScreen || {});
    },
    cells() {
      return Array.from(
        { length: this.activeScreenCapacity },
        (_, position) => ({
          position,
          slot:
            this.activeScreen?.slots?.find(
              (slot) => Number(slot.position) === position,
            ) || null,
        }),
      );
    },
    selectedSlot() {
      return this.cells[this.selectedPosition]?.slot || null;
    },
    canManage() {
      return this.effects.capabilities?.canManage === true;
    },
    canControl() {
      return this.effects.capabilities?.canControl === true;
    },
    syncStatus() {
      const status = this.$store.state.realtime?.status || "disconnected";
      return ["ready", "connected", "authenticated"].includes(status)
        ? "connected"
        : [
              "connecting",
              "ticketing",
              "authenticating",
              "syncing",
              "reconnecting",
            ].includes(status)
          ? "reconnecting"
          : "disconnected";
    },
    syncLabel() {
      return this.$t(`audio.soundEffects.sync.${this.syncStatus}`);
    },
    focusModeLabel() {
      return this.$t(
        this.focusMode
          ? "audio.soundEffects.focusMode.exit"
          : "audio.soundEffects.focusMode.enter",
      );
    },
    audioLocked() {
      return this.$store.state.jukebox?.audioContextState === "suspended";
    },
    jukeboxEffectChannel() {
      return this.$store.state.jukebox?.channels?.sfx || {};
    },
    jukeboxEffectInstance() {
      const slotId = soundpadSlotId(this.jukeboxEffectChannel);
      if (
        !slotId ||
        !["loading", "playing"].includes(this.jukeboxEffectChannel.status)
      ) {
        return null;
      }
      const slot = this.screens
        .flatMap((screen) => screen.slots || [])
        .find((item) => Number(item.id) === slotId);
      return {
        slotId,
        title:
          slot?.name ||
          this.jukeboxEffectChannel.title ||
          this.$t("audio.soundEffects.title"),
        status: this.jukeboxEffectChannel.status,
        loop: Boolean(this.jukeboxEffectChannel.loop),
        level: Number(this.jukeboxEffectChannel.level) || 0,
      };
    },
    nowPlaying() {
      return (
        Object.values(this.effects.instances || {}).find(
          (instance) => instance.status === "playing",
        ) ||
        (this.jukeboxEffectInstance?.status === "playing"
          ? this.jukeboxEffectInstance
          : null)
      );
    },
    nowLevel() {
      return String(Math.max(0.08, Number(this.nowPlaying?.level) || 0));
    },
    nowState() {
      if (!this.nowPlaying) return this.$t("audio.soundEffects.ready");
      return this.$t(
        this.nowPlaying.loop
          ? "audio.soundEffects.states.loop"
          : "audio.soundEffects.states.playing",
      );
    },
    screenStatus() {
      if (!this.activeScreen) return "";
      return `${this.activeScreen.label} · ${this.visibleSlotCount(this.activeScreen)}/${this.activeScreenCapacity}`;
    },
    filteredTracks() {
      const query = this.query.toLocaleLowerCase();
      return (this.effects.tracks || []).filter((track) => {
        if (this.category && track.category !== this.category) return false;
        if (
          this.libraryScope === "setting" &&
          track.library?.scope !== "system"
        )
          return false;
        if (
          this.libraryScope === "personal" &&
          track.library?.scope !== "personal"
        )
          return false;
        if (this.libraryScope === "campaign" && track.attached !== true)
          return false;
        return (
          !query ||
          [track.title, track.library?.name, ...(track.tags || [])].some(
            (value) =>
              String(value || "")
                .toLocaleLowerCase()
                .includes(query),
          )
        );
      });
    },
  },
  watch: {
    campaignId: {
      immediate: true,
      handler() {
        if (
          Number(this.effects.campaignId) !== Number(this.campaignId) ||
          !["loading", "ready"].includes(this.effects.phase)
        ) {
          this.load();
        }
      },
    },
    selectedSlot: {
      immediate: true,
      handler(slot) {
        this.draft = slot
          ? {
              ...emptyDraft(),
              ...slot,
              recipientUserIds: [...(slot.recipientUserIds || [])],
            }
          : emptyDraft();
      },
    },
    activeScreenCapacity(capacity) {
      if (this.selectedPosition >= capacity) {
        this.selectedPosition = Math.max(0, capacity - 1);
      }
    },
  },
  beforeUnmount() {
    window.clearTimeout(this.noticeTimer);
  },
  methods: {
    load() {
      return this.$store
        .dispatch("soundEffects/enter", this.campaignId)
        .catch(() => {});
    },
    instanceFor(slot) {
      if (!slot) return null;
      return (
        this.$store.getters["soundEffects/instanceForSlot"](slot.id) ||
        (Number(this.jukeboxEffectInstance?.slotId) === Number(slot.id)
          ? this.jukeboxEffectInstance
          : null)
      );
    },
    loadingFor(slot) {
      if (!slot) return false;
      if (
        Number(this.jukeboxEffectInstance?.slotId) === Number(slot.id) &&
        this.jukeboxEffectInstance.status === "loading"
      ) {
        return true;
      }
      const playback = this.$store.getters["soundEffects/playbackForSlot"](
        slot.id,
      );
      return Boolean(playback && !this.instanceFor(slot));
    },
    selectScreen(screenId) {
      this.selectedPosition = 0;
      this.$store
        .dispatch("soundEffects/selectScreen", screenId)
        .catch(() => {});
    },
    triggerCompact(position) {
      const slot = this.cells[position]?.slot;
      if (!slot) {
        this.showNotice(this.$t("audio.soundEffects.assignInFull"));
        return;
      }
      this.trigger(slot);
    },
    triggerFull(position) {
      this.selectPosition(position);
      const slot = this.cells[position]?.slot;
      if (slot) this.trigger(slot);
    },
    trigger(slot) {
      if (!this.canControl) {
        this.showNotice(this.$t("audio.soundEffects.gmControlOnly"));
        return;
      }
      this.$store
        .dispatch("soundEffects/toggleSlot", slot)
        .catch(() =>
          this.showNotice(this.$t("audio.soundEffects.errors.play")),
        );
    },
    selectPosition(position) {
      this.selectedPosition = position;
    },
    async addScreen() {
      await this.run(() => this.$store.dispatch("soundEffects/createScreen"));
    },
    async renameScreen(screen) {
      const name = window.prompt(
        this.$t("audio.soundEffects.renamePrompt"),
        screen.name || "",
      );
      if (name === null) return;
      await this.run(() =>
        this.$store.dispatch("soundEffects/updateScreen", {
          screenId: screen.id,
          name,
        }),
      );
    },
    openScreenSettings(screen) {
      this.settingsScreen = screen;
    },
    closeScreenSettings() {
      if (!this.busy) this.settingsScreen = null;
    },
    toggleFocusMode() {
      this.focusMode = !this.focusMode;
    },
    async saveScreenSettings(layout) {
      if (!this.settingsScreen) return;
      const result = await this.run(() =>
        this.$store.dispatch("soundEffects/updateScreen", {
          screenId: this.settingsScreen.id,
          ...layout,
        }),
      );
      if (result) this.settingsScreen = null;
    },
    screenCapacity(screen) {
      return soundEffectScreenCapacity(screen);
    },
    visibleSlotCount(screen) {
      return visibleSoundEffectSlotCount(screen);
    },
    async moveScreen(screen, position) {
      await this.run(() =>
        this.$store.dispatch("soundEffects/updateScreen", {
          screenId: screen.id,
          position,
        }),
      );
    },
    async duplicateScreen(screen) {
      await this.run(() =>
        this.$store.dispatch("soundEffects/duplicateScreen", screen.id),
      );
    },
    async deleteScreen(screen) {
      if (
        !window.confirm(
          this.$t("audio.soundEffects.deleteConfirm", {
            name: screen.name || screen.label,
          }),
        )
      )
        return;
      await this.run(() =>
        this.$store.dispatch("soundEffects/deleteScreen", screen.id),
      );
    },
    dragTrack(event, track) {
      event.dataTransfer.effectAllowed = "copy";
      event.dataTransfer.setData(
        "application/x-blatyrpg-audio-track",
        String(track.id),
      );
      event.dataTransfer.setData("text/plain", String(track.id));
    },
    dropTrack(event, position) {
      const id = Number(
        event.dataTransfer.getData("application/x-blatyrpg-audio-track") ||
          event.dataTransfer.getData("text/plain"),
      );
      const track = this.effects.tracks.find((item) => Number(item.id) === id);
      if (!track) return;
      this.selectedPosition = position;
      this.assignTrack(track);
    },
    assignTrack(track) {
      if (!this.canManage) return;
      const current = this.cells[this.selectedPosition]?.slot;
      const payload = current
        ? {
            ...current,
            audioTrackId: track.id,
            name: current.name || track.title,
          }
        : { ...emptyDraft(), audioTrackId: track.id, name: track.title };
      this.draft = {
        ...payload,
        recipientUserIds: [...(payload.recipientUserIds || [])],
      };
      return this.saveEditor();
    },
    saveEditor() {
      if (!this.draft.audioTrackId || !this.activeScreen) return;
      return this.run(() =>
        this.$store.dispatch("soundEffects/saveSlot", {
          screenId: this.activeScreen.id,
          position: this.selectedPosition,
          payload: {
            audioTrackId: Number(this.draft.audioTrackId),
            name: this.draft.name,
            icon: this.draft.icon,
            color: this.draft.color,
            shortcut: this.draft.shortcut || null,
            volume: Number(this.draft.volume),
            playMode: this.draft.loop ? "loop" : "once",
            loop: Boolean(this.draft.loop),
            fadeInMs: Number(this.draft.fadeInMs) || 0,
            fadeOutMs: Number(this.draft.fadeOutMs) || 0,
            stopOthers: Boolean(this.draft.stopOthers),
            audienceScope: this.draft.audienceScope,
            recipientUserIds: this.draft.recipientUserIds.map(Number),
          },
        }),
      );
    },
    removeAssignment() {
      if (!this.activeScreen) return;
      return this.run(() =>
        this.$store.dispatch("soundEffects/deleteSlot", {
          screenId: this.activeScreen.id,
          position: this.selectedPosition,
        }),
      );
    },
    preview(track) {
      this.$store
        .dispatch("soundEffects/preview", track)
        .catch(() =>
          this.showNotice(this.$t("audio.soundEffects.errors.preview")),
        );
    },
    trackMeta(track) {
      const seconds = Math.max(0, Math.round(Number(track.duration)));
      const duration = seconds
        ? `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, "0")}`
        : "—:—";
      return `${track.library?.name || this.$t("audio.soundEffects.campaignLibrary")} · ${duration}`;
    },
    memberUserId(member) {
      return Number(member.userId || member.user?.id || member.id);
    },
    memberName(member) {
      return (
        member.displayName ||
        member.username ||
        member.user?.username ||
        `#${this.memberUserId(member)}`
      );
    },
    setMasterVolume(value) {
      this.$store.dispatch("soundEffects/setMasterVolume", value);
    },
    stopAll() {
      this.$store
        .dispatch("soundEffects/stopAll")
        .catch(() =>
          this.showNotice(this.$t("audio.soundEffects.errors.play")),
        );
    },
    unlock() {
      this.$store
        .dispatch("soundEffects/unlock")
        .catch(() =>
          this.showNotice(this.$t("audio.soundEffects.errors.play")),
        );
    },
    showNotice(message) {
      window.clearTimeout(this.noticeTimer);
      this.notice = message;
      this.noticeTimer = window.setTimeout(() => {
        this.notice = "";
      }, 4000);
    },
    async run(operation) {
      if (this.busy) return null;
      this.busy = true;
      try {
        const result = await operation();
        this.showNotice(this.$t("audio.soundEffects.saved"));
        return result;
      } catch (error) {
        this.showNotice(
          error?.code === "sound_effect_shortcut_conflict"
            ? this.$t("audio.soundEffects.errors.shortcut")
            : this.$t("audio.soundEffects.errors.save"),
        );
        return null;
      } finally {
        this.busy = false;
      }
    },
  },
};
</script>
