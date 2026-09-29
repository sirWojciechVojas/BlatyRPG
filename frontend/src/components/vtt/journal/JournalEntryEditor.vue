<template>
  <form class="journal-editor" @submit.prevent="$emit('save', form)">
    <header class="journal-editor__header">
      <div class="journal-field journal-field--title">
        <label for="journal-entry-title">{{
          $t("vtt.journal.fields.title")
        }}</label>
        <input
          id="journal-entry-title"
          v-model.trim="form.title"
          type="text"
          maxlength="180"
          required
          autofocus
        />
      </div>
      <div class="journal-editor__actions">
        <button
          type="button"
          class="journal-button"
          :disabled="saving"
          @click="$emit('cancel')"
        >
          {{ $t("vtt.journal.actions.cancel") }}
        </button>
        <button
          type="submit"
          class="journal-button journal-button--primary"
          :disabled="saving || !form.title"
        >
          {{
            $t(
              saving ? "vtt.journal.states.saving" : "vtt.journal.actions.save",
            )
          }}
        </button>
      </div>
    </header>

    <p v-if="error" class="journal-form-error" role="alert">
      {{ $t(`vtt.journal.errors.${knownError}`) }}
    </p>

    <div class="journal-form-grid journal-form-grid--meta">
      <div class="journal-field">
        <label for="journal-entry-status">{{
          $t("vtt.journal.fields.status")
        }}</label>
        <select id="journal-entry-status" v-model="form.status">
          <option v-for="status in statuses" :key="status" :value="status">
            {{ $t(`vtt.journal.status.${status}`) }}
          </option>
        </select>
      </div>
      <div class="journal-field">
        <label for="journal-entry-visibility">{{
          $t("vtt.journal.fields.visibility")
        }}</label>
        <select id="journal-entry-visibility" v-model="form.visibility">
          <option
            v-for="visibility in visibilities"
            :key="visibility"
            :value="visibility"
          >
            {{ $t(`vtt.journal.visibility.${visibility}`) }}
          </option>
        </select>
      </div>
      <div class="journal-field">
        <label for="journal-entry-session">{{
          $t("vtt.journal.fields.session")
        }}</label>
        <input
          id="journal-entry-session"
          v-model.number="form.sessionNumber"
          type="number"
          min="0"
        />
      </div>
      <div class="journal-field">
        <label for="journal-entry-date">{{
          $t("vtt.journal.fields.date")
        }}</label>
        <input id="journal-entry-date" v-model="form.occurredOn" type="date" />
      </div>
      <div v-if="form.type === 'npc'" class="journal-field">
        <label for="journal-entry-trust">{{
          $t("vtt.journal.fields.trust")
        }}</label>
        <input
          id="journal-entry-trust"
          v-model.number="form.trustLevel"
          type="number"
          min="0"
          max="100"
        />
      </div>
    </div>

    <div class="journal-field">
      <label for="journal-entry-summary">{{
        $t("vtt.journal.fields.summary")
      }}</label>
      <textarea
        id="journal-entry-summary"
        v-model="form.summary"
        rows="2"
        maxlength="500"
      />
    </div>

    <div class="journal-form-grid journal-form-grid--sections">
      <section
        v-for="section in orderedSections"
        :key="section.key"
        class="journal-section-editor"
      >
        <div class="journal-section-editor__heading">
          <label :for="`journal-section-${section.key}`">
            {{ $t(`vtt.journal.sections.${section.key}`) }}
          </label>
          <select
            v-if="definition(section.key)?.sensitive"
            v-model="section.visibility"
            :aria-label="
              $t('vtt.journal.fields.sectionVisibility', {
                section: $t(`vtt.journal.sections.${section.key}`),
              })
            "
          >
            <option
              v-for="visibility in visibilities"
              :key="visibility"
              :value="visibility"
            >
              {{ $t(`vtt.journal.visibility.${visibility}`) }}
            </option>
          </select>
        </div>
        <textarea
          :id="`journal-section-${section.key}`"
          v-model="section.content"
          rows="4"
          maxlength="20000"
        />
      </section>
    </div>

    <section class="journal-editor-block">
      <h4>{{ $t("vtt.journal.fields.checklist") }}</h4>
      <div
        v-for="(item, index) in form.checklist"
        :key="item.id || `new-${index}`"
        class="journal-check-edit"
      >
        <input
          v-model="item.isCompleted"
          type="checkbox"
          :aria-label="
            $t('vtt.journal.actions.toggleChecklist', { label: item.label })
          "
        />
        <input v-model.trim="item.label" type="text" maxlength="300" required />
        <button
          type="button"
          class="journal-icon-button"
          :aria-label="$t('vtt.journal.actions.remove')"
          @click="form.checklist.splice(index, 1)"
        >
          ×
        </button>
      </div>
      <div class="journal-inline-add">
        <input
          v-model.trim="newChecklistLabel"
          type="text"
          maxlength="300"
          :placeholder="$t('vtt.journal.placeholders.checklist')"
          @keydown.enter.prevent="addChecklist"
        />
        <button type="button" class="journal-button" @click="addChecklist">
          {{ $t("vtt.journal.actions.add") }}
        </button>
      </div>
    </section>

    <section v-if="form.type === 'npc'" class="journal-editor-block">
      <div class="journal-editor-block__heading">
        <h4>{{ $t("vtt.journal.fields.encounters") }}</h4>
        <button type="button" class="journal-button" @click="addEncounter">
          {{ $t("vtt.journal.actions.addEncounter") }}
        </button>
      </div>
      <div
        v-for="(encounter, index) in form.encounters"
        :key="encounter.id || `encounter-${index}`"
        class="journal-encounter-edit"
      >
        <input
          v-model.number="encounter.sessionNumber"
          type="number"
          min="0"
          :aria-label="$t('vtt.journal.fields.session')"
          :placeholder="$t('vtt.journal.fields.session')"
        />
        <input
          v-model="encounter.occurredOn"
          type="date"
          :aria-label="$t('vtt.journal.fields.date')"
        />
        <textarea
          v-model="encounter.summary"
          rows="2"
          maxlength="10000"
          required
          :aria-label="$t('vtt.journal.fields.encounterSummary')"
        />
        <button
          type="button"
          class="journal-icon-button"
          :aria-label="$t('vtt.journal.actions.remove')"
          @click="form.encounters.splice(index, 1)"
        >
          ×
        </button>
      </div>
    </section>

    <section class="journal-editor-block">
      <h4>{{ $t("vtt.journal.fields.relations") }}</h4>
      <div
        v-for="(relation, index) in form.relations"
        :key="relation.id || `${relation.target?.id}-${index}`"
        class="journal-relation-edit"
      >
        <select
          v-model="relation.relationType"
          :aria-label="$t('vtt.journal.fields.relationType')"
        >
          <option v-for="type in relationTypes" :key="type" :value="type">
            {{ $t(`vtt.journal.relation.${type}`) }}
          </option>
        </select>
        <strong>{{ relation.target?.title || "—" }}</strong>
        <button
          type="button"
          class="journal-icon-button"
          :aria-label="$t('vtt.journal.actions.remove')"
          @click="form.relations.splice(index, 1)"
        >
          ×
        </button>
      </div>
      <div v-if="availableRelations.length" class="journal-inline-add">
        <select v-model="relationTarget">
          <option value="">
            {{ $t("vtt.journal.placeholders.relation") }}
          </option>
          <option
            v-for="item in availableRelations"
            :key="item.id"
            :value="item.id"
          >
            {{ item.title }}
          </option>
        </select>
        <button
          type="button"
          class="journal-button"
          :disabled="!relationTarget"
          @click="addRelation"
        >
          {{ $t("vtt.journal.actions.link") }}
        </button>
      </div>
    </section>
  </form>
