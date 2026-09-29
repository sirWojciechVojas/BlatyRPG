<template>
  <span class="combat-token-identity" :title="token.name">
    <span class="combat-token-identity__avatar" aria-hidden="true">
      <AuthenticatedImage
        v-if="token.imageUrl && !imageFailed"
        :src="token.imageUrl"
        alt=""
        draggable="false"
        @error="imageFailed = true"
      />
      <b v-else>{{ initials }}</b>
    </span>
    <strong>{{ token.name }}</strong>
  </span>
</template>

<script>
import { tokenInitials } from "@/lib/vtt/combatPresentation";
import AuthenticatedImage from "@/components/ui/AuthenticatedImage.vue";

export default {
  name: "CombatTokenIdentity",
  components: { AuthenticatedImage },
  props: {
    token: { type: Object, required: true },
  },
  data: () => ({ imageFailed: false }),
  computed: {
    initials() {
      return tokenInitials(this.token.name);
    },
  },
  watch: {
    "token.imageUrl"() {
      this.imageFailed = false;
    },
  },
};
</script>
