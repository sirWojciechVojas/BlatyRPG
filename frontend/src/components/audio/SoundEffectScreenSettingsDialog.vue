<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="sound-effect-screen-settings__backdrop"
      @mousedown.self="cancel"
    >
      <form
        ref="dialog"
        class="sound-effect-screen-settings"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        :aria-busy="busy || undefined"
        @submit.prevent="save"
        @keydown="handleKeydown"
      >
        <header>
          <h2 :id="titleId">
            {{ $t("audio.soundEffects.screenSettings.title") }}
            <small>{{ screen?.label }}</small>
          </h2>
          <button
            type="button"
            class="sound-effect-screen-settings__close"
            :disabled="busy"
            :aria-label="$t('audio.soundEffects.screenSettings.cancel')"
            @click="cancel"
          >
            ×
          </button>
        </header>

        <div class="sound-effect-screen-settings__fields">
          <label>
            <span>{{ $t("audio.soundEffects.screenSettings.columns") }}:</span>
            <input
              ref="initialControl"
              v-model.number="draft.columns"
              type="number"
              :min="limits.columns.min"
              :max="limits.columns.max"
              step="1"
              required
            />
          </label>
          <label>
            <span>{{ $t("audio.soundEffects.screenSettings.rows") }}:</span>
            <input
              v-model.number="draft.rows"
              type="number"
              :min="limits.rows.min"
              :max="limits.rows.max"
              step="1"
              required
            />
          </label>
          <label>
            <span
              >{{ $t("audio.soundEffects.screenSettings.textLines") }}:</span
            >
            <input
              v-model.number="draft.textLines"
              type="number"
              :min="limits.textLines.min"
              :max="limits.textLines.max"
              step="1"
              required
            />
          </label>
          <label>
            <span>{{ $t("audio.soundEffects.screenSettings.style") }}:</span>
            <select v-model="draft.padStyle" required>
              <option value="square">
                {{ $t("audio.soundEffects.screenSettings.styles.square") }}
              </option>
              <option value="wide">
                {{ $t("audio.soundEffects.screenSettings.styles.wide") }}
              </option>
              <option value="compact">
                {{ $t("audio.soundEffects.screenSettings.styles.compact") }}
              </option>
            </select>
          </label>
        </div>

        <p v-if="hiddenAssignments" class="sound-effect-screen-settings__hint">
          {{
            $t("audio.soundEffects.screenSettings.hiddenAssignments", {
              count: hiddenAssignments,
            })
          }}
        </p>

        <footer>
          <button
            type="submit"
            class="sound-effect-screen-settings__ok"
            :disabled="busy"
          >
            {{ $t("audio.soundEffects.screenSettings.ok") }}
          </button>
          <button type="button" :disabled="busy" @click="cancel">
            {{ $t("audio.soundEffects.screenSettings.cancel") }}
          </button>
        </footer>
      </form>
    </div>
  </Teleport>
</template>

<script>
import {
  SOUND_EFFECT_SCREEN_LIMITS,
  normalizeSoundEffectScreenLayout,
} from "@/lib/audio/soundEffectScreenLayout";

const FOCUSABLE =
  'button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';
let dialogSequence = 0;

