<!-- Responsibility: ItemClassPicker shop interface component. -->
<template>
  <div class="field-edit-panel">
    <div class="field-edit-selection-bar">
      {{
        $t("modals.fieldEdit.fields.itemClass.selected", {
          label: selectedLabel,
          code: selectedCode || $t("modals.fieldEdit.common.emptyValue"),
        })
      }}
    </div>

    <input
      v-model.trim="searchQuery"
      type="text"
      class="form-control-sm trade-input field-edit-value__search"
      :placeholder="$t('modals.fieldEdit.fields.itemClass.searchPlaceholder')"
      data-autofocus="true"
    />

    <div class="field-edit-class-groups">
      <section
        v-for="group in filteredGroups"
        :key="`class-group-${group.id}`"
        class="field-edit-class-group"
      >
        <header class="field-edit-class-group__title">
          {{ $t(`modals.fieldEdit.fields.itemClass.groups.${group.id}`) }}
        </header>
        <div class="field-edit-class-grid">
          <button
            v-for="entry in group.items"
            :key="`class-option-${entry.code}`"
            type="button"
            class="field-edit-class-card"
            :class="{ active: entry.code === selectedCode }"
            :aria-pressed="String(entry.code === selectedCode)"
            @click="$emit('update:modelValue', entry.code)"
          >
            <span class="field-edit-class-card__icon">
              <span :class="`bi ${entry.icon}`" aria-hidden="true"></span>
            </span>
            <span class="field-edit-class-card__label">
              {{
                $t(
                  `modals.fieldEdit.fields.itemClass.options.${entry.code}.label`,
                )
              }}
            </span>
            <span class="field-edit-class-card__code">{{ entry.code }}</span>
            <span class="field-edit-class-card__desc">
              {{
                $t(
                  `modals.fieldEdit.fields.itemClass.options.${entry.code}.description`,
                )
              }}
            </span>
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import i18n from "@/i18n";

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: "",
  },
});

defineEmits(["update:modelValue"]);

const searchQuery = ref("");

const classGroups = [
  {
    id: "equipment",
    items: [
      { code: "WEAPON", icon: "bi-sword" },
      { code: "ARMOR", icon: "bi-shield-fill" },
      { code: "CLOTH", icon: "bi-person-bounding-box" },
      { code: "ARMAMENT", icon: "bi-bullseye" },
      { code: "TOOL", icon: "bi-wrench-adjustable-circle" },
      { code: "CUTLERY", icon: "bi-fork-knife" },
    ],
  },
  {
    id: "consumables",
    items: [
      { code: "POTION", icon: "bi-cup-hot" },
      { code: "FOOD", icon: "bi-egg-fried" },
      { code: "ALCHEMY", icon: "bi-eyedropper" },
      { code: "POWDER", icon: "bi-box-seam" },
    ],
  },
  {
    id: "treasures",
    items: [
      { code: "JEWELLERY", icon: "bi-gem" },
      { code: "MAGIC", icon: "bi-stars" },
    ],
  },
  {
    id: "other",
    items: [
      { code: "GADGET", icon: "bi-cpu" },
      { code: "STATIONERY", icon: "bi-journal-text" },
      { code: "FORAGE", icon: "bi-flower1" },
      { code: "ANIMAL", icon: "bi-bug-fill" },
    ],
  },
];

const selectedCode = computed(() =>
  String((props.modelValue ?? "") || "")
    .trim()
    .toUpperCase(),
);

const selectedLabel = computed(() => {
  if (!selectedCode.value) {
    return i18n.global.t("modals.fieldEdit.common.emptyValue");
  }
  return (
    i18n.global.t(
      `modals.fieldEdit.fields.itemClass.options.${selectedCode.value}.label`,
    ) || selectedCode.value
  );
});

const filteredGroups = computed(() => {
  const token = searchQuery.value.trim().toLowerCase();
  if (!token) {
    return classGroups;
  }
  return classGroups
    .map((group) => ({
      ...group,
      items: group.items.filter((entry) => {
        const code = String(entry.code || "");
        const label = String(
          i18n.global.t(
            `modals.fieldEdit.fields.itemClass.options.${entry.code}.label`,
          ) || "",
        );
        const description = String(
          i18n.global.t(
            `modals.fieldEdit.fields.itemClass.options.${entry.code}.description`,
          ) || "",
        );
        const haystack = `${code} ${label} ${description}`.toLowerCase();
        return haystack.includes(token);
      }),
    }))
    .filter((group) => group.items.length > 0);
});
</script>
