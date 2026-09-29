<template>
  <section
    class="compendium-knowledge"
    :aria-busy="loading || saving ? 'true' : 'false'"
  >
    <header class="compendium-knowledge__summary">
      <strong>
        {{
          $t("vtt.table.compendium.bestiaryKnowledgeForEntry", {
            entry: entry.title,
          })
        }}
      </strong>
      <div class="compendium-knowledge__counts" aria-live="polite">
        <span>
          {{ $t("vtt.table.compendium.bestiaryLevelUnknownShort") }}
          <b>{{ levelCounts.unknown }}</b>
        </span>
        <span>
          {{ $t("vtt.table.compendium.bestiaryLevelSummaryShort") }}
          <b>{{ levelCounts.summary }}</b>
        </span>
        <span>
          {{ $t("vtt.table.compendium.bestiaryLevelFullShort") }}
          <b>{{ levelCounts.full }}</b>
        </span>
      </div>
    </header>

    <div v-if="assignments.length" class="compendium-knowledge__bulk">
      <span>{{ $t("vtt.table.compendium.bestiaryRevealAll") }}</span>
      <button
        type="button"
        :disabled="loading || saving"
        @click="revealAll('summary')"
      >
        {{ $t("vtt.table.compendium.bestiaryLevelSummaryShort") }}
      </button>
      <button
        type="button"
        :disabled="loading || saving"
        @click="revealAll('full')"
      >
        {{ $t("vtt.table.compendium.bestiaryLevelFullShort") }}
      </button>
      <span class="compendium-knowledge__spacer" />
      <button
        type="button"
        class="compendium-knowledge__hide-all"
        :disabled="loading || saving"
        @click="hideAll"
      >
        {{ $t("vtt.table.compendium.bestiaryHideAll") }}
      </button>
    </div>

    <p v-if="loading" class="compendium-knowledge__state" role="status">
      {{ $t("vtt.table.compendium.bestiaryAssignmentsLoading") }}
    </p>
    <p
      v-else-if="errorMessage && !assignments.length"
      class="compendium-knowledge__state compendium-knowledge__state--error"
      role="alert"
    >
      {{ errorMessage }}
    </p>
    <p v-else-if="!assignments.length" class="compendium-knowledge__state">
      {{ $t("vtt.table.compendium.noBestiaryCharacters") }}
    </p>

    <div
      v-if="!loading && assignments.length"
      class="compendium-knowledge__table"
      role="table"
    >
      <div
        class="compendium-knowledge__row compendium-knowledge__row--head"
        role="row"
      >
        <span role="columnheader">
          {{ $t("vtt.table.compendium.bestiaryHero") }}
        </span>
        <span
          v-for="level in knowledgeLevels"
          :key="level.value"
          role="columnheader"
          :title="level.label"
        >
          {{ level.shortLabel }}
        </span>
      </div>
      <div
        v-for="assignment in assignments"
        :key="assignment.characterId"
        class="compendium-knowledge__row"
        role="row"
      >
        <strong role="rowheader">
          {{ assignment.characterName }}
          <span
            v-if="isChanged(assignment.characterId)"
            :aria-label="$t('vtt.table.compendium.bestiaryUnsavedChange')"
            >•</span
          >
        </strong>
        <label
          v-for="level in knowledgeLevels"
          :key="level.value"
          class="compendium-knowledge__radio"
          :class="{
            active: draftLevel(assignment.characterId) === level.value,
          }"
          role="cell"
          :title="level.label"
        >
          <input
            type="radio"
            :name="`knowledge-${entry.id}-${assignment.characterId}`"
            :value="level.value"
            :checked="draftLevel(assignment.characterId) === level.value"
            :disabled="saving"
            :aria-label="
              $t('vtt.table.compendium.bestiaryLevelFor', {
                character: assignment.characterName,
              }) +
              ': ' +
              level.label
            "
            @change="setLevel(assignment.characterId, level.value)"
          />
        </label>
      </div>
    </div>

    <p
      v-if="errorMessage && assignments.length"
      class="compendium-knowledge__state compendium-knowledge__state--error"
      role="alert"
    >
      {{ errorMessage }}
    </p>

    <section
      v-if="entry.sourceBacked"
      class="compendium-knowledge__source-selection"
    >
      <header>
        <div>
          <strong>{{
            $t("vtt.table.compendium.bestiarySourceSelection")
          }}</strong>
          <small>{{
            $t("vtt.table.compendium.bestiarySourceSelectionHint")
          }}</small>
        </div>
        <span>
          {{
            $t("vtt.table.compendium.bestiarySelectedSections", {
              selected: draftSectionKeys.length,
              total: sourceSections.length,
            })
          }}
        </span>
        <button
          type="button"
          :disabled="saving || !sourceSections.length"
          @click="selectAllSections"
        >
          {{ $t("vtt.table.compendium.bestiarySelectAllSections") }}
        </button>
        <button
          type="button"
          :disabled="saving || !draftSectionKeys.length"
          @click="clearSections"
        >
          {{ $t("vtt.table.compendium.bestiaryClearSections") }}
        </button>
      </header>
      <p v-if="!sourceSections.length" class="compendium-knowledge__state">
        {{ $t("vtt.table.compendium.bestiaryNoSourceSections") }}
      </p>
      <div v-else class="compendium-knowledge__sections">
        <label v-for="section in sourceSections" :key="section.id">
          <input
            type="checkbox"
            :checked="sectionSelected(section.id)"
            :disabled="saving"
            @change="toggleSection(section.id)"
          />
          <span>{{ section.title }}</span>
        </label>
      </div>
      <p
        v-if="levelCounts.full && !draftSectionKeys.length"
        class="compendium-knowledge__source-warning"
      >
        {{ $t("vtt.table.compendium.bestiaryNoSectionsForFull") }}
      </p>
    </section>

    <footer
      v-if="assignments.length || entry.sourceBacked"
      class="compendium-knowledge__footer"
    >
      <span aria-live="polite">
        {{
          saveMessage ||
          (dirtyCount
            ? $t("vtt.table.compendium.bestiaryUnsavedCount", {
                count: dirtyCount,
              })
            : $t("vtt.table.compendium.bestiaryNoUnsaved"))
        }}
      </span>
      <button type="button" :disabled="!dirtyCount || saving" @click="discard">
        {{ $t("vtt.table.compendium.cancel") }}
      </button>
      <button
        type="button"
        class="compendium-knowledge__save"
        :disabled="!dirtyCount || saving"
        @click="save"
      >
        {{
          saving
            ? $t("vtt.table.compendium.saving")
            : $t("vtt.table.compendium.bestiarySave", {
                count: dirtyCount,
              })
        }}
      </button>
    </footer>

    <p v-if="assignments.length" class="compendium-knowledge__legend">
      {{ $t("vtt.table.compendium.bestiaryKnowledgeLegend") }}
    </p>

    <section v-if="assignments.length" class="compendium-knowledge__preview">
      <header>
        <strong>{{ $t("vtt.table.compendium.bestiaryPreview") }}</strong>
        <label>
          <span class="compendium-knowledge__sr">
            {{ $t("vtt.table.compendium.bestiaryPreviewCharacter") }}
          </span>
          <select v-model="previewCharacterId">
            <option
              v-for="assignment in assignments"
              :key="assignment.characterId"
              :value="String(assignment.characterId)"
            >
              {{ assignment.characterName }}
            </option>
          </select>
        </label>
        <small v-if="previewAssignment">
          {{ previewAssignment.characterName }} ·
          {{ knowledgeLabel(previewLevel) }}
          <template v-if="isChanged(previewAssignment.characterId)">
            · {{ $t("vtt.table.compendium.bestiaryPreviewDraft") }}
          </template>
        </small>
      </header>

      <div
        v-if="previewLevel === 'unknown'"
        class="compendium-knowledge__preview-hidden"
      >
        {{ $t("vtt.table.compendium.bestiaryPreviewHidden") }}
      </div>
      <article
        v-else-if="previewLevel === 'summary'"
        class="compendium-knowledge__preview-summary"
      >
        <h3>{{ entry.title }}</h3>
        <p>
          {{ entry.excerpt || $t("vtt.bestiary.summaryUnavailable") }}
        </p>
      </article>
      <article v-else class="compendium-knowledge__preview-full">
        <h3>{{ entry.title }}</h3>
        <p v-if="entry.excerpt">{{ entry.excerpt }}</p>
        <CompendiumSourceDocument
          v-if="entry.sourceBacked && selectedSourceHtml"
          :html="selectedSourceHtml"
          :links="entry.wikiLinks || []"
          @navigate="$emit('navigate', $event)"
        />
        <p
          v-else-if="entry.sourceBacked"
          class="compendium-entry__empty-section"
        >
          {{ $t("vtt.table.compendium.bestiaryNoSelectedContent") }}
        </p>
        <CompendiumDocument
          v-else-if="hasPublicRichContent"
          :content="entry.publicContent"
          :assets="publicAssets"
          :mentions="entry.mentions || []"
          :campaign-id="campaignId"
          @navigate="$emit('navigate', $event)"
        />
        <p
          v-else-if="entry.playerDescription"
          class="compendium-entry__plain-text"
        >
          {{ entry.playerDescription }}
        </p>
        <p v-else class="compendium-entry__empty-section">
          {{ $t("vtt.table.compendium.noPlayerDescription") }}
        </p>
      </article>
    </section>
  </section>
