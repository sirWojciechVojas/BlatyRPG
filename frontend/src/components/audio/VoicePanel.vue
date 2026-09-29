<template>
  <section class="audio-panel voice-panel voice-panel-shell">
    <div
      class="voice-connection"
      :data-status="connectionState"
      role="status"
      aria-live="polite"
    >
      <span class="voice-connection__indicator" aria-hidden="true"></span>
      <span class="voice-connection__copy">
        <strong>{{ statusLabel }}</strong>
        <small>{{ $t("audio.voice.channelSubtitle") }}</small>
      </span>
      <span
        class="voice-connection__quality"
        :data-quality="connectionQuality"
        :aria-label="qualityLabel"
        :title="qualityLabel"
      >
        <i
          v-for="index in 4"
          :key="index"
          :class="{ active: index <= activeQualityBars }"
          aria-hidden="true"
        ></i>
      </span>
    </div>

    <p v-if="voice.error" class="voice-panel__error" role="alert">
      {{ errorLabel }}
    </p>

    <button
      v-if="voice.audioBlocked || voice.audioContextState === 'suspended'"
      type="button"
      class="voice-panel__unlock"
      @click="unlock"
    >
      {{ $t("audio.voice.unlock") }}
    </button>

    <div v-if="!joined" class="voice-panel__join">
      <p>{{ $t("audio.voice.joinPrompt") }}</p>
      <button
        type="button"
        class="voice-panel__join-button"
        :disabled="voice.joining"
        @click="join"
      >
        {{ $t(voice.error ? "audio.voice.retry" : "audio.voice.join") }}
      </button>
    </div>

    <VideoStage v-if="joined" :joined="joined" />

    <section class="voice-members" aria-labelledby="voice-members-heading">
      <h3 id="voice-members-heading">
        {{
          $t("audio.voice.participantsCount", {
            count: sortedParticipants.length,
          })
        }}
      </h3>

      <div class="voice-members__list">
        <p v-if="!sortedParticipants.length" class="voice-members__empty">
          {{ $t("audio.voice.empty") }}
        </p>

        <article
          v-for="participant in sortedParticipants"
          :key="participant.identity"
          class="voice-member"
          :class="{
            'voice-member--speaking': isSpeaking(participant),
            'voice-member--local': participant.local,
            'voice-member--settings-open':
              contextMenu.identity === participant.identity,
          }"
          @contextmenu.prevent.stop="
            openParticipantContextMenu($event, participant)
          "
        >
          <div class="voice-member__main">
            <span class="voice-member__avatar-wrap">
              <img
                v-if="participant.avatar"
                class="voice-member__avatar"
                :src="participant.avatar"
                alt=""
              />
              <span v-else class="voice-member__avatar" aria-hidden="true">
                {{ initials(participant.nickname) }}
              </span>
              <span
                v-if="isSpeaking(participant)"
                class="voice-member__speaking-dot"
                aria-hidden="true"
              ></span>
            </span>

            <div class="voice-member__identity">
              <strong>
                {{ participant.nickname }}
                <span v-if="participant.local" class="voice-member__you">
                  {{ $t("audio.voice.you") }}
                </span>
              </strong>
              <span
                class="voice-member__role"
                :class="{ 'voice-member__role--gm': isGm(participant) }"
              >
                {{ roleLabel(participant) }}
              </span>
              <span
                class="voice-member__state"
                :data-state="participantStateKey(participant)"
                aria-live="polite"
              >
                {{ participantStateLabel(participant) }}
              </span>

              <span
                class="voice-member__meter"
                :class="{
                  'voice-member__meter--unavailable':
                    participant.meterAvailable === false,
                }"
                role="meter"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-valuenow="meterPercentage(participant)"
                :aria-label="meterLabel(participant)"
                :title="meterTitle(participant)"
              >
                <i
                  v-for="index in meterSegments"
                  :key="index"
                  :class="{
                    active: meterSegmentActive(participant, index),
                  }"
                  aria-hidden="true"
                ></i>
              </span>
            </div>

            <div class="voice-member__actions">
              <button
                v-if="participant.local"
                type="button"
                class="voice-member__microphone voice-member__microphone--button voice-member__media-button"
                :data-state="participantStateKey(participant)"
                :class="{ active: voice.muted }"
                :aria-label="localMicrophoneActionLabel"
                :aria-pressed="voice.muted === true"
                :title="localMicrophoneActionLabel"
                @click="toggleMute"
              >
                <TableRailIcon :name="participantIcon(participant)" />
              </button>
              <span
                v-else
                class="voice-member__microphone"
                :data-state="participantStateKey(participant)"
                :aria-label="participantStateLabel(participant)"
                :title="participantStateLabel(participant)"
              >
                <TableRailIcon :name="participantIcon(participant)" />
              </span>
              <button
                v-if="participant.local"
                type="button"
                class="voice-member__camera voice-member__camera--button voice-member__media-button"
                :data-state="participantCameraOn(participant) ? 'on' : 'off'"
                :class="{ active: !participantCameraOn(participant) }"
                :disabled="video.busy"
                :aria-label="localCameraActionLabel(participant)"
                :aria-pressed="participantCameraOn(participant)"
                :title="localCameraActionLabel(participant)"
                @click="toggleCamera"
              >
                <TableRailIcon :name="participantCameraIcon(participant)" />
              </button>
              <span
                v-else
                class="voice-member__camera"
                :data-state="participantCameraOn(participant) ? 'on' : 'off'"
                :aria-label="participantCameraLabel(participant)"
                :title="participantCameraLabel(participant)"
              >
                <TableRailIcon :name="participantCameraIcon(participant)" />
              </span>
              <button
                type="button"
                class="voice-member__settings-trigger"
                :aria-label="
                  $t('audio.voice.participantSettings.open', {
                    name: participant.nickname,
                  })
                "
                :title="
                  $t('audio.voice.participantSettings.open', {
                    name: participant.nickname,
                  })
                "
                aria-haspopup="menu"
                :aria-expanded="contextMenu.identity === participant.identity"
                :aria-controls="contextMenuId"
                @click="
                  openParticipantContextMenuFromButton($event, participant)
                "
              >
                <TableRailIcon name="more" />
              </button>
            </div>
          </div>
        </article>
      </div>
    </section>

    <div class="voice-ptt" :class="{ 'voice-ptt--pressed': pttPressed }">
      <kbd>{{ $t("audio.voice.spaceKey") }}</kbd>
      <span>{{ $t("audio.voice.pttInstruction") }}</span>
      <button
        type="button"
        class="voice-switch"
        role="switch"
        :aria-checked="voice.pushToTalk === true"
        :aria-label="$t('audio.voice.pttToggle')"
        :title="$t('audio.voice.pttToggle')"
        :disabled="!joined"
        @click="setPtt(!voice.pushToTalk)"
      >
        <span aria-hidden="true"></span>
      </button>
    </div>

    <footer class="voice-self">
      <div v-if="joined" class="voice-self__identity">
        <img
          v-if="currentParticipant?.avatar"
          :src="currentParticipant.avatar"
          alt=""
        />
        <span v-else class="voice-self__avatar" aria-hidden="true">
          {{ initials(currentParticipant?.nickname) }}
        </span>
        <span>
          <strong>{{
            currentParticipant?.nickname || $t("audio.voice.you")
          }}</strong>
          <small :data-status="connectionState">
            <i aria-hidden="true"></i>
            {{ selfStatusLabel }}
          </small>
        </span>
      </div>

      <div
        class="voice-self__controls"
        role="toolbar"
        :aria-label="$t('audio.voice.controls')"
      >
        <button
          type="button"
          :class="{ active: voice.muted }"
          :disabled="!joined"
          :aria-label="
            $t(voice.muted ? 'audio.voice.unmute' : 'audio.voice.mute')
          "
          :aria-pressed="voice.muted === true"
          :title="$t(voice.muted ? 'audio.voice.unmute' : 'audio.voice.mute')"
          @click="toggleMute"
        >
          <span class="voice-self__control-icon">
            <TableRailIcon
              :name="voice.muted ? 'microphoneOff' : 'microphone'"
            />
          </span>
          <small>{{ $t("audio.voice.microphone") }}</small>
        </button>
        <button
          type="button"
          :class="{ active: voice.deafened }"
          :disabled="!joined"
          :aria-label="
            $t(
              voice.deafened
                ? 'audio.voice.unmuteIncoming'
                : 'audio.voice.muteIncoming',
            )
          "
          :aria-pressed="voice.deafened === true"
          :title="
            $t(
              voice.deafened
                ? 'audio.voice.unmuteIncoming'
                : 'audio.voice.muteIncoming',
            )
          "
          @click="toggleDeafen"
        >
          <span class="voice-self__control-icon">
            <TableRailIcon name="headphones" />
          </span>
          <small>{{ $t("audio.voice.headphones") }}</small>
        </button>
        <button
          type="button"
          :disabled="!joined"
          :aria-label="$t('audio.voice.audioSettings')"
          :title="$t('audio.voice.audioSettings')"
          @click="$emit('open-settings')"
        >
          <span class="voice-self__control-icon">
            <TableRailIcon name="settings" />
          </span>
          <small>{{ $t("audio.voice.settings") }}</small>
        </button>
        <button
          type="button"
          class="voice-self__disconnect"
          :disabled="!joined"
          :aria-label="$t('audio.voice.leave')"
          :title="$t('audio.voice.leave')"
          @click="leave"
        >
          <span class="voice-self__control-icon">
            <TableRailIcon name="phoneOff" />
          </span>
          <small>{{ $t("audio.voice.disconnect") }}</small>
        </button>
      </div>
    </footer>

    <Teleport to="body">
      <div
        v-if="contextParticipant"
        :id="contextMenuId"
        ref="contextMenu"
        class="voice-context-menu"
        :style="contextMenuStyle"
        role="menu"
        tabindex="-1"
        :aria-labelledby="`${contextMenuId}-title`"
        @contextmenu.prevent
        @keydown.esc="closeParticipantContextMenu"
      >
        <header class="voice-context-menu__header">
          <img
            v-if="contextParticipant.avatar"
            :src="contextParticipant.avatar"
            alt=""
          />
          <span v-else class="voice-context-menu__avatar" aria-hidden="true">
            {{ initials(contextParticipant.nickname) }}
          </span>
          <span>
            <strong :id="`${contextMenuId}-title`">
              {{ contextParticipant.nickname }}
            </strong>
            <small>{{ roleLabel(contextParticipant) }}</small>
          </span>
        </header>

        <template v-if="!contextParticipant.local">
          <section class="voice-context-menu__mix" role="group">
            <p>{{ $t("audio.voice.participantSettings.localOnly") }}</p>
            <label>
              <span>
                {{ $t("audio.voice.volume") }}
                <output>{{ percent(contextParticipant.volume) }}</output>
              </span>
              <input
                :value="contextParticipant.volume"
                type="range"
                min="0"
                max="1"
                step="0.01"
                :aria-label="$t('audio.voice.volume')"
                @input="
                  participantVolume(
                    contextParticipant.identity,
                    $event.target.value,
                  )
                "
              />
            </label>
            <label>
              <span>
                {{ $t("audio.voice.pan") }}
                <output>{{ Number(contextParticipant.pan).toFixed(1) }}</output>
              </span>
              <input
                :value="contextParticipant.pan"
                type="range"
                min="-1"
                max="1"
                step="0.1"
                :aria-label="$t('audio.voice.pan')"
                @input="
                  participantPan(
                    contextParticipant.identity,
                    $event.target.value,
                  )
                "
              />
            </label>
          </section>
          <div class="voice-context-menu__separator" role="separator"></div>
          <button
            type="button"
            role="menuitemcheckbox"
            :aria-checked="contextParticipant.localMuted === true"
            @click="toggleParticipantMute(contextParticipant)"
          >
            <TableRailIcon
              :name="
                contextParticipant.localMuted ? 'microphoneOff' : 'microphone'
              "
            />
            <span>{{
              $t(
                contextParticipant.localMuted
                  ? "audio.voice.participantSettings.localUnmute"
                  : "audio.voice.participantSettings.localMute",
              )
            }}</span>
            <i class="voice-context-menu__check" aria-hidden="true"></i>
          </button>
          <button
            type="button"
            role="menuitem"
            @click="resetParticipantMix(contextParticipant)"
          >
            <TableRailIcon name="refresh" />
            <span>{{ $t("audio.voice.participantSettings.reset") }}</span>
          </button>
        </template>

        <template v-else>
          <button type="button" role="menuitem" @click="toggleMute">
            <TableRailIcon
              :name="voice.muted ? 'microphoneOff' : 'microphone'"
            />
            <span>{{ localMicrophoneActionLabel }}</span>
          </button>
          <button type="button" role="menuitem" @click="openAudioSettings">
            <TableRailIcon name="settings" />
            <span>{{ $t("audio.voice.audioSettings") }}</span>
          </button>
          <div class="voice-context-menu__separator" role="separator"></div>
          <button
            type="button"
            class="voice-context-menu__danger"
            role="menuitem"
            @click="leave"
          >
            <TableRailIcon name="phoneOff" />
            <span>{{ $t("audio.voice.leave") }}</span>
          </button>
        </template>
      </div>
    </Teleport>
  </section>
