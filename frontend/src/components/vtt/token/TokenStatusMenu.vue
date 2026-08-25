<template>
  <div class="token-status-menu" role="menu">
    <button
      v-for="status in statuses"
      :key="status.code"
      type="button"
      role="menuitemcheckbox"
      :aria-checked="isActive(status.code)"
      :class="{ active: isActive(status.code) }"
      :title="$t(`vtt.token.quickStatuses.${status.code}`)"
      @click="$emit('toggle', status.code)"
    >
      {{ status.symbol }}
    </button>
  </div>
</template>

<script>
import { QUICK_TOKEN_STATUSES, tokenStatusCode } from "@/lib/vtt/tokenStatuses";

export default {
  name: "TokenStatusMenu",
  props: {
    activeStatuses: { type: Array, default: () => [] },
  },
  emits: ["toggle"],
  computed: {
    statuses: () => QUICK_TOKEN_STATUSES,
    activeCodes() {
      return new Set(this.activeStatuses.map(tokenStatusCode));
    },
  },
  methods: {
    isActive(code) {
      return this.activeCodes.has(code);
    },
  },
};
</script>