</template>

<script>
import { bestiaryApiClient } from "@/lib/bestiary/bestiaryApiClient";
import { selectSourceSections } from "@/lib/compendium/sourceSectionSelection";
import CompendiumDocument from "./CompendiumDocument.vue";
import CompendiumSourceDocument from "./CompendiumSourceDocument.vue";

const LEVEL_RANK = { unknown: 0, summary: 1, full: 2 };

const hasTextualContent = (node) => {
  if (!node || typeof node !== "object") return false;
  if (["text", "image", "attachment", "compendiumMention"].includes(node.type))
    return true;
  return Array.isArray(node.content) && node.content.some(hasTextualContent);
};

const normalizedLevel = (assignment) => {
  const level = assignment?.level || assignment?.state || "unknown";
  return (
    { hidden: "unknown", catalogued: "summary", encountered: "full" }[level] ||
    level
  );
};

export default {
  name: "CompendiumBestiaryKnowledge",
  components: { CompendiumDocument, CompendiumSourceDocument },
  props: {
    campaignId: { type: [Number, String], required: true },
    entry: { type: Object, required: true },
  },
  emits: ["navigate", "dirty-count", "assignment-count", "section-keys"],
  data: () => ({
    assignments: [],
    savedLevels: {},
    draftLevels: {},
    savedSectionKeys: [],
    draftSectionKeys: [],
    previewCharacterId: "",
    loading: false,
    saving: false,
    errorMessage: "",
    saveMessage: "",
    requestSequence: 0,
  }),
  computed: {
    knowledgeLevels() {
      return [
        {
          value: "unknown",
          label: this.$t("vtt.table.compendium.bestiaryLevelUnknown"),
          shortLabel: this.$t("vtt.table.compendium.bestiaryLevelUnknownShort"),
        },
        {
          value: "summary",
          label: this.$t("vtt.table.compendium.bestiaryLevelSummary"),
          shortLabel: this.$t("vtt.table.compendium.bestiaryLevelSummaryShort"),
        },
        {
          value: "full",
          label: this.$t("vtt.table.compendium.bestiaryLevelFull"),
          shortLabel: this.$t("vtt.table.compendium.bestiaryLevelFullShort"),
        },
      ];
    },
    dirtyAssignments() {
      return this.assignments.filter(
        (assignment) =>
          this.draftLevel(assignment.characterId) !==
          this.savedLevel(assignment.characterId),
      );
    },
    dirtyCount() {
      return this.dirtyAssignments.length + (this.sectionsChanged ? 1 : 0);
    },
    sectionsChanged() {
      return (
        JSON.stringify(this.draftSectionKeys) !==
        JSON.stringify(this.savedSectionKeys)
      );
    },
    levelCounts() {
      return this.assignments.reduce(
        (counts, assignment) => {
          counts[this.draftLevel(assignment.characterId)] += 1;
          return counts;
        },
        { unknown: 0, summary: 0, full: 0 },
      );
    },
    previewAssignment() {
      return (
        this.assignments.find(
          (assignment) =>
            Number(assignment.characterId) === Number(this.previewCharacterId),
        ) ||
        this.assignments[0] ||
        null
      );
    },
    previewLevel() {
      return this.previewAssignment
        ? this.draftLevel(this.previewAssignment.characterId)
        : "unknown";
    },
    hasPublicRichContent() {
      return hasTextualContent(this.entry.publicContent);
    },
    publicAssets() {
      return (this.entry.assets || []).filter(
        (asset) => asset.audience === "public",
      );
    },
    sourceSections() {
      return (this.entry.sections || [])
        .filter((section) => section?.id && section?.title)
        .map((section) => ({
          id: String(section.id),
          title: String(section.title),
        }));
    },
    selectedSourceHtml() {
      return selectSourceSections(
        String(this.entry.sourceHtml || ""),
        this.draftSectionKeys,
      );
    },
  },
  watch: {
    campaignId: "resetAndLoad",
    entry: {
      handler: "resetAndLoad",
      deep: false,
    },
    dirtyCount(value) {
      this.$emit("dirty-count", value);
    },
    draftSectionKeys(value) {
      this.$emit("section-keys", [...value]);
    },
  },
  mounted() {
    this.load();
  },
  beforeUnmount() {
    this.requestSequence += 1;
    this.$emit("dirty-count", 0);
    this.$emit("assignment-count", 0);
    this.$emit("section-keys", []);
  },
  methods: {
    resetAndLoad() {
      this.requestSequence += 1;
      this.assignments = [];
      this.savedLevels = {};
      this.draftLevels = {};
      this.savedSectionKeys = [];
      this.draftSectionKeys = [];
      this.previewCharacterId = "";
      this.errorMessage = "";
      this.saveMessage = "";
      this.$emit("dirty-count", 0);
      this.$emit("assignment-count", 0);
      this.$emit("section-keys", []);
      this.load();
    },
    async load() {
      if (!Number(this.campaignId) || !Number(this.entry?.id)) return;
      const entryId = Number(this.entry.id);
      const sequence = ++this.requestSequence;
      this.loading = true;
      this.errorMessage = "";
      try {
        const response = await bestiaryApiClient.assignments(
          this.campaignId,
          entryId,
        );
        if (
          sequence !== this.requestSequence ||
          Number(this.entry.id) !== entryId
        )
          return;
        this.assignments = (response.assignments || []).map((assignment) => ({
          ...assignment,
          level: normalizedLevel(assignment),
        }));
        this.savedLevels = Object.fromEntries(
          this.assignments.map((assignment) => [
            Number(assignment.characterId),
            assignment.level,
          ]),
        );
        this.draftLevels = { ...this.savedLevels };
        const validSectionIds = new Set(
          this.sourceSections.map((section) => section.id),
        );
        this.savedSectionKeys = (response.sectionKeys || [])
          .map(String)
          .filter((key) => validSectionIds.has(key));
        this.draftSectionKeys = [...this.savedSectionKeys];
        this.$emit("section-keys", [...this.draftSectionKeys]);
        this.previewCharacterId = this.assignments[0]
          ? String(this.assignments[0].characterId)
          : "";
        this.$emit("assignment-count", this.assignments.length);
      } catch (error) {
        if (sequence === this.requestSequence) {
          this.errorMessage =
            error?.payload?.message || error?.message || "Error";
        }
      } finally {
        if (sequence === this.requestSequence) this.loading = false;
      }
    },
    savedLevel(characterId) {
      return this.savedLevels[Number(characterId)] || "unknown";
    },
    draftLevel(characterId) {
      return this.draftLevels[Number(characterId)] || "unknown";
    },
    isChanged(characterId) {
      return this.draftLevel(characterId) !== this.savedLevel(characterId);
    },
    setLevel(characterId, level) {
      this.draftLevels = {
        ...this.draftLevels,
        [Number(characterId)]: level,
      };
      this.saveMessage = "";
    },
    revealAll(level) {
      const targetRank = LEVEL_RANK[level];
      this.draftLevels = Object.fromEntries(
        this.assignments.map((assignment) => {
          const current = this.draftLevel(assignment.characterId);
          return [
            Number(assignment.characterId),
            LEVEL_RANK[current] >= targetRank ? current : level,
          ];
        }),
      );
      this.saveMessage = "";
    },
    hideAll() {
      this.draftLevels = Object.fromEntries(
        this.assignments.map((assignment) => [
          Number(assignment.characterId),
          "unknown",
        ]),
      );
      this.saveMessage = "";
    },
    discard() {
      this.draftLevels = { ...this.savedLevels };
      this.draftSectionKeys = [...this.savedSectionKeys];
      this.errorMessage = "";
      this.saveMessage = "";
    },
    knowledgeLabel(level) {
      return (
        this.knowledgeLevels.find((option) => option.value === level)?.label ||
        level
      );
    },
    sectionSelected(sectionId) {
      return this.draftSectionKeys.includes(String(sectionId));
    },
    toggleSection(sectionId) {
      const key = String(sectionId);
      this.draftSectionKeys = this.sectionSelected(key)
        ? this.draftSectionKeys.filter((value) => value !== key)
        : this.sourceSections
            .map((section) => section.id)
            .filter(
              (value) => value === key || this.draftSectionKeys.includes(value),
            );
      this.saveMessage = "";
    },
    selectAllSections() {
      this.draftSectionKeys = this.sourceSections.map((section) => section.id);
      this.saveMessage = "";
    },
    clearSections() {
      this.draftSectionKeys = [];
      this.saveMessage = "";
    },
    async save() {
      if (!this.dirtyCount || this.saving) return;
      const entryId = Number(this.entry.id);
      const changes = this.dirtyAssignments.map((assignment) => ({
        characterId: Number(assignment.characterId),
        level: this.draftLevel(assignment.characterId),
      }));
      const changeCount = this.dirtyCount;
      const sectionKeysChanged = this.sectionsChanged;
      this.saving = true;
      this.errorMessage = "";
      this.saveMessage = "";
      try {
        const response = await bestiaryApiClient.saveAssignments(
          this.campaignId,
          entryId,
          changes,
          sectionKeysChanged ? this.draftSectionKeys : undefined,
        );
        if (Number(this.entry.id) !== entryId) return;
        const updates = new Map(
          (response.assignments || []).map((assignment) => [
            Number(assignment.characterId),
            { ...assignment, level: normalizedLevel(assignment) },
          ]),
        );
        this.assignments = this.assignments.map(
          (assignment) =>
            updates.get(Number(assignment.characterId)) || assignment,
        );
        this.savedLevels = { ...this.draftLevels };
        if (sectionKeysChanged) {
          this.savedSectionKeys = (response.sectionKeys || []).map(String);
          this.draftSectionKeys = [...this.savedSectionKeys];
        }
        this.saveMessage = this.$t("vtt.table.compendium.bestiarySavedCount", {
          count: changeCount,
        });
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>
