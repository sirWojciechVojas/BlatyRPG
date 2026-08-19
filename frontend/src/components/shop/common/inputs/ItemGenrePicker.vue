<!-- Responsibility: ItemGenrePicker shop interface component. -->
<template>
  <div class="field-edit-panel">
    <div class="field-edit-selection-bar">
      {{
        $t("modals.fieldEdit.fields.itemGenre.selected", {
          classCode: selectedClass || $t("modals.fieldEdit.common.emptyValue"),
          value: modelText || $t("modals.fieldEdit.common.emptyValue"),
        })
      }}
    </div>

    <div v-if="disabled" class="field-edit-muted">
      {{ $t("modals.fieldEdit.fields.itemGenre.selectClassFirst") }}
    </div>

    <template v-else>
      <input
        :value="modelText"
        type="text"
        class="form-control-sm trade-input field-edit-value__input"
        :placeholder="$t('modals.fieldEdit.fields.itemGenre.inputPlaceholder')"
        data-autofocus="true"
        @input="$emit('update:modelValue', $event?.target?.value || '')"
      />
      <input
        v-model.trim="searchQuery"
        type="text"
        class="form-control-sm trade-input field-edit-value__search"
        :placeholder="$t('modals.fieldEdit.fields.itemGenre.searchPlaceholder')"
      />

      <div class="field-edit-genre-groups">
        <section
          v-for="group in groupedSuggestions"
          :key="`genre-group-${group.key}`"
          class="field-edit-genre-group"
        >
          <header class="field-edit-genre-group__title">
            {{ group.key }}
          </header>
          <div class="field-edit-value__suggestions">
            <button
              v-for="entry in group.items"
              :key="`genre-${entry}`"
              type="button"
              class="field-edit-suggestion"
              :class="{ active: entry === modelText }"
              @click="$emit('update:modelValue', entry)"
            >
              {{ entry }}
            </button>
          </div>
        </section>
        <div v-if="!groupedSuggestions.length" class="img-grid-empty">
          {{ $t("modals.fieldEdit.fields.itemGenre.noResults") }}
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: "",
  },
  suggestions: {
    type: Array,
    default: () => [],
  },
  selectedClass: {
    type: String,
    default: "",
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

defineEmits(["update:modelValue"]);

const searchQuery = ref("");

const modelText = computed(() =>
  String(props.modelValue ?? "")
    .trim()
    .toUpperCase(),
);

const filteredSuggestions = computed(() => {
  const source = Array.isArray(props.suggestions) ? props.suggestions : [];
  const token = searchQuery.value.trim().toUpperCase();
  const normalized = source
    .map((entry) =>
      String(entry || "")
        .trim()
        .toUpperCase(),
    )
    .filter(Boolean);
  if (!token) {
    return normalized;
  }
  return normalized.filter((entry) => entry.includes(token));
});

const groupedSuggestions = computed(() => {
  const groups = new Map();
  filteredSuggestions.value.forEach((entry) => {
    const key = entry[0] || "#";
    if (!groups.has(key)) {
      groups.set(key, []);
    }
    groups.get(key).push(entry);
  });
  return Array.from(groups.entries())
    .sort(([left], [right]) => left.localeCompare(right, "pl"))
    .map(([key, items]) => ({
      key,
      items: [...items].sort((left, right) => left.localeCompare(right, "pl")),
    }));
});
</script>
