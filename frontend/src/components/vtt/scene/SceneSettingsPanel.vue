<template>
  <aside class="scene-settings" :aria-labelledby="titleId">
    <header class="scene-settings__header">
      <h2 :id="titleId">
        {{
          $t(
            mode === "create"
              ? "vtt.scene.settings.createTitle"
              : "vtt.scene.settings.editTitle",
          )
        }}
      </h2>
      <button
        type="button"
        class="scene-button"
        :aria-label="$t('vtt.scene.actions.cancel')"
        @click="$emit('cancel')"
      >
        ×
      </button>
    </header>

    <form class="scene-settings__form" @submit.prevent="submit">
      <label class="scene-field scene-field--wide">
        <span>{{ $t("vtt.scene.fields.name") }}</span>
        <input v-model.trim="form.name" required maxlength="150" />
      </label>
      <label class="scene-field scene-field--wide">
        <span>{{ $t("vtt.scene.fields.description") }}</span>
        <textarea
          v-model="form.description"
          rows="3"
          maxlength="10000"
        ></textarea>
      </label>
      <label class="scene-field scene-field--wide">
        <span>{{ $t("vtt.scene.fields.backgroundUrl") }}</span>
        <input
          v-model.trim="form.backgroundUrl"
          type="text"
          inputmode="url"
          maxlength="2048"
        />
      </label>
      <label class="scene-field">
        <span>{{ $t("vtt.scene.fields.width") }}</span>
        <input
          v-model.number="form.width"
          type="number"
          min="256"
          max="50000"
          required
        />
      </label>
      <label class="scene-field">
        <span>{{ $t("vtt.scene.fields.height") }}</span>
        <input
          v-model.number="form.height"
          type="number"
          min="256"
          max="50000"
          required
        />
      </label>
      <label class="scene-field">
        <span>{{ $t("vtt.scene.fields.padding") }}</span>
        <input v-model.number="form.padding" type="number" min="0" max="5000" />
      </label>
      <label class="scene-field">
        <span>{{ $t("vtt.scene.fields.backgroundColor") }}</span>
        <input v-model="form.backgroundColor" type="color" />
      </label>

      <fieldset class="scene-settings__group">
        <legend>{{ $t("vtt.scene.settings.grid") }}</legend>
        <label class="scene-field scene-field--wide">
          <span>{{ $t("vtt.scene.fields.gridType") }}</span>
          <select v-model="form.gridType">
            <option v-for="type in gridTypes" :key="type" :value="type">
              {{ $t(`vtt.scene.grid.${type}`) }}
            </option>
          </select>
        </label>
        <template v-if="form.gridType !== 'gridless'">
          <label class="scene-field">
            <span>{{ $t("vtt.scene.fields.gridSize") }}</span>
            <input
              v-model.number="form.gridSize"
              type="number"
              min="1"
              max="1000"
              required
            />
          </label>
          <label class="scene-field">
            <span>{{ $t("vtt.scene.fields.gridDistance") }}</span>
            <input
              v-model.number="form.gridDistance"
              type="number"
              min="0.01"
              max="1000000"
              step="0.01"
              required
            />
          </label>
          <label class="scene-field">
            <span>{{ $t("vtt.scene.fields.gridUnit") }}</span>
            <input v-model.trim="form.gridUnit" maxlength="32" required />
          </label>
          <label class="scene-field">
            <span>{{ $t("vtt.scene.fields.gridColor") }}</span>
            <input v-model="form.gridColor" type="color" />
          </label>
          <label class="scene-field">
            <span>{{ $t("vtt.scene.fields.gridOffsetX") }}</span>
            <input
              v-model.number="form.gridOffsetX"
              type="number"
              min="-50000"
              max="50000"
              step="0.1"
            />
          </label>
          <label class="scene-field">
            <span>{{ $t("vtt.scene.fields.gridOffsetY") }}</span>
            <input
              v-model.number="form.gridOffsetY"
              type="number"
              min="-50000"
              max="50000"
              step="0.1"
            />
          </label>
          <label class="scene-field scene-field--wide">
            <span
              >{{ $t("vtt.scene.fields.gridOpacity") }}:
              {{ form.gridOpacity }}</span
            >
            <input
              v-model.number="form.gridOpacity"
              type="range"
              min="0"
              max="1"
              step="0.05"
            />
          </label>
        </template>
      </fieldset>

      <label class="scene-field">
        <span>{{ $t("vtt.scene.fields.sortOrder") }}</span>
        <input
          v-model.number="form.sortOrder"
          type="number"
          min="-100000"
          max="100000"
        />
      </label>
      <label class="scene-field scene-field--check">
        <input v-model="form.isVisible" type="checkbox" />
        <span>{{ $t("vtt.scene.fields.isVisible") }}</span>
      </label>

      <footer class="scene-settings__footer">
        <button
          v-if="mode === 'edit'"
          type="button"
          class="scene-button scene-button--danger"
          :disabled="busy"
          @click="$emit('delete')"
        >
          {{ $t("vtt.scene.actions.delete") }}
        </button>
        <span class="scene-settings__spacer"></span>
        <button
          type="button"
          class="scene-button"
          :disabled="busy"
          @click="$emit('cancel')"
        >
          {{ $t("vtt.scene.actions.cancel") }}
        </button>
        <button
          type="submit"
          class="scene-button scene-button--primary"
          :disabled="busy"
        >
          {{ $t("vtt.scene.actions.save") }}
        </button>
      </footer>
    </form>
  </aside>
</template>

<script>
import { GRID_TYPES } from "@/lib/vtt/grid";

const emptyScene = () => ({
  name: "",
  description: "",
  backgroundUrl: "",
  width: 1920,
  height: 1080,
  padding: 0,
  backgroundColor: "#20242b",
  gridType: GRID_TYPES.SQUARE,
  gridSize: 100,
  gridDistance: 5,
  gridUnit: "m",
  gridOffsetX: 0,
  gridOffsetY: 0,
  gridColor: "#000000",
  gridOpacity: 0.35,
  isVisible: true,
  sortOrder: 0,
});

export default {
  name: "SceneSettingsPanel",
  props: {
    scene: { type: Object, default: null },
    mode: { type: String, default: "edit" },
    busy: { type: Boolean, default: false },
  },
  emits: ["save", "cancel", "delete"],
  data: () => ({
    form: emptyScene(),
    gridTypes: Object.values(GRID_TYPES),
    titleId: "scene-settings-title",
  }),
  watch: {
    scene: { handler: "reset", immediate: true },
    mode: "reset",
  },
  methods: {
    reset() {
      this.form = { ...emptyScene(), ...(this.scene || {}) };
    },
    submit() {
      const payload = { ...this.form };
      for (const field of [
        "id",
        "campaignId",
        "revision",
        "createdAt",
        "updatedAt",
      ]) {
        delete payload[field];
      }
      this.$emit("save", payload);
    },
  },
};
</script>
