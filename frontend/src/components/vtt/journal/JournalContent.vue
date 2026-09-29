<template>
  <section
    class="journal-content"
    :aria-label="$t('vtt.journal.title')"
    :aria-busy="loading || entryLoading || saving ? 'true' : 'false'"
  >
    <nav class="journal-tabs" :aria-label="$t('vtt.journal.tabsLabel')">
      <button
        v-for="tab in tabs"
        :key="tab.type"
        type="button"
        class="journal-tab"
        :class="{ 'journal-tab--active': activeType === tab.type }"
        :aria-current="activeType === tab.type ? 'page' : undefined"
        @click="activeType = tab.type"
      >
        {{ $t(tab.labelKey) }}
      </button>
    </nav>

    <div v-if="!resolvedCharacterId" class="journal-state" role="status">
      <strong>{{ $t("vtt.journal.noCharacterTitle") }}</strong>
      <span>{{ $t("vtt.journal.noCharacterBody") }}</span>
    </div>

    <div v-else class="journal-frame">
      <div class="journal-frame__surface">
        <aside
          class="journal-index"
          :aria-label="$t('vtt.journal.entriesLabel')"
        >
          <div class="journal-index__toolbar">
            <button
              type="button"
              class="journal-button journal-button--primary"
              :disabled="!capabilities.canCreate || saving"
              @click="startCreate"
            >
              <span aria-hidden="true">＋</span>
              {{ $t("vtt.journal.actions.new") }}
            </button>
            <button
              type="button"
              class="journal-button journal-button--quiet"
              :aria-pressed="String(showArchived)"
              @click="showArchived = !showArchived"
            >
              {{
                $t(
                  showArchived
                    ? "vtt.journal.actions.active"
                    : "vtt.journal.actions.archive",
                )
              }}
            </button>
          </div>

          <div
            v-if="loading"
            class="journal-state journal-state--compact"
            role="status"
          >
            {{ $t("vtt.journal.states.loading") }}
          </div>
          <div
            v-else-if="listError"
            class="journal-state journal-state--compact journal-state--error"
            role="alert"
          >
            <span>{{ errorLabel(listError) }}</span>
            <button
              type="button"
              class="journal-link-button"
              @click="loadEntries"
            >
              {{ $t("vtt.journal.actions.retry") }}
            </button>
          </div>
          <div
            v-else-if="!entries.length"
            class="journal-state journal-state--compact"
            role="status"
          >
            {{
              $t(
                showArchived
                  ? "vtt.journal.states.noArchived"
                  : "vtt.journal.states.empty",
              )
            }}
          </div>
          <div v-else class="journal-index__groups">
            <section v-for="group in groupedEntries" :key="group.status">
              <h3>{{ $t(`vtt.journal.status.${group.status}`) }}</h3>
              <button
                v-for="item in group.items"
                :key="item.id"
                type="button"
                class="journal-entry-link"
                :class="{
                  'journal-entry-link--active': selectedId === item.id,
                }"
                @click="selectEntry(item.id)"
              >
                <strong>{{ item.title }}</strong>
                <small>{{ entryStatusLine(item) }}</small>
              </button>
            </section>
          </div>
        </aside>

        <main class="journal-document">
          <div v-if="entryLoading" class="journal-state" role="status">
            {{ $t("vtt.journal.states.entryLoading") }}
          </div>
          <div
            v-else-if="entryError"
            class="journal-state journal-state--error"
            role="alert"
          >
            <strong>{{ $t("vtt.journal.states.loadFailed") }}</strong>
            <span>{{ errorLabel(entryError) }}</span>
            <button
              type="button"
              class="journal-link-button"
              @click="reloadEntry"
            >
              {{ $t("vtt.journal.actions.retry") }}
            </button>
          </div>
          <JournalEntryEditor
            v-else-if="editing && draft"
            :draft="draft"
            :section-definitions="draftSectionDefinitions"
            :available-relations="availableRelations"
            :saving="saving"
            :error="saveError"
            @save="saveEntry"
            @cancel="cancelEditing"
          />
          <JournalEntryView
            v-else-if="entry"
            :entry="entry"
            :busy="saving"
            @edit="startEdit"
            @archive="toggleArchive"
            @delete="confirmDelete"
            @toggle-checklist="toggleChecklist"
          />
          <div v-else class="journal-state" role="status">
            <strong>{{ $t("vtt.journal.states.selectTitle") }}</strong>
            <span>{{ $t("vtt.journal.states.selectBody") }}</span>
          </div>
        </main>
      </div>
    </div>

    <footer class="journal-footer">
      <span>{{ characterName }}</span>
      <span>{{ $t("vtt.journal.entryCount", { count: entries.length }) }}</span>
      <span v-if="saving" role="status">{{
        $t("vtt.journal.states.saving")
      }}</span>
      <span v-else>{{ $t("vtt.journal.footerPrivacy") }}</span>
    </footer>
  </section>
