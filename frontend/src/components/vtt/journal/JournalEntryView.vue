<template>
  <article class="journal-entry">
    <header class="journal-entry__header">
      <div>
        <p class="journal-entry__eyebrow">
          {{ $t(`vtt.journal.tabs.${tabKey}`) }}
        </p>
        <h3>{{ entry.title }}</h3>
        <p v-if="entry.summary" class="journal-entry__summary">
          {{ entry.summary }}
        </p>
      </div>
      <div v-if="entry.capabilities.canEdit" class="journal-entry__actions">
        <button
          type="button"
          class="journal-button"
          :disabled="busy"
          @click="$emit('edit')"
        >
          {{ $t("vtt.journal.actions.edit") }}
        </button>
        <button
          type="button"
          class="journal-button"
          :disabled="busy"
          @click="$emit('archive')"
        >
          {{
            $t(
              entry.archived
                ? "vtt.journal.actions.restore"
                : "vtt.journal.actions.archiveVerb",
            )
          }}
        </button>
        <button
          type="button"
          class="journal-button journal-button--danger"
          :disabled="busy"
          @click="$emit('delete')"
        >
          {{ $t("vtt.journal.actions.delete") }}
        </button>
      </div>
    </header>

    <dl class="journal-entry__meta">
      <div>
        <dt>{{ $t("vtt.journal.fields.status") }}</dt>
        <dd>{{ $t(`vtt.journal.status.${entry.status}`) }}</dd>
      </div>
      <div>
        <dt>{{ $t("vtt.journal.fields.visibility") }}</dt>
        <dd>{{ $t(`vtt.journal.visibility.${entry.visibility}`) }}</dd>
      </div>
      <div>
        <dt>{{ $t("vtt.journal.fields.session") }}</dt>
        <dd>{{ entry.sessionNumber ?? "—" }}</dd>
      </div>
      <div>
        <dt>{{ $t("vtt.journal.fields.date") }}</dt>
        <dd>{{ entry.occurredOn || "—" }}</dd>
      </div>
      <div>
        <dt>{{ $t("vtt.journal.fields.author") }}</dt>
        <dd>{{ entry.author?.name || "—" }}</dd>
      </div>
      <div v-if="entry.type === 'npc'">
        <dt>{{ $t("vtt.journal.fields.trust") }}</dt>
        <dd>{{ entry.trustLevel ?? "—" }} / 100</dd>
      </div>
    </dl>

    <div class="journal-entry__sections">
      <section
        v-for="section in nonEmptySections"
        :key="section.key"
        class="journal-entry-section"
      >
        <h4>
          {{ $t(`vtt.journal.sections.${section.key}`) }}
          <small v-if="section.visibility">
            {{ $t(`vtt.journal.visibility.${section.visibility}`) }}
          </small>
        </h4>
        <p>{{ section.content }}</p>
      </section>
    </div>

    <section v-if="entry.checklist.length" class="journal-entry-block">
      <h4>{{ $t("vtt.journal.fields.checklist") }}</h4>
      <button
        v-for="item in entry.checklist"
        :key="item.id"
        type="button"
        class="journal-check-item"
        :class="{ 'journal-check-item--done': item.isCompleted }"
        :disabled="!entry.capabilities.canEdit || busy"
        :aria-pressed="String(item.isCompleted)"
        @click="$emit('toggle-checklist', item)"
      >
        <span aria-hidden="true">{{ item.isCompleted ? "☑" : "☐" }}</span>
        <span>{{ item.label }}</span>
      </button>
    </section>

    <section v-if="entry.encounters.length" class="journal-entry-block">
      <h4>{{ $t("vtt.journal.fields.encounters") }}</h4>
      <ol class="journal-encounters">
        <li v-for="encounter in entry.encounters" :key="encounter.id">
          <strong>
            {{
              encounter.sessionNumber
                ? $t("vtt.journal.sessionShort", {
                    number: encounter.sessionNumber,
                  })
                : $t("vtt.journal.fields.encounter")
            }}
          </strong>
          <small v-if="encounter.occurredOn">{{ encounter.occurredOn }}</small>
          <p>{{ encounter.summary }}</p>
        </li>
      </ol>
    </section>

    <section v-if="entry.relations.length" class="journal-entry-block">
      <h4>{{ $t("vtt.journal.fields.relations") }}</h4>
      <ul class="journal-relations">
        <li v-for="relation in entry.relations" :key="relation.id">
          <small>{{
            $t(`vtt.journal.relation.${relation.relationType}`)
          }}</small>
          <strong>{{ relation.target.title }}</strong>
        </li>
      </ul>
    </section>
  </article>
</template>

<script>
export default {
  name: "JournalEntryView",
  props: {
    entry: { type: Object, required: true },
    busy: { type: Boolean, default: false },
  },
  emits: ["edit", "archive", "delete", "toggle-checklist"],
  computed: {
    nonEmptySections() {
      return this.entry.sections.filter((section) =>
        String(section.content || "").trim(),
      );
    },
    tabKey() {
      return {
        quest: "quests",
        npc: "npcs",
        motivation: "motivations",
        chronicle: "chronicle",
      }[this.entry.type];
    },
  },
};
</script>
