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
      <nav class="token-settings-panel__tabs" role="tablist">
        <button
          v-for="tab in tabs"
          :key="tab"
          type="button"
          role="tab"
          :aria-selected="activeTab === tab"
          :class="{ active: activeTab === tab }"
          @click="activeTab = tab"
        >
          {{ $t(`vtt.token.settings.tabs.${tab}`) }}
        </button>
        <TokenSettingsTransfer
          v-if="canManage"
          :draft="draft"
          @apply="applyTransfer"
        />
      </nav>

      <div class="token-settings-panel__workspace">
        <TokenSettingsPreview
          :draft="draft"
          :token="token"
          :grid-type="gridType"
        />

        <div class="token-settings-panel__body">
          <TokenAppearanceSettings
            v-show="activeTab === 'general'"
            v-model="draft"
            :can-manage="canManage"
          />

          <TokenResourceSettings
            v-show="activeTab === 'resources'"
            v-model="draft.resources"
            :actor="actor"
            :bar-position="draft.resourceBarPosition"
            :can-manage="canManage"
            @update:bar-position="draft.resourceBarPosition = $event"
          />

          <TokenMovementSettings
            v-show="activeTab === 'movement'"
            v-model="draft"
            :can-manage="canManage"
          />

          <TokenVisionSettings
            v-if="canManage"
            v-show="activeTab === 'vision'"
            v-model="draft.vision"
          />

          <div v-if="canManage" v-show="activeTab === 'permissions'">
            <section class="token-settings-panel__toggles">
              <label>
                <input v-model="draft.hidden" type="checkbox" />
                {{ $t("vtt.token.settings.hidden") }}
              </label>
              <label>
                <input v-model="draft.locked" type="checkbox" />
                {{ $t("vtt.token.settings.locked") }}
              </label>
            </section>

            <section class="token-settings-panel__permissions">
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
          </div>
        </div>
      </div>

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
import TokenAppearanceSettings from "./TokenAppearanceSettings.vue";
import TokenMovementSettings from "./TokenMovementSettings.vue";
import TokenPermissionField from "./TokenPermissionField.vue";
import TokenResourceSettings from "./TokenResourceSettings.vue";
import TokenSettingsPreview from "./TokenSettingsPreview.vue";
import TokenSettingsTransfer from "./TokenSettingsTransfer.vue";
import TokenVisionSettings from "./TokenVisionSettings.vue";
import {
  createTokenSettingsDraft,
  tokenSettingsPayload,
  tokenSettingsPosition,
} from "@/lib/vtt/tokenSettingsDraft";

export default {
  name: "TokenSettingsPanel",
  components: {
    TokenAppearanceSettings,
    TokenMovementSettings,
    TokenPermissionField,
    TokenResourceSettings,
    TokenSettingsPreview,
    TokenSettingsTransfer,
    TokenVisionSettings,
  },
  props: {
    token: { type: Object, required: true },
    gridSize: { type: Number, default: 100 },
    gridType: { type: String, default: "square" },
    members: { type: Array, default: () => [] },
    actor: { type: Object, default: null },
    anchor: { type: Object, default: () => ({}) },
    busy: { type: Boolean, default: false },
  },
  emits: ["save", "close"],
  data() {
    return {
      draft: createTokenSettingsDraft(this.token, this.gridSize),
      activeTab: "general",
      imageFailed: false,
      viewport: { width: window.innerWidth, height: window.innerHeight },
    };
  },
  computed: {
    tabs() {
      return this.canManage
        ? ["general", "movement", "vision", "resources", "permissions"]
        : ["general", "movement", "resources"];
    },
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
    applyTransfer(draft) {
      this.draft = draft;
      this.activeTab = "general";
    },
  },
};
</script>