</template>

<script>
import { journalApiClient } from "@/lib/journal/journalApiClient";
import JournalEntryEditor from "./JournalEntryEditor.vue";
import JournalEntryView from "./JournalEntryView.vue";

const TABS = Object.freeze([
  { type: "quest", labelKey: "vtt.journal.tabs.quests" },
  { type: "npc", labelKey: "vtt.journal.tabs.npcs" },
  { type: "motivation", labelKey: "vtt.journal.tabs.motivations" },
  { type: "chronicle", labelKey: "vtt.journal.tabs.chronicle" },
]);

const SECTIONS = Object.freeze({
  quest: [
    "facts",
    "team_decisions",
    "clues_events",
    "stakes",
    "personal_perspective",
    "suspicions",
    "emotions",
    "motivation",
  ],
  npc: [
    "player_knowledge",
    "appearance_behavior",
    "subjective_impression",
    "suspicions",
    "obligations",
    "next_conversation",
  ],
  motivation: [
    "hero_goal",
    "motivation_source",
    "personal_meaning",
    "boundary",
    "inner_conflict",
    "decision_impact",
    "change_history",
  ],
  chronicle: [
    "chronicle_event",
    "team_decisions",
    "personal_perspective",
    "emotions",
    "suspicions",
  ],
});

const SENSITIVE_SECTIONS = new Set([
  "personal_perspective",
  "suspicions",
  "emotions",
  "motivation",
  "subjective_impression",
  "next_conversation",
  "personal_meaning",
  "boundary",
  "inner_conflict",
  "notes",
]);

const clone = (value) => JSON.parse(JSON.stringify(value));

