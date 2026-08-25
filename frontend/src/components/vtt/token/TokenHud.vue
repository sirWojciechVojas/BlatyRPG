<template>
  <div class="token-hud" role="toolbar" :aria-label="token.name">
    <header class="token-hud__header">
      <i :class="`token-hud__dot token-hud__dot--${token.disposition}`" />
      <span>
        <small>{{ $t("vtt.token.hud.kicker") }}</small>
        <strong>{{ token.name }}</strong>
      </span>
      <button
        type="button"
        class="token-hud__close"
        :title="$t('vtt.token.hud.close')"
        @click="$emit('close')"
      >
        ×
      </button>
    </header>
    <div class="token-hud__actions">
      <button
        type="button"
        class="token-hud__move"
        :disabled="controlDisabled"
        :title="$t('vtt.token.hud.move')"
        @pointerdown.stop="$emit('move-start', $event)"
      >
        <b>✥</b><small>{{ $t("vtt.token.hud.short.move") }}</small>
      </button>
      <button
        type="button"
        :class="{ active: statusOpen || (token.statuses || []).length }"
        :disabled="controlDisabled && !token.capabilities.canEdit"
        :title="$t('vtt.token.hud.statuses')"
        :aria-pressed="statusOpen"
        @click="statusOpen = !statusOpen"
      >
        <b>✚</b><small>{{ $t("vtt.token.hud.short.status") }}</small>
      </button>
      <button
        type="button"
        :class="{ active: targeted }"
        :title="$t('vtt.token.hud.target')"
        :aria-pressed="targeted"
        @click="$emit('target')"
      >
        <b>⌖</b><small>{{ $t("vtt.token.hud.short.target") }}</small>
      </button>
      <button
        type="button"
        :disabled="controlDisabled"
        :title="$t('vtt.token.rotateLeft')"
        @click="$emit('rotate', -15)"
      >
        <b>↶</b><small>{{ $t("vtt.token.hud.short.left") }}</small>
      </button>
      <button
        type="button"
        :disabled="controlDisabled"
        :title="$t('vtt.token.rotateRight')"
        @click="$emit('rotate', 15)"
      >
        <b>↷</b><small>{{ $t("vtt.token.hud.short.right") }}</small>
      </button>
      <button
        v-if="token.capabilities.canManage"
        type="button"
        :class="{ active: !token.hidden }"
        :disabled="busy"
        :title="$t('vtt.token.hud.visibility')"
        :aria-pressed="!token.hidden"
        @click="$emit('visibility')"
      >
        <b>{{ token.hidden ? "◌" : "◉" }}</b>
        <small>{{ $t("vtt.token.hud.short.visibility") }}</small>
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
        <b>{{ token.locked ? "▣" : "▢" }}</b>
        <small>{{ $t("vtt.token.hud.short.lock") }}</small>
      </button>
      <button
        v-if="token.capabilities.canEdit || token.capabilities.canManage"
        type="button"
        :disabled="busy"
        :title="$t('vtt.token.hud.settings')"
        @click="$emit('settings', $event)"
      >
        <b>⚙</b><small>{{ $t("vtt.token.hud.short.settings") }}</small>
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
        <b>♙</b><small>{{ $t("vtt.token.hud.short.actor") }}</small>
      </button>
      <button
        v-if="token.capabilities.canManage"
        type="button"
        class="token-hud__danger"
        :disabled="busy"
        :title="$t('vtt.token.delete')"
        @click="$emit('delete')"
      >
        <b>⌫</b><small>{{ $t("vtt.token.hud.short.delete") }}</small>
      </button>
    </div>
    <TokenStatusMenu
      v-if="statusOpen"
      :active-statuses="token.statuses"
      @toggle="$emit('status', $event)"
    />
  </div>
</template>

<script>
import TokenStatusMenu from "./TokenStatusMenu.vue";

export default {
  name: "TokenHud",
  components: { TokenStatusMenu },
  props: {
    token: { type: Object, required: true },
    busy: { type: Boolean, default: false },
    targeted: { type: Boolean, default: false },
  },
  emits: [
    "move-start",
    "status",
    "target",
    "rotate",
    "visibility",
    "lock",
    "settings",
    "open-actor",
    "delete",
    "close",
  ],
  data: () => ({ statusOpen: false }),
  computed: {
    controlDisabled() {
      return (
        this.busy || this.token.locked || !this.token.capabilities.canControl
      );
    },
  },
};
</script>
