<template>
  <div class="profession-picker">
    <div class="profession-picker__input">
      <input
        :id="inputId"
        ref="input"
        v-model="query"
        type="text"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="list"
        :aria-expanded="open ? 'true' : 'false'"
        :aria-controls="listboxId"
        :aria-activedescendant="activeOptionId"
        :placeholder="$t('vtt.table.professions.searchProfession')"
        :disabled="disabled"
        @focus="openPicker"
        @input="search"
        @blur="closeLater"
        @keydown.down.prevent="moveActive(1)"
        @keydown.up.prevent="moveActive(-1)"
        @keydown.enter.prevent="chooseActive"
        @keydown.esc="closePicker"
      />
      <button
        v-if="modelValue || query"
        type="button"
        class="profession-picker__clear"
        :aria-label="$t('vtt.table.professions.clearProfession')"
        :title="$t('vtt.table.professions.clearProfession')"
        :disabled="disabled"
        @mousedown.prevent
        @click="clear"
      >
        ×
      </button>
    </div>

    <div v-if="open" class="profession-picker__dropdown">
      <ul
        v-if="suggestions.length"
        :id="listboxId"
        class="profession-picker__options"
        role="listbox"
        :aria-label="$t('vtt.table.professions.professionSuggestions')"
      >
        <li v-for="(profession, index) in suggestions" :key="profession.id">
          <button
            :id="optionId(index)"
            type="button"
            role="option"
            :aria-selected="index === activeIndex ? 'true' : 'false'"
            :aria-describedby="
              previewProfession?.id === profession.id ? tooltipId : undefined
            "
            :class="{ 'is-active': index === activeIndex }"
            @mousedown.prevent
            @click="choose(profession)"
            @mouseenter="showPreview(profession, $event.currentTarget)"
            @mouseleave="hidePreview(profession)"
            @focus="showPreview(profession, $event.currentTarget)"
          >
            <span>
              <strong>{{ displayName(profession.name) }}</strong>
              <small>
                {{ professionType(profession) }} ·
                {{
                  $t("vtt.table.professions.entryNumber", { id: profession.id })
                }}
              </small>
            </span>
            <b aria-hidden="true">i</b>
          </button>
        </li>
      </ul>
      <p v-else class="profession-picker__empty">
        {{
          $t(
            normalizedQuery
              ? "vtt.table.professions.noProfessionSuggestions"
              : "vtt.table.professions.autocompleteHint",
          )
        }}
      </p>
    </div>

    <Teleport to="body">
      <aside
        v-if="previewProfession"
        :id="tooltipId"
        class="profession-picker-tooltip"
        :class="{ 'is-interactive': previewInteractive }"
        :style="previewStyle"
        role="tooltip"
        :tabindex="previewInteractive ? 0 : undefined"
        @mouseenter="enterPreview"
        @mouseleave="leavePreview"
        @focus="enterPreview"
        @blur="leavePreview"
      >
        <header>
          <div>
            <span>{{ professionType(previewProfession) }}</span>
            <strong>{{ displayName(previewProfession.name) }}</strong>
          </div>
          <small>{{
            $t("vtt.table.professions.entryNumber", {
              id: previewProfession.id,
            })
          }}</small>
        </header>
        <div class="profession-picker-tooltip__bridge" role="status">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle class="track" cx="12" cy="12" r="9" />
            <circle class="progress" cx="12" cy="12" r="9" />
          </svg>
          <small>{{
            $t(
              previewInteractive
                ? "vtt.table.professions.tooltipBridgeReady"
                : "vtt.table.professions.tooltipBridgeWait",
            )
          }}</small>
        </div>
        <p>{{ previewDescription }}</p>
        <dl v-if="previewSections.length">
          <div v-for="section in previewSections" :key="section.label">
            <dt>{{ section.label }}</dt>
            <dd>{{ section.value }}</dd>
          </div>
        </dl>
      </aside>
    </Teleport>
  </div>
</template>

<script>
import {
  displayProfessionName,
  normalizeProfessionText,
  presentProfessionText,
  professionCollator,
} from "./professionPresentation";

let pickerSequence = 0;
const SECONDARY_ATTRIBUTES = new Set([
  "attacks",
  "wounds",
  "strength_bonus",
  "toughness_bonus",
  "movement",
  "magic",
  "insanity_points",
  "fate_points",
]);

