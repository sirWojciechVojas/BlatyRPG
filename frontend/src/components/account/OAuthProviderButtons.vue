<template>
  <div class="oauth-provider-list" :aria-label="$t(labelKey)">
    <button
      v-for="provider in providers"
      :key="provider.provider"
      class="oauth-provider-button"
      :class="`oauth-provider-button--${provider.provider}`"
      type="button"
      :disabled="disabled || !provider.enabled"
      :aria-label="buttonLabel(provider)"
      @click="$emit('select', provider.provider)"
    >
      <span class="oauth-provider-mark" aria-hidden="true" />
      <span>{{ buttonLabel(provider) }}</span>
      <small v-if="provider.linked">{{ $t("auth.oauth.linkedStatus") }}</small>
      <small v-else-if="!provider.enabled">{{
        $t("auth.oauth.unavailable")
      }}</small>
    </button>
  </div>
</template>

<script>
export default {
  name: "OAuthProviderButtons",
  props: {
    providers: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    mode: { type: String, default: "login" },
    labelKey: { type: String, default: "auth.oauth.providersLabel" },
  },
  emits: ["select"],
  methods: {
    buttonLabel(provider) {
      if (provider.linked) {
        return this.$t("auth.oauth.connectedAs", {
          provider: provider.baseLabel || provider.label,
          email: provider.email || "",
        });
      }
      return this.$t(
        this.mode === "link"
          ? "auth.oauth.linkWith"
          : "auth.oauth.continueWith",
        { provider: provider.label },
      );
    },
  },
};
</script>
