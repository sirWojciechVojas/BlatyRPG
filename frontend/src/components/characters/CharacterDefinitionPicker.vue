<template>
  <section class="character-definition-picker">
    <div class="character-definition-picker__search">
      <input
        v-model.trim="query"
        type="search"
        autocomplete="off"
        :placeholder="placeholder"
        :disabled="disabled || loading"
        @focus="open = true"
        @blur="closeLater"
        @keydown.esc="open = false"
      />
      <span v-if="loading" aria-live="polite">…</span>
      <div v-if="open && suggestions.length" role="listbox">
        <button
          v-for="item in suggestions"
          :key="item.id"
          type="button"
          role="option"
          @mousedown.prevent
          @click="add(item)"
        >
          <strong>{{ item.name }}</strong>
          <small v-if="item.description">{{ item.description }}</small>
        </button>
      </div>
    </div>

    <ul v-if="modelValue.length" class="character-definition-picker__items">
      <li v-for="(item, index) in modelValue" :key="itemKey(item, index)">
        <span>{{ itemName(item) }}</span>
        <button
          type="button"
          :aria-label="$t('characters.development.removeEntry')"
          :disabled="disabled"
          @click="remove(index)"
        >
          ×
        </button>
      </li>
    </ul>
    <p v-else class="character-muted">
      {{ $t("characters.development.noEntries") }}
    </p>
  </section>
</template>

<script>
const normalized = (value) =>
  String(value || "")
    .trim()
    .toLocaleLowerCase("pl-PL");

export default {
  name: "CharacterDefinitionPicker",
  props: {
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: "" },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
  },
  emits: ["update:modelValue"],
  data: () => ({ query: "", open: false }),
  computed: {
    selectedNames() {
      return new Set(
        this.modelValue.map((item) => normalized(this.itemName(item))),
      );
    },
    suggestions() {
      const query = normalized(this.query);
      return this.options
        .filter((item) => !this.selectedNames.has(normalized(item.name)))
        .filter((item) => !query || normalized(item.name).includes(query))
        .sort((left, right) => left.name.localeCompare(right.name, "pl"))
        .slice(0, 10);
    },
  },
  methods: {
    itemName(item) {
      return typeof item === "string" ? item : String(item?.name || "");
    },
    itemKey(item, index) {
      return `${item?.definitionId || item?.id || this.itemName(item)}-${index}`;
    },
    add(item) {
      this.$emit("update:modelValue", [
        ...this.modelValue,
        {
          definitionId: Number(item.id),
          name: item.name,
          description: item.description,
        },
      ]);
      this.query = "";
      this.open = false;
    },
    remove(index) {
      this.$emit(
        "update:modelValue",
        this.modelValue.filter((_item, itemIndex) => itemIndex !== index),
      );
    },
    closeLater() {
      window.setTimeout(() => {
        this.open = false;
      }, 120);
    },
  },
};
</script>
