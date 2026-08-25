<template>
  <span v-if="badges.length" class="token-status-badges" aria-hidden="true">
    <i v-for="status in badges" :key="status.code">{{ status.symbol }}</i>
  </span>
</template>

<script>
import { quickTokenStatus } from "@/lib/vtt/tokenStatuses";

export default {
  name: "TokenStatusBadges",
  props: {
    statuses: { type: Array, default: () => [] },
  },
  computed: {
    badges() {
      return this.statuses.map(quickTokenStatus).filter(Boolean).slice(0, 4);
    },
  },
};
</script>