export default {
  name: "JournalContent",
  components: { JournalEntryEditor, JournalEntryView },
  props: {
    campaignId: { type: [Number, String], required: true },
    characterId: { type: [Number, String], default: null },
    characters: { type: Array, default: () => [] },
  },
  data: () => ({
    tabs: TABS,
    activeType: "quest",
    showArchived: false,
    entries: [],
    relationEntries: [],
    character: null,
    capabilities: { canCreate: false, canManageCampaign: false },
    selectedId: null,
    entry: null,
    draft: null,
    editing: false,
    creating: false,
    loading: false,
    entryLoading: false,
    saving: false,
    listError: null,
    entryError: null,
    saveError: null,
    listRequest: 0,
    entryRequest: 0,
  }),
  computed: {
    resolvedCharacterId() {
      const id = Number(this.characterId);
      return Number.isInteger(id) && id > 0 ? id : null;
    },
    characterName() {
      if (this.character?.name) return this.character.name;
      return (
        this.characters.find(
          (character) => Number(character.id) === this.resolvedCharacterId,
        )?.name || this.$t("vtt.journal.noCharacterShort")
      );
    },
    loadKey() {
      return [
        this.campaignId,
        this.resolvedCharacterId || "none",
        this.activeType,
        this.showArchived ? "archive" : "active",
      ].join(":");
    },
    groupedEntries() {
      return ["in_progress", "paused", "closed"]
        .map((status) => ({
          status,
          items: this.entries.filter((entry) => entry.status === status),
        }))
        .filter((group) => group.items.length);
    },
    draftSectionDefinitions() {
      const existing = new Set(
        (this.draft?.sections || []).map((section) => section.key),
      );
      return (SECTIONS[this.draft?.type] || [])
        .map((key) => ({
          key,
          labelKey: `vtt.journal.sections.${key}`,
          sensitive: SENSITIVE_SECTIONS.has(key),
        }))
        .filter(
          ({ key, sensitive }) =>
            !sensitive ||
            this.draft?.capabilities?.canEditPrivate !== false ||
            existing.has(key),
        );
    },
    availableRelations() {
      const excluded = new Set([
        Number(this.draft?.id),
        ...(this.draft?.relations || []).map((relation) =>
          Number(relation.target?.id || relation.targetEntryId),
        ),
      ]);
      return this.relationEntries.filter(
        (item) => !excluded.has(Number(item.id)),
      );
    },
  },
  watch: {
    loadKey: {
      immediate: true,
      handler() {
        this.loadEntries();
      },
    },
  },
  beforeUnmount() {
    this.listRequest += 1;
    this.entryRequest += 1;
  },
  methods: {
    async loadEntries() {
      const characterId = this.resolvedCharacterId;
      const sequence = ++this.listRequest;
      this.entryRequest += 1;
      this.entries = [];
      this.relationEntries = [];
      this.entry = null;
      this.selectedId = null;
      this.editing = false;
      this.draft = null;
      this.listError = null;
      if (!characterId) return;
      this.loading = true;
      try {
        const [result, relationCatalog] = await Promise.all([
          journalApiClient.list(this.campaignId, characterId, {
            type: this.activeType,
            archived: this.showArchived,
          }),
          journalApiClient.list(this.campaignId, characterId, {
            archived: false,
          }),
        ]);
        if (sequence !== this.listRequest) return;
        this.entries = result.items;
        this.relationEntries = relationCatalog.items;
        this.character = result.character;
        this.capabilities = result.capabilities;
        if (this.entries.length) await this.selectEntry(this.entries[0].id);
      } catch (error) {
        if (sequence === this.listRequest) this.listError = error;
      } finally {
        if (sequence === this.listRequest) this.loading = false;
      }
    },
    async selectEntry(id) {
      if (this.saving || (this.editing && !this.confirmDiscard())) return;
      this.editing = false;
      this.draft = null;
      this.selectedId = Number(id);
      const sequence = ++this.entryRequest;
      this.entryLoading = true;
      this.entryError = null;
      try {
        const result = await journalApiClient.get(this.campaignId, id);
        if (sequence !== this.entryRequest) return;
        this.entry = result.entry;
        this.character = result.character || this.character;
      } catch (error) {
        if (sequence === this.entryRequest) this.entryError = error;
      } finally {
        if (sequence === this.entryRequest) this.entryLoading = false;
      }
    },
    reloadEntry() {
      if (this.selectedId) this.selectEntry(this.selectedId);
    },
    startCreate() {
      if (
        !this.capabilities.canCreate ||
        (this.editing && !this.confirmDiscard())
      )
        return;
      this.creating = true;
      this.editing = true;
      this.entry = null;
      this.selectedId = null;
      this.saveError = null;
      this.draft = this.emptyDraft(this.activeType);
    },
    startEdit() {
      if (!this.entry?.capabilities?.canEdit) return;
      this.creating = false;
      this.editing = true;
      this.saveError = null;
      this.draft = this.entryDraft(this.entry);
    },
    cancelEditing() {
      this.saveError = null;
      this.editing = false;
      this.draft = null;
      if (this.creating) {
        this.creating = false;
        if (this.entries.length) this.selectEntry(this.entries[0].id);
      }
    },
    async saveEntry(form = null) {
      const workingDraft = form || this.draft;
      if (!workingDraft || this.saving) return;
      this.saving = true;
      this.saveError = null;
      try {
        const payload = this.writePayload(workingDraft);
        const result = this.creating
          ? await journalApiClient.create(this.campaignId, payload)
          : await journalApiClient.update(
              this.campaignId,
              workingDraft.id,
              payload,
            );
        this.entry = result.entry;
        this.selectedId = result.entry.id;
        this.creating = false;
        this.editing = false;
        this.draft = null;
        await this.refreshEntries(result.entry.id);
      } catch (error) {
        this.saveError = error;
      } finally {
        this.saving = false;
      }
    },
    async refreshEntries(reselectId = null) {
      const [result, relationCatalog] = await Promise.all([
        journalApiClient.list(this.campaignId, this.resolvedCharacterId, {
          type: this.activeType,
          archived: this.showArchived,
        }),
        journalApiClient.list(this.campaignId, this.resolvedCharacterId, {
          archived: false,
        }),
      ]);
      this.entries = result.items;
      this.relationEntries = relationCatalog.items;
      this.capabilities = result.capabilities;
      if (reselectId) {
        this.selectedId = Number(reselectId);
        const detail = await journalApiClient.get(this.campaignId, reselectId);
        this.entry = detail.entry;
      }
    },
    async toggleArchive() {
      if (!this.entry?.capabilities?.canEdit || this.saving) return;
      this.saving = true;
      this.saveError = null;
      try {
        await journalApiClient.archive(
          this.campaignId,
          this.entry.id,
          this.entry.revision,
          !this.entry.archived,
        );
        await this.loadEntries();
      } catch (error) {
        this.entryError = error;
      } finally {
        this.saving = false;
      }
    },
    async confirmDelete() {
      if (
        !this.entry?.capabilities?.canEdit ||
        this.saving ||
        !window.confirm(this.$t("vtt.journal.actions.deleteConfirm"))
      ) {
        return;
      }
      this.saving = true;
      try {
        await journalApiClient.remove(
          this.campaignId,
          this.entry.id,
          this.entry.revision,
        );
        await this.loadEntries();
      } catch (error) {
        this.entryError = error;
      } finally {
        this.saving = false;
      }
    },
    async toggleChecklist(item) {
      if (!this.entry?.capabilities?.canEdit || this.saving) return;
      this.saving = true;
      try {
        const result = await journalApiClient.updateChecklistItem(
          this.campaignId,
          this.entry.id,
          item.id,
          { isCompleted: !item.isCompleted },
        );
        this.entry = result.entry;
        this.patchSummary(result.entry);
      } catch (error) {
        this.entryError = error;
      } finally {
        this.saving = false;
      }
    },
    patchSummary(entry) {
      const index = this.entries.findIndex((item) => item.id === entry.id);
      if (index >= 0)
        this.entries.splice(index, 1, { ...this.entries[index], ...entry });
    },
    addDraftChecklist(label) {
      const value = String(label || "").trim();
      if (value)
        this.draft.checklist.push({
          id: null,
          label: value,
          isCompleted: false,
        });
    },
    removeDraftChecklist(index) {
      this.draft.checklist.splice(index, 1);
    },
    addDraftEncounter() {
      this.draft.encounters.push({
        id: null,
        sessionNumber: null,
        occurredOn: null,
        summary: "",
      });
    },
    removeDraftEncounter(index) {
      this.draft.encounters.splice(index, 1);
    },
    addDraftRelation(targetId) {
      const target = this.relationEntries.find(
        (item) => item.id === Number(targetId),
      );
      if (target)
        this.draft.relations.push({
          relationType: target.type,
          target: clone(target),
        });
    },
    removeDraftRelation(index) {
      this.draft.relations.splice(index, 1);
    },
    emptyDraft(type) {
      return {
        id: null,
        characterId: this.resolvedCharacterId,
        type,
        title: "",
        status: "in_progress",
        summary: "",
        sessionNumber: null,
        occurredOn: null,
        visibility: "private",
        trustLevel: type === "npc" ? 50 : null,
        revision: 1,
        capabilities: { canEdit: true, canEditPrivate: true },
        sections: (SECTIONS[type] || []).map((key) => ({
          key,
          content: "",
          visibility: SENSITIVE_SECTIONS.has(key) ? "private" : null,
        })),
        checklist: [],
        encounters: [],
        relations: [],
      };
    },
    entryDraft(entry) {
      const draft = clone(entry);
      const byKey = new Map(
        draft.sections.map((section) => [section.key, section]),
      );
      for (const key of SECTIONS[draft.type] || []) {
        if (
          SENSITIVE_SECTIONS.has(key) &&
          draft.capabilities?.canEditPrivate === false
        ) {
          continue;
        }
        if (!byKey.has(key)) {
          draft.sections.push({
            key,
            content: "",
            visibility: SENSITIVE_SECTIONS.has(key) ? "private" : null,
          });
        }
      }
      return draft;
    },
    writePayload(draft) {
      return {
        characterId: draft.characterId,
        type: draft.type,
        title: String(draft.title || "").trim(),
        status: draft.status,
        summary: String(draft.summary || "").trim(),
        sessionNumber: draft.sessionNumber === "" ? null : draft.sessionNumber,
        occurredOn: draft.occurredOn || null,
        visibility: draft.visibility,
        trustLevel: draft.type === "npc" ? draft.trustLevel : null,
        revision: draft.revision,
        sections: draft.sections.map(({ key, content, visibility }) => ({
          key,
          content,
          visibility,
        })),
        checklist: draft.checklist.map(({ id, label, isCompleted }) => ({
          id,
          label,
          isCompleted,
        })),
        encounters: draft.encounters.map(
          ({ id, sessionNumber, occurredOn, summary }) => ({
            id,
            sessionNumber: sessionNumber === "" ? null : sessionNumber,
            occurredOn: occurredOn || null,
            summary,
          }),
        ),
        relations: draft.relations.map((relation) => ({
          targetEntryId: Number(relation.target?.id || relation.targetEntryId),
          relationType: relation.relationType || "related",
        })),
      };
    },
    entryStatusLine(item) {
      const parts = [];
      if (item.sessionNumber) {
        parts.push(
          this.$t("vtt.journal.sessionShort", { number: item.sessionNumber }),
        );
      }
      parts.push(this.$t(`vtt.journal.visibility.${item.visibility}`));
      return parts.join(" · ");
    },
    confirmDiscard() {
      return window.confirm(this.$t("vtt.journal.actions.discardConfirm"));
    },
    errorLabel(error) {
      const code = String(error?.code || "unknown");
      const key = `vtt.journal.errors.${code}`;
      const translated = this.$t(key);
      return translated === key
        ? this.$t("vtt.journal.errors.unknown")
        : translated;
    },
  },
};
</script>

<style src="./journal.css"></style>