export default {
  name: "SoundEffectScreenSettingsDialog",
  props: {
    open: Boolean,
    screen: { type: Object, default: null },
    busy: Boolean,
  },
  emits: ["save", "cancel"],
  data() {
    dialogSequence += 1;
    return {
      draft: normalizeSoundEffectScreenLayout(this.screen || {}),
      limits: SOUND_EFFECT_SCREEN_LIMITS,
      titleId: `sound-effect-screen-settings-title-${dialogSequence}`,
      previouslyFocused: null,
    };
  },
  computed: {
    capacity() {
      return Number(this.draft.columns) * Number(this.draft.rows);
    },
    hiddenAssignments() {
      return (this.screen?.slots || []).filter(
        (slot) => Number(slot.position) >= this.capacity,
      ).length;
    },
  },
  watch: {
    open: {
      immediate: true,
      handler(isOpen, wasOpen) {
        if (isOpen) {
          this.resetDraft();
          if (!wasOpen) {
            this.previouslyFocused = document.activeElement;
            this.$nextTick(() => this.$refs.initialControl?.focus());
          }
        } else if (wasOpen) {
          this.restoreFocus();
        }
      },
    },
    screen() {
      if (this.open) this.resetDraft();
    },
  },
  beforeUnmount() {
    this.restoreFocus(true);
  },
  methods: {
    resetDraft() {
      this.draft = normalizeSoundEffectScreenLayout(this.screen || {});
    },
    save(event) {
      if (this.busy || !event.currentTarget.checkValidity()) return;
      const layout = normalizeSoundEffectScreenLayout(this.draft);
      this.$emit("save", layout);
    },
    cancel() {
      if (!this.busy) this.$emit("cancel");
    },
    handleKeydown(event) {
      if (event.key === "Escape") {
        event.preventDefault();
        event.stopPropagation();
        this.cancel();
        return;
      }
      if (event.key !== "Tab") return;
      const controls = Array.from(
        this.$refs.dialog?.querySelectorAll(FOCUSABLE) || [],
      );
      if (!controls.length) return;
      const first = controls[0];
      const last = controls.at(-1);
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    },
    restoreFocus(immediately = false) {
      const target = this.previouslyFocused;
      this.previouslyFocused = null;
      const focus = () => target?.isConnected && target.focus();
      if (immediately) focus();
      else this.$nextTick(focus);
    },
  },
};
</script>

<style scoped>
.sound-effect-screen-settings__backdrop {
  position: fixed;
  inset: 0;
  z-index: 10050;
  display: grid;
  padding: 1rem;
  place-items: center;
  background: rgb(5 4 3 / 68%);
}

.sound-effect-screen-settings {
  width: min(24rem, 100%);
  overflow: hidden;
  color: #eee3d1;
  border: 1px solid #6a5136;
  border-radius: 3px;
  background: #211914;
  box-shadow: 0 1rem 3rem rgb(0 0 0 / 58%);
}

.sound-effect-screen-settings header {
  display: flex;
  min-height: 2.7rem;
  align-items: center;
  justify-content: space-between;
  padding: 0.6rem 0.75rem;
  border-bottom: 1px solid #3b2e23;
  background: #17120f;
}

.sound-effect-screen-settings h2 {
  margin: 0;
  color: #d2a85c;
  font-size: 0.82rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.sound-effect-screen-settings h2 small {
  margin-left: 0.35rem;
  color: #aa9a85;
  font-size: 0.72rem;
}

.sound-effect-screen-settings button,
.sound-effect-screen-settings input,
.sound-effect-screen-settings select {
  min-height: 1.85rem;
  color: #eee3d1;
  border: 1px solid #57432f;
  border-radius: 2px;
  background: #130f0c;
  font: inherit;
}

.sound-effect-screen-settings button:focus-visible,
.sound-effect-screen-settings input:focus-visible,
.sound-effect-screen-settings select:focus-visible {
  outline: 2px solid #d2a85c;
  outline-offset: 1px;
}

.sound-effect-screen-settings__close {
  width: 1.8rem;
  padding: 0;
  border: 0 !important;
  background: transparent !important;
  color: #aa9a85 !important;
  font-size: 1rem !important;
}

.sound-effect-screen-settings__fields {
  display: grid;
  gap: 0.5rem;
  padding: 0.9rem 0.75rem;
}

.sound-effect-screen-settings__fields label {
  display: grid;
  grid-template-columns: minmax(7.5rem, 0.9fr) minmax(8rem, 1.35fr);
  align-items: center;
  gap: 0.75rem;
  font-size: 0.74rem;
}

.sound-effect-screen-settings__fields input,
.sound-effect-screen-settings__fields select {
  width: 100%;
  padding: 0.25rem 0.4rem;
}

.sound-effect-screen-settings__hint {
  margin: 0 0.75rem 0.75rem;
  color: #d8b373;
  font-size: 0.66rem;
  line-height: 1.35;
}

.sound-effect-screen-settings footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.45rem;
  padding: 0.65rem 0.75rem;
  border-top: 1px solid #3b2e23;
  background: #17120f;
}

.sound-effect-screen-settings footer button {
  min-width: 5.25rem;
  padding: 0.25rem 0.65rem;
  font-size: 0.72rem;
}

.sound-effect-screen-settings__ok {
  color: #21180e !important;
  border-color: #d2a85c !important;
  background: #d2a85c !important;
  font-weight: 700 !important;
}
</style>