</template>

<script>
import TableRailIcon from "@/components/vtt/table/TableRailIcon.vue";
import VideoStage from "@/components/video/VideoStage.vue";
import "./audioDock.css";
import "./voicePanel.css";

const connectionStates = new Set([
  "connected",
  "connecting",
  "reconnecting",
  "disconnected",
  "error",
]);
const connectionQualities = new Set([
  "excellent",
  "good",
  "poor",
  "lost",
  "unknown",
]);

export default {
  name: "VoicePanel",
  components: { TableRailIcon, VideoStage },
  props: {
    campaignId: { type: [Number, String], required: true },
  },
  emits: ["open-settings"],
  data: () => ({
    meterSegments: 12,
    contextMenu: { identity: "", x: 0, y: 0 },
  }),
  computed: {
    voice() {
      return this.$store.state.voice || { participants: [] };
    },
    video() {
      return (
        this.$store.state.video || {
          cameraEnabled: false,
          busy: false,
          participants: [],
        }
      );
    },
    participants() {
      return Array.isArray(this.voice.participants)
        ? this.voice.participants
        : [];
    },
    joined() {
      return ["connected", "reconnecting"].includes(this.voice.status);
    },
    connectionState() {
      return connectionStates.has(this.voice.status)
        ? this.voice.status
        : "connecting";
    },
    currentParticipant() {
      return this.participants.find((participant) => participant.local) || null;
    },
    contextParticipant() {
      return (
        this.participants.find(
          (participant) => participant.identity === this.contextMenu.identity,
        ) || null
      );
    },
    contextMenuId() {
      return "voice-participant-context-menu";
    },
    contextMenuStyle() {
      return {
        left: `${this.contextMenu.x}px`,
        top: `${this.contextMenu.y}px`,
      };
    },
    connectionQuality() {
      if (!this.joined) return "unknown";
      const quality = String(
        this.currentParticipant?.connectionQuality || "unknown",
      ).toLowerCase();
      return connectionQualities.has(quality) ? quality : "unknown";
    },
    activeQualityBars() {
      return { excellent: 4, good: 3, poor: 1 }[this.connectionQuality] || 0;
    },
    qualityLabel() {
      return this.$t(`audio.voice.quality.${this.connectionQuality}`);
    },
    sortedParticipants() {
      return [...this.participants].sort((left, right) => {
        const speaking =
          Number(this.isSpeaking(right)) - Number(this.isSpeaking(left));
        if (speaking) return speaking;
        const gm = Number(this.isGm(right)) - Number(this.isGm(left));
        if (gm) return gm;
        return String(left.nickname || "").localeCompare(
          String(right.nickname || ""),
        );
      });
    },
    statusLabel() {
      if (
        this.voice.status === "error" &&
        this.voice.error === "microphone_permission_denied"
      ) {
        return this.$t("audio.voice.status.permissionDenied");
      }
      return this.$t(`audio.voice.status.${this.connectionState}`);
    },
    selfStatusLabel() {
      return this.$t(
        this.connectionState === "reconnecting"
          ? "audio.voice.selfReconnecting"
          : "audio.voice.selfConnected",
      );
    },
    errorLabel() {
      if (!this.voice.error) return "";
      const key = `audio.voice.errors.${this.voice.error}`;
      return this.$te(key)
        ? this.$t(key)
        : this.$t("audio.voice.errors.default");
    },
    pttPressed() {
      return this.voice.pushToTalk && this.voice.pushToTalkPressed;
    },
    localMicrophoneActionLabel() {
      return this.$t(
        this.voice.muted ? "audio.voice.unmute" : "audio.voice.mute",
      );
    },
  },
  watch: {
    joined(value) {
      if (!value) this.closeParticipantContextMenu();
    },
    participants(value) {
      if (
        this.contextMenu.identity &&
        !value.some(
          (participant) => participant.identity === this.contextMenu.identity,
        )
      ) {
        this.closeParticipantContextMenu();
      }
    },
  },
  created() {
    this.$store.dispatch("voice/initialize");
  },
  mounted() {
    window.addEventListener("pointerdown", this.handleOutsidePointerDown);
    window.addEventListener("keydown", this.handleEscape);
    window.addEventListener("blur", this.releasePushToTalk);
    window.addEventListener("resize", this.closeParticipantContextMenu);
    window.addEventListener("scroll", this.closeParticipantContextMenu, true);
  },
  beforeUnmount() {
    window.removeEventListener("pointerdown", this.handleOutsidePointerDown);
    window.removeEventListener("keydown", this.handleEscape);
    window.removeEventListener("blur", this.releasePushToTalk);
    window.removeEventListener("resize", this.closeParticipantContextMenu);
    window.removeEventListener(
      "scroll",
      this.closeParticipantContextMenu,
      true,
    );
    this.closeParticipantContextMenu();
    this.releasePushToTalk();
  },
  methods: {
    join() {
      return this.$store
        .dispatch("voice/join", this.campaignId)
        .catch(() => {});
    },
    leave() {
      this.closeParticipantContextMenu();
      return this.$store.dispatch("voice/leave").catch(() => {});
    },
    toggleMute() {
      return this.$store.dispatch("voice/toggleMute").catch(() => {});
    },
    toggleCamera() {
      return this.$store.dispatch("video/toggleCamera").catch(() => {});
    },
    toggleDeafen() {
      return this.$store.dispatch("voice/setDeafened", !this.voice.deafened);
    },
    unlock() {
      return this.$store.dispatch("voice/unlockAudio").catch(() => {});
    },
    setPtt(value) {
      this.$store.dispatch("voice/setPushToTalk", Boolean(value));
    },
    releasePushToTalk() {
      if (this.voice.pushToTalkPressed) {
        this.$store.dispatch("voice/pressPushToTalk", false);
      }
    },
    participantVolume(identity, value) {
      this.$store.dispatch("voice/setParticipantVolume", {
        identity,
        volume: Number(value),
      });
    },
    participantPan(identity, value) {
      this.$store.dispatch("voice/setParticipantPan", {
        identity,
        pan: Number(value),
      });
    },
    toggleParticipantMute(participant) {
      this.$store.dispatch("voice/setParticipantMuted", {
        identity: participant.identity,
        muted: participant.localMuted !== true,
      });
    },
    resetParticipantMix(participant) {
      const identity = participant.identity;
      this.$store.dispatch("voice/setParticipantVolume", {
        identity,
        volume: 1,
      });
      this.$store.dispatch("voice/setParticipantPan", { identity, pan: 0 });
      this.$store.dispatch("voice/setParticipantMuted", {
        identity,
        muted: false,
      });
    },
    openParticipantContextMenu(event, participant) {
      const margin = 8;
      this.contextMenu = {
        identity: participant.identity,
        x: Math.max(margin, Number(event.clientX) || margin),
        y: Math.max(margin, Number(event.clientY) || margin),
      };
      this.$nextTick(() => {
        this.fitParticipantContextMenu();
        this.$refs.contextMenu?.focus();
      });
    },
    openParticipantContextMenuFromButton(event, participant) {
      const rect = event.currentTarget.getBoundingClientRect();
      this.openParticipantContextMenu(
        {
          clientX: rect.right,
          clientY: rect.bottom,
        },
        participant,
      );
    },
    fitParticipantContextMenu() {
      const element = this.$refs.contextMenu;
      if (!element) return;
      const margin = 8;
      const bounds = element.getBoundingClientRect();
      this.contextMenu = {
        ...this.contextMenu,
        x: Math.max(
          margin,
          Math.min(
            this.contextMenu.x,
            window.innerWidth - bounds.width - margin,
          ),
        ),
        y: Math.max(
          margin,
          Math.min(
            this.contextMenu.y,
            window.innerHeight - bounds.height - margin,
          ),
        ),
      };
    },
    closeParticipantContextMenu() {
      if (!this.contextMenu.identity) return;
      this.contextMenu = { identity: "", x: 0, y: 0 };
    },
    openAudioSettings() {
      this.closeParticipantContextMenu();
      this.$emit("open-settings");
    },
    handleOutsidePointerDown(event) {
      if (
        this.contextMenu.identity &&
        !event.target?.closest?.(".voice-context-menu") &&
        !event.target?.closest?.(".voice-member__settings-trigger")
      ) {
        this.closeParticipantContextMenu();
      }
    },
    handleEscape(event) {
      if (event.key === "Escape") this.closeParticipantContextMenu();
    },
    isGm(participant) {
      return ["gm", "game_master", "admin"].includes(
        String(participant.role || "").toLowerCase(),
      );
    },
    roleLabel(participant) {
      const role = String(participant.role || "player").toLowerCase();
      const key = this.isGm(participant)
        ? "gm"
        : ["assistant", "observer"].includes(role)
          ? role
          : "player";
      return this.$t(`audio.voice.roles.${key}`);
    },
    participantMuted(participant) {
      if (!participant.local) return participant.muted === true;
      return (
        this.voice.muted === true ||
        participant.muted === true ||
        (this.voice.pushToTalk === true &&
          this.voice.pushToTalkPressed !== true)
      );
    },
    participantStateKey(participant) {
      if (
        this.connectionState === "reconnecting" ||
        String(participant.connectionQuality).toLowerCase() === "lost"
      ) {
        return "connectionLost";
      }
      if (participant.hasMicrophone === false) return "noMicrophone";
      if (this.participantMuted(participant)) return "muted";
      return participant.speaking ? "speaking" : "silent";
    },
    participantStateLabel(participant) {
      return this.$t(
        `audio.voice.participantState.${this.participantStateKey(participant)}`,
      );
    },
    participantIcon(participant) {
      return ["muted", "noMicrophone"].includes(
        this.participantStateKey(participant),
      )
        ? "microphoneOff"
        : "microphone";
    },
    videoParticipant(participant) {
      const videoParticipants = Array.isArray(this.video.participants)
        ? this.video.participants
        : [];
      return (
        videoParticipants.find(
          (candidate) => candidate.identity === participant.identity,
        ) || null
      );
    },
    participantCameraOn(participant) {
      return participant.local
        ? this.video.cameraEnabled === true
        : this.videoParticipant(participant)?.cameraOn === true;
    },
    participantCameraIcon(participant) {
      return this.participantCameraOn(participant) ? "camera" : "cameraOff";
    },
    participantCameraLabel(participant) {
      return this.$t(
        this.participantCameraOn(participant)
          ? "audio.video.cameraOn"
          : "audio.video.cameraOff",
      );
    },
    localCameraActionLabel(participant) {
      return this.$t(
        this.participantCameraOn(participant)
          ? "audio.video.turnCameraOff"
          : "audio.video.turnCameraOn",
      );
    },
    isSpeaking(participant) {
      return this.participantStateKey(participant) === "speaking";
    },
    initials(nickname) {
      const words = String(nickname || this.$t("audio.voice.you"))
        .trim()
        .split(/\s+/u)
        .filter(Boolean);
      return words
        .slice(0, 2)
        .map((word) => word.slice(0, 1).toUpperCase())
        .join("");
    },
    meterPercentage(participant) {
      return Math.round(
        Math.max(0, Math.min(1, Number(participant.level) || 0)) * 100,
      );
    },
    meterSegmentActive(participant, index) {
      if (participant.meterAvailable === false) return false;
      const percentage = this.meterPercentage(participant);
      return (
        percentage > 0 && percentage > ((index - 1) / this.meterSegments) * 100
      );
    },
    meterLabel(participant) {
      return this.$t("audio.voice.levelFor", {
        name: participant.nickname,
      });
    },
    meterTitle(participant) {
      return participant.meterAvailable === false
        ? this.$t("audio.voice.levelUnavailable")
        : `${this.meterLabel(participant)}: ${this.meterPercentage(participant)}%`;
    },
    percent(value) {
      return `${Math.round(Number(value) * 100)}%`;
    },
  },
};
</script>
