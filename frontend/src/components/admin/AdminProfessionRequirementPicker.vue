<template>
  <section class="admin-requirement-picker">
    <header>
      <strong>{{ label }}</strong>
      <small>{{ modelValue.length }}</small>
    </header>

    <ul v-if="modelValue.length" class="admin-requirement-picker__selected">
      <li v-for="(item, index) in modelValue" :key="`${item.raw}-${index}`">
        <span>
          <strong>{{ item.display }}</strong>
          <small>ID: {{ item.raw }}</small>
        </span>
        <button
          type="button"
          :aria-label="
            $t('admin.professions.removeRequirement', { name: item.display })
          "
          :title="
            $t('admin.professions.removeRequirement', { name: item.display })
          "
          @click="remove(index)"
        >
          ×
        </button>
      </li>
    </ul>
    <p v-else>{{ $t("admin.professions.noRequirements") }}</p>

    <div class="admin-requirement-picker__search">
      <input
        ref="input"
        v-model="query"
        type="text"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="list"
        :aria-expanded="open ? 'true' : 'false'"
        :aria-controls="listboxId"
        :aria-activedescendant="activeOptionId"
        :placeholder="placeholder"
        @focus="openPicker"
        @input="search"
        @blur="closeLater"
        @keydown.down.prevent="moveActive(1)"
        @keydown.up.prevent="moveActive(-1)"
        @keydown.enter.prevent="chooseActive"
        @keydown.esc="closePicker"
      />
      <ul
        v-if="open && suggestions.length"
        :id="listboxId"
        class="admin-requirement-picker__suggestions"
        role="listbox"
        :aria-label="label"
      >
        <li v-for="(option, index) in suggestions" :key="option.raw">
          <button
            :id="optionId(index)"
            type="button"
            role="option"
            :aria-selected="index === activeIndex ? 'true' : 'false'"
            :class="{ active: index === activeIndex }"
            @mousedown.prevent
            @click="choose(option)"
          >
            <span>{{ option.display }}</span>
            <small>ID: {{ option.raw }}</small>
          </button>
        </li>
      </ul>
      <small
        v-else-if="open && normalizedQuery"
        class="admin-requirement-picker__empty"
      >
        {{ $t("admin.professions.requirementNoResults") }}
      </small>
    </div>
    <small class="admin-requirement-picker__hint">{{ hint }}</small>
  </section>
</template>

<script>
let pickerSequence = 0;

const normalize = (value) =>
  String(value || "")
    .toLocaleLowerCase("pl-PL")
    .replace(/ł/gu, "l")
    .normalize("NFD")
    .replace(/\p{Diacritic}/gu, "")
    .trim();

export default {
  name: "AdminProfessionRequirementPicker",
  props: {
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] },
    label: { type: String, required: true },
    placeholder: { type: String, required: true },
    hint: { type: String, required: true },
  },
  emits: ["update:modelValue"],
  data() {
    pickerSequence += 1;
    return {
      query: "",
      open: false,
      activeIndex: -1,
      closeTimer: null,
      listboxId: `admin-requirement-list-${pickerSequence}`,
    };
  },
  computed: {
    normalizedQuery() {
      return normalize(this.query);
    },
    suggestions() {
      const query = this.normalizedQuery;
      if (!query) return [];
      const selected = new Set(this.modelValue.map((item) => item.raw));
      return this.options
        .filter((option) => !selected.has(option.raw))
        .map((option) => {
          const display = normalize(option.display);
          const words = display.split(" ");
          let rank = 3;
          if (display.startsWith(query)) rank = 0;
          else if (words.some((word) => word.startsWith(query))) rank = 1;
          else if (display.includes(query) || option.raw.includes(query)) {
            rank = 2;
          }
          return { option, rank };
        })
        .filter((item) => item.rank < 3)
        .sort(
          (left, right) =>
            left.rank - right.rank ||
            left.option.display.localeCompare(right.option.display, "pl", {
              sensitivity: "base",
              numeric: true,
            }),
        )
        .slice(0, 12)
        .map((item) => item.option);
    },
    activeOptionId() {
      return this.open && this.activeIndex >= 0
        ? this.optionId(this.activeIndex)
        : undefined;
    },
  },
  beforeUnmount() {
    window.clearTimeout(this.closeTimer);
  },
  methods: {
    optionId(index) {
      return `${this.listboxId}-option-${index}`;
    },
    openPicker() {
      window.clearTimeout(this.closeTimer);
      this.open = true;
      this.activeIndex = this.suggestions.length ? 0 : -1;
    },
    search() {
      this.open = true;
      this.activeIndex = this.suggestions.length ? 0 : -1;
    },
    closeLater() {
      window.clearTimeout(this.closeTimer);
      this.closeTimer = window.setTimeout(() => this.closePicker(), 120);
    },
    closePicker() {
      this.open = false;
      this.activeIndex = -1;
    },
    moveActive(direction) {
      if (!this.open) this.open = true;
      if (!this.suggestions.length) return;
      this.activeIndex =
        (this.activeIndex + direction + this.suggestions.length) %
        this.suggestions.length;
      this.$nextTick(() => {
        document
          .getElementById(this.activeOptionId)
          ?.scrollIntoView?.({ block: "nearest" });
      });
    },
    chooseActive() {
      const option = this.suggestions[this.activeIndex];
      if (this.open && option) this.choose(option);
    },
    choose(option) {
      this.$emit("update:modelValue", [
        ...this.modelValue,
        { raw: option.raw, display: option.display, decoded: true },
      ]);
      this.query = "";
      this.open = false;
      this.activeIndex = -1;
      this.$nextTick(() => this.$refs.input?.focus());
    },
    remove(index) {
      this.$emit(
        "update:modelValue",
        this.modelValue.filter((_item, itemIndex) => itemIndex !== index),
      );
    },
  },
};
</script>
