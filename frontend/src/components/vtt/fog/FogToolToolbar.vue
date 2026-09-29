<template>
  <div class="fog-toolbar" role="toolbar" :aria-label="$t('vtt.fog.toolbar')">
    <select
      :value="previewValue"
      :title="$t('vtt.fog.preview')"
      @change="selectPreview"
    >
      <option value="gm">{{ $t("vtt.fog.gmView") }}</option>
      <optgroup :label="$t('vtt.fog.players')">
        <option
          v-for="member in playerMembers"
          :key="`user-${member.userId}`"
          :value="`user:${member.userId}`"
        >
          {{ member.username || member.displayName || `#${member.userId}` }}
        </option>
      </optgroup>
      <optgroup :label="$t('vtt.fog.tokens')">
        <option
          v-for="token in tokens"
          :key="`token-${token.id}`"
          :value="`token:${token.id}`"
        >
          {{ token.name }}
        </option>
      </optgroup>
    </select>
    <span class="fog-toolbar__separator" />
    <button
      v-for="tool in tools"
      :key="tool.id"
      type="button"
      :class="{ active: modelValue === tool.id }"
      :disabled="busy || !editable"
      :title="$t(tool.label)"
      @click="$emit('update:modelValue', tool.id)"
    >
      <span aria-hidden="true">{{ tool.icon }}</span>
      <span>{{ $t(tool.label) }}</span>
    </button>
    <div v-if="brushTool" class="fog-toolbar__brush-settings">
      <label :title="$t('vtt.fog.brushSize')">
        <span>{{ $t("vtt.fog.brushSize") }}</span>
        <input
          :value="brushSize"
          type="range"
          :min="brushMin"
          :max="brushMax"
          :step="brushStep"
          @input="updateBrush('size', $event)"
        />
        <output>{{ Math.round(brushSize) }}</output>
      </label>
      <label :title="$t('vtt.fog.brushHardness')">
        <span>{{ $t("vtt.fog.brushHardness") }}</span>
        <input
          :value="brushHardness"
          type="range"
          min="0"
          max="100"
          step="1"
          @input="updateBrush('hardness', $event)"
        />
        <output>{{ Math.round(brushHardness) }}%</output>
      </label>
    </div>
    <span class="fog-toolbar__separator" />
    <button
      type="button"
      :disabled="busy || !editable"
      :title="$t('vtt.fog.revealAll')"
      @click="$emit('command', 'revealall')"
    >
      ◫
    </button>
    <button
      type="button"
      :disabled="busy || !editable"
      :title="$t('vtt.fog.hideAll')"
      @click="$emit('command', 'hideall')"
    >
      ▣
    </button>
    <button
      type="button"
      class="danger"
      :disabled="busy || !editable"
      :title="$t('vtt.fog.reset')"
      @click="$emit('command', 'reset')"
    >
      ↺
    </button>
  </div>
</template>

<script>
export default {
  name: "FogToolToolbar",
  props: {
    modelValue: { type: String, default: "reveal-rect" },
    members: { type: Array, default: () => [] },
    tokens: { type: Array, default: () => [] },
    preview: { type: Object, default: () => ({ mode: "gm", id: null }) },
    busy: { type: Boolean, default: false },
    brushSize: { type: Number, default: 100 },
    brushHardness: { type: Number, default: 85 },
    brushMin: { type: Number, default: 16 },
    brushMax: { type: Number, default: 2000 },
    brushStep: { type: Number, default: 8 },
  },
  emits: ["update:modelValue", "command", "preview-change", "brush-change"],
  data: () => ({
    tools: [
      { id: "reveal-rect", icon: "□", label: "vtt.fog.revealRect" },
      { id: "reveal-poly", icon: "⬡", label: "vtt.fog.revealPolygon" },
      { id: "reveal-brush", icon: "●", label: "vtt.fog.revealBrush" },
      { id: "hide-rect", icon: "▩", label: "vtt.fog.hideRect" },
      { id: "hide-poly", icon: "⬢", label: "vtt.fog.hidePolygon" },
      { id: "hide-brush", icon: "◐", label: "vtt.fog.hideBrush" },
    ],
  }),
  computed: {
    playerMembers() {
      return this.members.filter((member) => member.role !== "gm");
    },
    previewValue() {
      return this.preview?.mode === "gm"
        ? "gm"
        : `${this.preview.mode}:${this.preview.id}`;
    },
    editable() {
      return this.preview?.mode === "user" && Number(this.preview.id) > 0;
    },
    brushTool() {
      return this.modelValue.includes("brush");
    },
  },
  methods: {
    selectPreview(event) {
      const [mode, rawId] = String(event.target.value).split(":");
      this.$emit("preview-change", { mode, id: Number(rawId) || null });
    },
    updateBrush(field, event) {
      this.$emit("brush-change", {
        size: field === "size" ? Number(event.target.value) : this.brushSize,
        hardness:
          field === "hardness"
            ? Number(event.target.value)
            : this.brushHardness,
      });
    },
  },
};
</script>
