<template>
  <span class="scene-token-info-stack" aria-hidden="true">
    <TokenResourceBars :token="token" />
    <small class="scene-token-name">{{ token.name }}</small>
  </span>
</template>

<script>
import TokenResourceBars from "./TokenResourceBars.vue";

export default {
  name: "TokenInfoStack",
  components: { TokenResourceBars },
  props: {
    token: { type: Object, required: true },
  },
};
</script>
