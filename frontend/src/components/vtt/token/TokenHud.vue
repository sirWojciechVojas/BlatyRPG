<template>
  <div
    class="token-hud"
    role="toolbar"
    :aria-label="token.name"
    :style="hudStyle"
  >
    <div class="token-hud__rail token-hud__rail--left">
      <button
        type="button"
        class="token-hud__move"
        :disabled="controlDisabled"
        :title="$t('vtt.token.hud.move')"
        @pointerdown.stop="$emit('move-start', $event)"
      >
        ✥
      </button>
      <button
        v-if="token.capabilities.canManage"
        type="button"
        :class="{ active: token.locked }"
        :disabled="busy"
        :title="$t('vtt.token.hud.lock')"
        :aria-pressed="token.locked"
        @click="$emit('lock')"
      >
        {{ token.locked ? "▣" : "▢" }}
      </button>
    </div>

    <section class="token-hud__card">
      <header class="token-hud__header">
        <i :class="`token-hud__dot token-hud__dot--${token.disposition}`" />
        <strong>{{ token.name }}</strong>
        <button
          type="button"
          class="token-hud__close"
          :title="$t('vtt.token.hud.close')"
          @click="$emit('close')"
        >
          ×
        </button>
      </header>

      <div v-if="hasResources" class="token-hud__resources">
        <div v-if="activeResources.bubbles.length" class="token-hud__values">
          <span
            v-for="bubble in activeResources.bubbles"
            :key="`bubble-${bubble.index}`"
            :title="bubble.item.label"
          >
            <small>{{ bubble.item.label || `#${bubble.index + 1}` }}</small>
            <b>{{ bubble.item.value }}</b>
          </span>
        </div>
        <span
          v-for="bar in activeResources.bars"
          :key="`bar-${bar.index}`"
          class="token-hud__bar"
          :title="`${bar.item.label}: ${bar.item.value}/${bar.item.max}`"
        >
          <i><b :style="barStyle(bar.item)" /></i>
          <small>{{ bar.item.label || `#${bar.index + 1}` }}</small>
          <em>{{ bar.item.value }}/{{ bar.item.max }}</em>
        </span>
      </div>

      <TokenStatusMenu
        v-if="statusOpen"
        :active-statuses="token.statuses"
        @toggle="$emit('status', $event)"
      />
      <TokenResourceQuickPanel
        v-if="resourceOpen"
        :resources="token.resources"
        @save="saveResources"
        @close="resourceOpen = false"
      />

      <footer v-if="token.capabilities.canManage">
        <button
          type="button"
          :class="{ active: !token.hidden }"
          :disabled="busy"
          :title="$t('vtt.token.hud.visibility')"
          @click="$emit('visibility')"
        >
          {{ token.hidden ? "◌" : "◉" }}
        </button>
        <button
          type="button"
          class="token-hud__danger"
          :disabled="busy"
          :title="$t('vtt.token.delete')"
          @click="$emit('delete')"
        >
          ⌫
        </button>
      </footer>
    </section>

    <div class="token-hud__rail token-hud__rail--right">
      <button
        type="button"
        :class="{ active: targeted }"
        :title="$t('vtt.token.hud.target')"
        :aria-pressed="targeted"
        @click="$emit('target')"
      >
        ⌖
      </button>
      <button
        type="button"
        :class="{ active: statusOpen || (token.statuses || []).length }"
        :disabled="controlDisabled && !token.capabilities.canEdit"
        :title="$t('vtt.token.hud.statuses')"
        :aria-pressed="statusOpen"
        @click="toggleStatuses"
      >
        ✚
      </button>
      <button
        v-if="canUseResources"
        type="button"
        :class="{ active: resourceOpen || hasResources }"
        :disabled="controlDisabled && !token.capabilities.canEdit"
        :title="$t('vtt.token.hud.resources')"
        :aria-pressed="resourceOpen"
        @click="toggleResources"
      >
        ▰
      </button>
      <button
        v-if="token.capabilities.canEdit || token.capabilities.canManage"
        type="button"
        :disabled="busy"
        :title="$t('vtt.token.hud.settings')"
        @click="$emit('settings', $event)"
      >
        ⚙
      </button>
      <button
        v-if="
          token.characterId &&
          (token.capabilities.canObserve || token.capabilities.canManage)
        "
        type="button"
        :title="$t('vtt.token.openActor')"
        @click="$emit('open-actor', token.characterId)"
      >
        ♙
      </button>
    </div>
  </div>
</template>

<script>
import TokenStatusMenu from "./TokenStatusMenu.vue";
import TokenResourceQuickPanel from "./TokenResourceQuickPanel.vue";
import {
  activeTokenResources,
  tokenBarPercent,
} from "@/lib/vtt/tokenResources";

export default {
  name: "TokenHud",
  components: { TokenResourceQuickPanel, TokenStatusMenu },
  props: {
    token: { type: Object, required: true },
    busy: { type: Boolean, default: false },
    targeted: { type: Boolean, default: false },
    scale: { type: Number, default: 1 },
  },
  emits: [
    "move-start",
    "status",
    "resources",
    "target",
    "visibility",
    "lock",
    "settings",
    "open-actor",
    "delete",
    "close",
  ],
  data: () => ({ statusOpen: false, resourceOpen: false }),
  computed: {
    hudStyle() {
      const inverse = 1 / Math.max(0.1, Number(this.scale) || 1);
      return {
        "--token-hud-scale": inverse,
        "--token-hud-card-gap": `${11 * inverse}px`,
        "--token-hud-rail-gap": `${9 * inverse}px`,
      };
    },
    controlDisabled() {
      return (
        this.busy || this.token.locked || !this.token.capabilities.canControl
      );
    },
    canUseResources() {
      return (
        this.token.capabilities.canControl ||
        this.token.capabilities.canEdit ||
        this.token.capabilities.canManage
      );
    },
    activeResources() {
      const indexed = (items) => items.map((item, index) => ({ item, index }));
      const active = activeTokenResources(this.token.resources);
      return { bars: indexed(active.bars), bubbles: indexed(active.bubbles) };
    },
    hasResources() {
      return (
        this.activeResources.bars.length > 0 ||
        this.activeResources.bubbles.length > 0
      );
    },
  },
  methods: {
    barStyle(bar) {
      return {
        width: `${tokenBarPercent(bar)}%`,
        backgroundColor: bar.color,
      };
    },
    toggleStatuses() {
      this.resourceOpen = false;
      this.statusOpen = !this.statusOpen;
    },
    toggleResources() {
      this.statusOpen = false;
      this.resourceOpen = !this.resourceOpen;
    },
    saveResources(resources) {
      this.$emit("resources", resources);
      this.resourceOpen = false;
    },
  },
};
</script>
