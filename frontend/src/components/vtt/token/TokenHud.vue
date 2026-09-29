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
        type="button"
        :class="{ active: targeted }"
        :title="$t('vtt.token.hud.target')"
        :aria-pressed="targeted"
        @click="$emit('target')"
      >
        ⌖
      </button>
      <button
        v-if="token.capabilities.canManage"
        type="button"
        :class="{ active: !token.hidden }"
        :disabled="busy"
        :title="$t('vtt.token.hud.visibility')"
        @click="$emit('visibility')"
      >
        {{ token.hidden ? "◌" : "◉" }}
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
        <span
          v-if="synchronized"
          class="token-hud__header-action token-hud__sync-badge active"
          role="status"
          :aria-label="$t('vtt.tokenSync.listenerBadge')"
          :title="$t('vtt.tokenSync.listenerBadge')"
        >
          🔗
        </span>
        <button
          v-if="token.capabilities.canManage"
          type="button"
          class="token-hud__header-action token-hud__danger"
          :disabled="busy"
          :title="$t('vtt.token.delete')"
          @click="$emit('delete')"
        >
          ⌫
        </button>
        <button
          type="button"
          class="token-hud__close"
          :title="$t('vtt.token.hud.close')"
          @click="$emit('close')"
        >
          ×
        </button>
      </header>

      <div class="token-hud__movement" :class="{ depleted: movementLeft <= 0 }">
        <small>PR</small>
        <b>{{ movementLeft }} / {{ movementRange }}</b>
      </div>

      <TokenStatusMenu
        v-if="statusOpen"
        :active-statuses="token.statuses"
        @toggle="$emit('status', $event)"
      />
      <TokenResourceQuickPanel
        v-if="resourceOpen"
        :resources="token.resources"
        :can-manage-movement="token.capabilities.canManage"
        @save="saveResources"
        @close="resourceOpen = false"
      />
    </section>

    <div class="token-hud__rail token-hud__rail--right">
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
        v-if="token.capabilities.canManage"
        type="button"
        :disabled="busy"
        :title="$t('vtt.token.assignment.action')"
        @click="$emit('assign-character')"
      >
        ♟
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
import { tokenMovementState } from "@/lib/vtt/tokenMovement";
import { activeTokenResources } from "@/lib/vtt/tokenResources";
import {
  normalizeTokenResourceBarPosition,
  tokenResourceStackHeight,
} from "@/lib/vtt/tokenResourcePosition";

export default {
  name: "TokenHud",
  components: { TokenResourceQuickPanel, TokenStatusMenu },
  props: {
    token: { type: Object, required: true },
    busy: { type: Boolean, default: false },
    targeted: { type: Boolean, default: false },
    scale: { type: Number, default: 1 },
    synchronized: { type: Boolean, default: false },
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
    "assign-character",
    "delete",
    "close",
  ],
  data: () => ({ statusOpen: false, resourceOpen: false }),
  computed: {
    hudStyle() {
      const inverse = 1 / Math.max(0.1, Number(this.scale) || 1);
      const count = this.activeResources.bars.length;
      const stackHeight = tokenResourceStackHeight(count);
      const position = normalizeTokenResourceBarPosition(
        this.token.resourceBarPosition,
      );
      const barGap =
        position === "below"
          ? stackHeight
          : position === "bottom-overlap"
            ? stackHeight / 2
            : 0;
      const informationGap = 38 + barGap;
      return {
        "--token-hud-scale": inverse,
        "--token-hud-card-gap": `${informationGap * inverse}px`,
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
      return activeTokenResources(this.token.resources);
    },
    hasResources() {
      return (
        this.activeResources.bars.length > 0 ||
        this.activeResources.bubbles.length > 0
      );
    },
    movementRange() {
      return tokenMovementState(this.token).range;
    },
    movementLeft() {
      return tokenMovementState(this.token).remaining;
    },
  },
  methods: {
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
