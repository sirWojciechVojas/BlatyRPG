<template>
  <img
    v-if="resolvedSrc"
    v-bind="$attrs"
    :src="resolvedSrc"
    :alt="alt"
    :draggable="draggable"
    @load="$emit('load', $event)"
    @error="$emit('error', $event)"
  />
</template>

<script>
import { resolveAccessToken } from "@/lib/api/jsonApiClient";

const protectedAsset = (src) =>
  /\/(?:api\/)?(?:admin\/)?token-template-assets\/\d+\/file(?:[?#]|$)/u.test(
    src,
  ) ||
  /\/(?:api\/)?campaigns\/\d+\/token-template-assets\/\d+\/file(?:[?#]|$)/u.test(
    src,
  ) ||
  /\/(?:api\/)?campaigns\/\d+\/maps\/assets\/\d+\/file(?:[?#]|$)/u.test(src) ||
  /\/(?:api\/)?profession-assets\/\d+\/file(?:[?#]|$)/u.test(src) ||
  /\/(?:api\/)?campaigns\/\d+\/scene-assets\/[A-Za-z0-9._-]+\/file(?:[?#]|$)/u.test(
    src,
  );

const requestUrl = (src) => {
  if (/^https?:\/\//iu.test(src) || src.startsWith("/api/")) return src;
  const base = String(process.env.VUE_APP_API_BASE || "/api").replace(
    /\/+$/u,
    "",
  );
  return `${base}/${src.replace(/^\/+/, "")}`;
};

export default {
  name: "AuthenticatedImage",
  inheritAttrs: false,
  props: {
    src: { type: String, default: "" },
    alt: { type: String, default: "" },
    draggable: { type: Boolean, default: false },
  },
  emits: ["load", "error"],
  data: () => ({ resolvedSrc: "", objectUrl: "", generation: 0 }),
  watch: {
    src: { immediate: true, handler: "resolveSource" },
  },
  beforeUnmount() {
    this.generation += 1;
    this.release();
  },
  methods: {
    release() {
      if (this.objectUrl) URL.revokeObjectURL(this.objectUrl);
      this.objectUrl = "";
    },
    async resolveSource(value) {
      const generation = ++this.generation;
      this.release();
      this.resolvedSrc = "";
      const src = String(value || "").trim();
      if (!src) return;
      if (!protectedAsset(src)) {
        this.resolvedSrc = src;
        return;
      }
      const token = resolveAccessToken();
      try {
        const response = await window.fetch(requestUrl(src), {
          headers: {
            Accept: "image/*",
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
          },
          credentials: "same-origin",
        });
        if (!response.ok) throw new Error(`http_${response.status}`);
        const blob = await response.blob();
        if (generation !== this.generation) return;
        this.objectUrl = URL.createObjectURL(blob);
        this.resolvedSrc = this.objectUrl;
      } catch (error) {
        if (generation === this.generation) this.$emit("error", error);
      }
    },
  },
};
</script>