</template>

<script>
const clone = (value) => JSON.parse(JSON.stringify(value));

export default {
  name: "JournalEntryEditor",
  props: {
    draft: { type: Object, required: true },
    sectionDefinitions: { type: Array, default: () => [] },
    availableRelations: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
    error: { type: Object, default: null },
  },
  emits: ["save", "cancel"],
  data() {
    return {
      form: clone(this.draft),
      newChecklistLabel: "",
      relationTarget: "",
      statuses: ["in_progress", "paused", "closed"],
      relationTypes: ["related", "quest", "npc", "chronicle"],
    };
  },
  computed: {
    visibilities() {
      const values = ["private", "campaign", "gm_player", "public_campaign"];
      return this.form.capabilities?.canEditPrivate === false
        ? values.filter((value) => value !== "private")
        : values;
    },
    orderedSections() {
      const byKey = new Map(
        this.form.sections.map((section) => [section.key, section]),
      );
      return this.sectionDefinitions.map(({ key, sensitive }) => {
        if (!byKey.has(key)) {
          const section = {
            key,
            content: "",
            visibility: sensitive ? "private" : null,
          };
          this.form.sections.push(section);
          byKey.set(key, section);
        }
        return byKey.get(key);
      });
    },
    knownError() {
      const known = [
        "validation_failed",
        "journal_conflict",
        "forbidden",
        "network_error",
      ];
      return known.includes(this.error?.code) ? this.error.code : "unknown";
    },
  },
  methods: {
    definition(key) {
      return this.sectionDefinitions.find((item) => item.key === key) || null;
    },
    addChecklist() {
      if (!this.newChecklistLabel) return;
      this.form.checklist.push({
        id: null,
        label: this.newChecklistLabel,
        isCompleted: false,
      });
      this.newChecklistLabel = "";
    },
    addEncounter() {
      this.form.encounters.push({
        id: null,
        sessionNumber: null,
        occurredOn: null,
        summary: "",
      });
    },
    addRelation() {
      const target = this.availableRelations.find(
        (item) => Number(item.id) === Number(this.relationTarget),
      );
      if (!target) return;
      this.form.relations.push({
        id: null,
        relationType: target.type || "related",
        target: clone(target),
      });
      this.relationTarget = "";
    },
  },
};
</script>
