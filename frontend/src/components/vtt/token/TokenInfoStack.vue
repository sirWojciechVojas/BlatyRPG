<template>
  <span
    :class="['scene-token-info-stack', `scene-token-info-stack--${position}`]"
    :style="stackStyle"
    aria-hidden="true"
  >
    <TokenResourceBars :token="token" />
    <small class="scene-token-name">{{ token.name }}</small>
  </span>
</template>

<script>
import TokenResourceBars from "./TokenResourceBars.vue";
import { tokenDisplayResourceBars } from "@/lib/vtt/tokenResources";
import {
  normalizeTokenResourceBarPosition,
  tokenResourceStackHeight,
} from "@/lib/vtt/tokenResourcePosition";

export default {
  name: "TokenInfoStack",
  components: { TokenResourceBars },
  props: {
    token: { type: Object, required: true },
  },
  computed: {
    position() {
      return normalizeTokenResourceBarPosition(this.token.resourceBarPosition);
    },
    stackStyle() {
      const count = tokenDisplayResourceBars(this.token).length;
      const height = tokenResourceStackHeight(count);
      return {
        "--token-resource-stack-height": `${height}px`,
        "--token-resource-stack-half-height": `${height / 2}px`,
      };
    },
  },
};
</script>
