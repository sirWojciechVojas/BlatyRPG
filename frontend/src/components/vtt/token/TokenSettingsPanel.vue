<template>
  <aside
    class="token-settings-panel"
    :style="panelStyle"
    role="dialog"
    :aria-label="$t('vtt.token.settings.title', { name: token.name })"
    @pointerdown.stop
  >
    <header>
      <div class="token-settings-panel__identity">
        <span class="token-settings-panel__preview">
          <img
            v-if="draft.imageUrl && !imageFailed"
            :src="draft.imageUrl"
            alt=""
            @error="imageFailed = true"
          />
          <b v-else>{{ initials }}</b>
        </span>
        <div>
          <small>{{ $t("vtt.token.settings.kicker") }}</small>
          <strong>{{ token.name }}</strong>
        </div>
      </div>
      <button
        type="button"
        :title="$t('vtt.token.settings.close')"
        @click="$emit('close')"
      >
        ×
      </button>
    </header>

    <form @submit.prevent="save">
      <section class="token-settings-panel__presets">
        <h3>{{ $t("vtt.token.settings.sizePresets") }}</h3>
        <div>
          <button
            v-for="preset in sizePresets"
            :key="preset.key"
            type="button"
            :class="{ active: hasSize(preset.cells) }"
            @click="applySize(preset.cells)"
          >
            <b>{{ preset.cells }}×</b>
            <small>{{ $t(`vtt.token.sizes.${preset.key}`) }}</small>
          </button>
        </div>
      </section>
      <section class="token-settings-panel__grid">
        <label class="token-settings-panel__wide">
          <span>{{ $t("vtt.token.settings.name") }}</span>
          <input v-model.trim="draft.name" maxlength="150" required />
        </label>
        <label class="token-settings-panel__wide">
          <span>{{ $t("vtt.token.settings.imageUrl") }}</span>
          <input v-model.trim="draft.imageUrl" maxlength="2048" />
        </label>
        <label>
          <span>{{ $t("vtt.token.settings.widthCells") }}</span>
          <input
            v-model.number="draft.widthCells"
            type="number"
            min="0.25"
            max="100"
            step="0.25"
          />
        </label>
        <label>
          <span>{{ $t("vtt.token.settings.heightCells") }}</span>
          <input
            v-model.number="draft.heightCells"
            type="number"
            min="0.25"
            max="100"
            step="0.25"
          />
        </label>
        <label>
          <span>{{ $t("vtt.token.settings.rotation") }}</span>
          <input v-model.number="draft.rotation" type="number" step="15" />
        </label>
        <label>
          <span>{{ $t("vtt.token.settings.facing") }}</span>
          <input v-model.number="draft.facing" type="number" step="15" />
        </label>
        <label>
          <span>{{ $t("vtt.token.settings.elevation") }}</span>
          <input v-model.number="draft.elevation" type="number" step="1" />
        </label>
        <label>
          <span>{{ $t("vtt.token.settings.disposition") }}</span>
          <select v-model="draft.disposition">
            <option v-for="value in dispositions" :key="value" :value="value">
              {{ $t(`vtt.token.dispositions.${value}`) }}
            </option>
          </select>
        </label>
      </section>

      <section v-if="canManage" class="token-settings-panel__toggles">
        <label>
          <input v-model="draft.hidden" type="checkbox" />
          {{ $t("vtt.token.settings.hidden") }}
        </label>
        <label>
          <input v-model="draft.locked" type="checkbox" />
          {{ $t("vtt.token.settings.locked") }}
        </label>
      </section>

      <section v-if="canManage" class="token-settings-panel__permissions">
        <h3>{{ $t("vtt.token.permissions.title") }}</h3>
        <TokenPermissionField
          v-model="draft.visibleTo"
          :label="$t('vtt.token.permissions.visibleTo')"
          :members="members"
        />
        <TokenPermissionField
          v-model="draft.controlledBy"
          :label="$t('vtt.token.permissions.controlledBy')"
          :members="members"
          allow-inherit
        />
        <TokenPermissionField
          v-model="draft.editableBy"
          :label="$t('vtt.token.permissions.editableBy')"
          :members="members"
          allow-inherit
        />
        <TokenPermissionField
          v-model="draft.observerBy"
          :label="$t('vtt.token.permissions.observerBy')"
          :members="members"
          allow-inherit
        />
      </section>

      <footer>
        <button type="button" @click="$emit('close')">
          {{ $t("vtt.token.settings.cancel") }}
        </button>
        <button type="submit" class="primary" :disabled="busy || !valid">
          {{ $t("vtt.token.settings.save") }}
        </button>
      </footer>
    </form>
  </aside>
</template>

<script>
import TokenPermissionField from "./TokenPermissionField.vue";
import {
  TOKEN_SIZE_PRESETS,
  createTokenSettingsDraft,
  tokenSettingsPayload,
  tokenSettingsPosition,
} from "@/lib/vtt/tokenSettingsDraft";

export default {
  name: "TokenSettingsPanel",
  components: { TokenPermissionField },
  props: {
    token: { type: Object, required: true },
    gridSize: { type: Number, default: 100 },
    members: { type: Array, default: () => [] },
    anchor: { type: Object, default: () => ({}) },
    busy: { type: Boolean, default: false },
  },
  emits: ["save", "close"],
  data() {
    return {
      draft: createTokenSettingsDraft(this.token, this.gridSize),
      sizePresets: TOKEN_SIZE_PRESETS,
      dispositions: ["friendly", "neutral", "hostile", "secret"],
      imageFailed: false,
      viewport: { width: window.innerWidth, height: window.innerHeight },
    };
  },
  computed: {
    canManage() {
      return this.token.capabilities.canManage === true;
    },
    initials() {
      return String(this.draft.name || "?")
        .split(/\s+/u)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toLocaleUpperCase();
    },
    panelStyle() {
      const position = tokenSettingsPosition(this.anchor, this.viewport);
      return { left: `${position.left}px`, top: `${position.top}px` };
    },
    permissionsValid() {
      if (!this.canManage) return true;
      return [
        this.draft.visibleTo,
        this.draft.controlledBy,
        this.draft.editableBy,
        this.draft.observerBy,
      ].every((scope) => scope.mode !== "users" || scope.userIds.length > 0);
    },
    valid() {
      return (
        Boolean(this.draft.name.trim()) &&
        this.draft.widthCells >= 0.25 &&
        this.draft.heightCells >= 0.25 &&
        this.permissionsValid
      );
    },
  },
  watch: {
    "draft.imageUrl"() {
      this.imageFailed = false;
    },
  },
  mounted() {
    window.addEventListener("resize", this.resize);
    window.addEventListener("keydown", this.onKeydown);
  },
  beforeUnmount() {
    window.removeEventListener("resize", this.resize);
    window.removeEventListener("keydown", this.onKeydown);
  },
  methods: {
    applySize(cells) {
      this.draft.widthCells = cells;
      this.draft.heightCells = cells;
    },
    hasSize(cells) {
      return (
        Number(this.draft.widthCells) === cells &&
        Number(this.draft.heightCells) === cells
      );
    },
    resize() {
      this.viewport = { width: window.innerWidth, height: window.innerHeight };
    },
    onKeydown(event) {
      if (event.key === "Escape") this.$emit("close");
    },
    save() {
      if (!this.valid) return;
      this.$emit(
        "save",
        tokenSettingsPayload(this.draft, this.gridSize, this.canManage),
      );
    },
  },
};
</script>
