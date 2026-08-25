<template>
  <span class="token-state-overlay" aria-hidden="true">
    <i v-if="flags.multiSelected" class="token-state-overlay__multi" />
    <i v-if="flags.activeTurn" class="token-state-overlay__active" />
    <i v-else-if="flags.waitingTurn" class="token-state-overlay__waiting" />
    <i v-if="flags.targeted" class="token-state-overlay__target" />
    <i v-if="flags.disabled" class="token-state-overlay__disabled" />
    <i
      v-if="flags.defeated"
      class="token-state-overlay__defeated"
      :class="{ 'token-state-overlay__defeated--dead': flags.dead }"
    />
    <span class="token-state-overlay__badges">
      <i
        class="token-state-overlay__control"
        :class="{
          'token-state-overlay__control--owned': flags.controlled,
        }"
      />
      <i v-if="flags.locked" class="token-state-overlay__badge">▣</i>
      <i v-if="flags.hidden" class="token-state-overlay__badge">◌</i>
      <i v-if="flags.GMOnly" class="token-state-overlay__badge">GM</i>
    </span>
  </span>
</template>

<script>
export default {
  name: "TokenStateOverlay",
  props: {
    flags: { type: Object, required: true },
  },
};
</script>