export default {
  name: "ProfessionAutocomplete",
  props: {
    modelValue: { type: [Number, String], default: null },
    professions: { type: Array, default: () => [] },
    inputId: { type: String, default: "character-profession-input" },
    disabled: { type: Boolean, default: false },
  },
  emits: ["update:modelValue"],
  data() {
    pickerSequence += 1;
    return {
      query: "",
      open: false,
      activeIndex: -1,
      previewProfession: null,
      previewInteractive: false,
      previewStyle: {},
      closeTimer: null,
      previewTimer: null,
      previewHideTimer: null,
      listboxId: `profession-picker-list-${pickerSequence}`,
      tooltipId: `profession-picker-tip-${pickerSequence}`,
    };
  },
  computed: {
    normalizedQuery() {
      return normalizeProfessionText(this.query);
    },
    selectedProfession() {
      return (
        this.professions.find(
          (profession) => Number(profession.id) === Number(this.modelValue),
        ) || null
      );
    },
    suggestions() {
      const query = this.normalizedQuery;
      return this.professions
        .map((profession) => {
          const name = normalizeProfessionText(profession.name);
          const words = name.split(" ");
          let rank = 3;
          if (!query) rank = 2;
          else if (name.startsWith(query)) rank = 0;
          else if (words.some((word) => word.startsWith(query))) rank = 1;
          else if (name.includes(query)) rank = 2;
          return { profession, rank };
        })
        .filter((item) => !query || item.rank < 3)
        .sort(
          (left, right) =>
            left.rank - right.rank ||
            professionCollator.compare(
              displayProfessionName(left.profession.name),
              displayProfessionName(right.profession.name),
            ),
        )
        .slice(0, 10)
        .map((item) => item.profession);
    },
    activeOptionId() {
      return this.open && this.activeIndex >= 0
        ? this.optionId(this.activeIndex)
        : undefined;
    },
    previewDescription() {
      const value = presentProfessionText(
        this.previewProfession?.description || this.previewProfession?.details,
      ).replace(/\s+/gu, " ");
      if (!value || value.toLocaleUpperCase("pl-PL") === "BRAK") {
        return this.$t("vtt.table.professions.descriptionMissing");
      }
      return value.length > 320 ? `${value.slice(0, 317).trim()}…` : value;
    },
    previewSections() {
      const development = this.previewProfession?.development || {};
      const sections = [];
      const attributes = (development.attributes || [])
        .filter((attribute) => Number(attribute.value || 0) !== 0)
        .map((attribute) => {
          const suffix = SECONDARY_ATTRIBUTES.has(attribute.key) ? "" : "%";
          const value = Number(attribute.value || 0);
          return `${this.$t(
            `vtt.table.professions.attributes.${attribute.key}`,
          )} ${value > 0 ? "+" : ""}${value}${suffix}`;
        });
      this.addPreviewSection(
        sections,
        this.$t("vtt.table.professions.attributesTitle"),
        attributes,
        8,
      );
      this.addPreviewSection(
        sections,
        this.$t("vtt.table.professions.skillsTitle"),
        this.definitionLabels(development.skills),
        5,
      );
      this.addPreviewSection(
        sections,
        this.$t("vtt.table.professions.talentsTitle"),
        this.definitionLabels(development.talents),
        5,
      );
      this.addPreviewSection(
        sections,
        this.$t("vtt.table.professions.entryProfessions"),
        this.pathLabels(development.paths?.entries),
        4,
      );
      this.addPreviewSection(
        sections,
        this.$t("vtt.table.professions.exitProfessions"),
        this.pathLabels(development.paths?.exits),
        4,
      );
      return sections;
    },
  },
  watch: {
    modelValue: {
      immediate: true,
      handler() {
        if (!this.open) this.restoreSelectedName();
      },
    },
    professions() {
      if (!this.open) this.restoreSelectedName();
    },
  },
  beforeUnmount() {
    window.clearTimeout(this.closeTimer);
    window.clearTimeout(this.previewTimer);
    window.clearTimeout(this.previewHideTimer);
  },
  methods: {
    displayName: displayProfessionName,
    optionId(index) {
      return `${this.listboxId}-option-${index}`;
    },
    professionType(profession) {
      return this.$t(
        profession?.is_advanced
          ? "vtt.table.professions.advancedProfession"
          : "vtt.table.professions.basicProfession",
      );
    },
    restoreSelectedName() {
      this.query = this.selectedProfession
        ? displayProfessionName(this.selectedProfession.name)
        : "";
    },
    openPicker(event) {
      window.clearTimeout(this.closeTimer);
      this.open = true;
      this.activeIndex = this.suggestions.length ? 0 : -1;
      event?.currentTarget?.select?.();
    },
    search() {
      this.open = true;
      this.activeIndex = this.suggestions.length ? 0 : -1;
      this.resetPreview();
      this.$emit("update:modelValue", null);
    },
    closeLater() {
      window.clearTimeout(this.closeTimer);
      this.closeTimer = window.setTimeout(() => this.closePicker(), 120);
    },
    closePicker() {
      this.open = false;
      this.activeIndex = -1;
      this.resetPreview();
      this.restoreSelectedName();
    },
    clear() {
      this.$emit("update:modelValue", null);
      this.query = "";
      this.open = true;
      this.activeIndex = this.suggestions.length ? 0 : -1;
      this.$nextTick(() => this.$refs.input?.focus());
    },
    moveActive(direction) {
      if (!this.open) this.open = true;
      if (!this.suggestions.length) return;
      const total = this.suggestions.length;
      this.activeIndex = (this.activeIndex + direction + total) % total;
      this.$nextTick(() => {
        const row = document.getElementById(this.activeOptionId);
        row?.scrollIntoView?.({ block: "nearest" });
        this.showPreview(this.suggestions[this.activeIndex], row);
      });
    },
    chooseActive() {
      const profession = this.suggestions[this.activeIndex];
      if (this.open && profession) this.choose(profession);
    },
    choose(profession) {
      this.$emit("update:modelValue", Number(profession.id));
      this.query = displayProfessionName(profession.name);
      this.open = false;
      this.activeIndex = -1;
      this.resetPreview();
    },
    showPreview(profession, target) {
      if (!profession || !target?.getBoundingClientRect) return;
      window.clearTimeout(this.previewHideTimer);
      const changed = this.previewProfession?.id !== profession.id;
      this.previewProfession = profession;
      if (changed) {
        window.clearTimeout(this.previewTimer);
        this.previewInteractive = false;
        this.previewTimer = window.setTimeout(() => {
          if (this.previewProfession?.id === profession.id) {
            this.previewInteractive = true;
          }
        }, 2000);
      }
      const rect = target.getBoundingClientRect();
      const viewportWidth = window.innerWidth || 1280;
      const viewportHeight = window.innerHeight || 720;
      const width = Math.min(380, Math.max(260, viewportWidth - 16));
      const gap = 10;
      let left = rect.right + gap;
      let top = Math.max(8, Math.min(rect.top - 8, viewportHeight - 430));
      if (left + width > viewportWidth - 8) {
        left = rect.left - width - gap;
      }
      if (left < 8) {
        left = 8;
        top = Math.min(rect.bottom + gap, Math.max(8, viewportHeight - 430));
      }
      this.previewStyle = {
        left: `${Math.round(left)}px`,
        top: `${Math.round(top)}px`,
        width: `${Math.round(width)}px`,
      };
    },
    hidePreview(profession) {
      if (this.previewProfession?.id !== profession?.id) return;
      if (!this.previewInteractive) {
        this.resetPreview();
        return;
      }
      this.schedulePreviewClose();
    },
    enterPreview() {
      if (!this.previewInteractive) return;
      window.clearTimeout(this.previewHideTimer);
    },
    leavePreview() {
      this.schedulePreviewClose();
    },
    schedulePreviewClose() {
      window.clearTimeout(this.previewHideTimer);
      this.previewHideTimer = window.setTimeout(() => this.resetPreview(), 320);
    },
    resetPreview() {
      window.clearTimeout(this.previewTimer);
      window.clearTimeout(this.previewHideTimer);
      this.previewTimer = null;
      this.previewHideTimer = null;
      this.previewProfession = null;
      this.previewInteractive = false;
    },
    definitionLabels(items) {
      return (items || [])
        .map((item) => item.display || item.name || item.raw)
        .filter(Boolean);
    },
    pathLabels(items) {
      return (items || [])
        .map((item) => displayProfessionName(item.name))
        .filter(Boolean);
    },
    addPreviewSection(sections, label, values, limit) {
      if (!values.length) return;
      const visible = values.slice(0, limit);
      const remaining = values.length - visible.length;
      sections.push({
        label,
        value: `${visible.join(", ")}${remaining > 0 ? ` (+${remaining})` : ""}`,
      });
    },
  },
};
</script>
