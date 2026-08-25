<template>
  <div class="token-hud" role="toolbar" :aria-label="token.name">
    <strong>{{ token.name }}</strong>
    <button
      type="button"
      :disabled="busy || token.locked || !token.capabilities.canControl"
      :title="$t('vtt.token.rotateLeft')"
      @click="$emit('rotate', -15)"
    >
      ↶
    </button>
    <button
      type="button"
      :disabled="busy || token.locked || !token.capabilities.canControl"
      :title="$t('vtt.token.rotateRight')"
      @click="$emit('rotate', 15)"
    >
      ↷
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
      ◉
    </button>
    <button
      v-if="token.capabilities.canManage"
      type="button"
      class="token-hud__danger"
      :disabled="busy"
      :title="$t('vtt.token.delete')"
      @click="$emit('delete')"
    >
      ×
    </button>
  </div>
</template>

<script>
export default {
  name: "TokenHud",
  props: {
    token: { type: Object, required: true },
    busy: { type: Boolean, default: false },
  },
  emits: ["rotate", "open-actor", "delete"],
};
</script>
