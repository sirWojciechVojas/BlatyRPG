<template>
  <details class="token-settings-transfer">
    <summary>{{ $t("vtt.token.settings.transfer.title") }}</summary>
    <div class="token-settings-transfer__actions">
      <button type="button" @click="copy">
        {{ $t("vtt.token.settings.transfer.copy") }}
      </button>
      <button type="button" @click="paste">
        {{ $t("vtt.token.settings.transfer.paste") }}
      </button>
      <button type="button" :disabled="!source.trim()" @click="apply">
        {{ $t("vtt.token.settings.transfer.apply") }}
      </button>
    </div>
    <textarea
      ref="source"
      v-model="source"
      :placeholder="$t('vtt.token.settings.transfer.placeholder')"
      spellcheck="false"
    />
    <small :class="{ error: status === 'error' }">{{ message }}</small>
  </details>
</template>

<script>
import {
  exportTokenSettings,
  importTokenSettings,
} from "@/lib/vtt/tokenSettingsTransfer";

export default {
  name: "TokenSettingsTransfer",
  props: { draft: { type: Object, required: true } },
  emits: ["apply"],
  data: () => ({ source: "", status: "idle" }),
  computed: {
    message() {
      return this.$t(`vtt.token.settings.transfer.${this.status}`);
    },
  },
  methods: {
    async copy() {
      this.source = exportTokenSettings(this.draft);
      try {
        await navigator.clipboard.writeText(this.source);
        this.status = "copied";
      } catch (_error) {
        this.status = "manualCopy";
        this.$nextTick(() => this.$refs.source?.select());
      }
    },
    async paste() {
      try {
        this.source = await navigator.clipboard.readText();
        this.status = "pasted";
      } catch (_error) {
        this.status = "manualPaste";
        this.$nextTick(() => this.$refs.source?.focus());
      }
    },
    apply() {
      try {
        this.$emit("apply", importTokenSettings(this.source, this.draft));
        this.status = "applied";
      } catch (_error) {
        this.status = "error";
      }
    },
  },
};
</script>
