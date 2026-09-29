<template>
  <section
    class="compendium-workspace"
    :class="{
      'compendium-workspace--compact': compact,
      'compendium-workspace--reading': compact && compactReading,
    }"
  >
    <header class="compendium-workspace__topbar">
      <div class="compendium-workspace__identity">
        <span>{{
          editorial
            ? $t("vtt.table.compendium.editorial")
            : $t("vtt.table.compendium.library")
        }}</span>
        <strong>{{
          overview.world?.name || $t("vtt.table.compendium.titleModule")
        }}</strong>
      </div>
      <nav :aria-label="$t('vtt.table.compendium.views')">
        <button
          type="button"
          :class="{ active: mode === 'articles' }"
          @click="setMode('articles')"
        >
          {{ $t("vtt.table.compendium.articles") }}
        </button>
        <button
          type="button"
          :class="{ active: mode === 'timeline' }"
          @click="setMode('timeline')"
        >
          {{ $t("vtt.table.compendium.timeline") }}
        </button>
        <button
          type="button"
          :class="{ active: mode === 'bestiary' }"
          @click="setMode('bestiary')"
        >
          {{ $t("vtt.table.compendium.bestiary") }}
        </button>
        <button
          v-if="canManageSchema"
          type="button"
          :class="{ active: mode === 'types' }"
          @click="setMode('types')"
        >
          {{ $t("vtt.table.compendium.types") }}
        </button>
        <button
          v-if="canManageSchema"
          type="button"
          :class="{ active: mode === 'settings' }"
          @click="setMode('settings')"
        >
          {{ $t("vtt.table.compendium.settings") }}
        </button>
      </nav>
      <div class="compendium-workspace__summary">
        <span
          >{{ entries.length }} {{ $t("vtt.table.compendium.entries") }}</span
        >
        <button
          type="button"
          :title="$t('vtt.table.compendium.refresh')"
          :aria-label="$t('vtt.table.compendium.refresh')"
          @click="initialize"
        >
          ↻
        </button>
      </div>
    </header>

    <div v-if="errorMessage" class="compendium-workspace__error" role="alert">
      <span>{{ errorMessage }}</span>
      <button type="button" @click="initialize">
        {{ $t("vtt.table.compendium.retry") }}
      </button>
    </div>
    <div
      v-if="loading && !overview.world"
      class="compendium-workspace__loading"
    >
      {{ $t("vtt.table.compendium.loading") }}
    </div>

    <template v-else-if="mode === 'types'">
      <section class="compendium-schema">
        <header>
          <h2>{{ $t("vtt.table.compendium.types") }}</h2>
          <button type="button" @click="newType">
            {{ $t("vtt.table.compendium.addType") }}
          </button>
        </header>
        <article v-for="type in overview.types" :key="type.id">
          <strong>{{ type.name }}</strong
          ><code>{{ type.code }}</code
          ><span
            >{{ type.fields.length }}
            {{ $t("vtt.table.compendium.fields") }}</span
          >
          <button v-if="!type.builtin" type="button" @click="editType(type)">
            {{ $t("vtt.table.compendium.edit") }}
          </button>
          <button v-if="!type.builtin" type="button" @click="deleteType(type)">
            {{ $t("vtt.table.compendium.delete") }}
          </button>
        </article>
        <form v-if="typeDraft" @submit.prevent="saveType">
          <input
            v-model.trim="typeDraft.name"
            required
            maxlength="100"
            :placeholder="$t('vtt.table.compendium.typeName')"
          />
          <input
            v-model.trim="typeDraft.code"
            :disabled="Boolean(typeDraft.id)"
            required
            maxlength="64"
            placeholder="code"
          />
          <textarea
            v-model="typeDraft.fieldsText"
            rows="10"
            :placeholder="$t('vtt.table.compendium.fieldsJson')"
          />
          <button type="submit">{{ $t("vtt.table.compendium.save") }}</button
          ><button type="button" @click="typeDraft = null">
            {{ $t("vtt.table.compendium.cancel") }}
          </button>
        </form>
      </section>
    </template>

    <template v-else-if="mode === 'settings'">
      <section class="compendium-settings">
        <div>
          <h2>{{ $t("vtt.table.compendium.calendar") }}</h2>
          <form @submit.prevent="saveCalendar">
            <input v-model.trim="calendarDraft.name" required />
            <label
              >{{ $t("vtt.table.compendium.monthsJson")
              }}<textarea
                v-model="calendarDraft.monthsText"
                rows="8"
                :disabled="overview.calendar?.structureLocked"
              />
            </label>
            <label
              >{{ $t("vtt.table.compendium.erasJson")
              }}<textarea
                v-model="calendarDraft.erasText"
                rows="8"
                :disabled="overview.calendar?.structureLocked"
              />
            </label>
            <p v-if="overview.calendar?.structureLocked">
              {{ $t("vtt.table.compendium.calendarLocked") }}
            </p>
            <button type="submit">{{ $t("vtt.table.compendium.save") }}</button>
          </form>
        </div>
        <div>
          <h2>{{ $t("vtt.table.compendium.tags") }}</h2>
          <form @submit.prevent="addTag">
            <input v-model.trim="newTagName" required maxlength="80" /><input
              v-model="newTagColor"
              type="color"
            /><button>{{ $t("vtt.table.compendium.add") }}</button>
          </form>
          <p v-for="tag in overview.tags" :key="tag.id">
            <span
              class="compendium-tag-dot"
              :style="{ background: tag.color }"
            />{{ tag.name }}
            <button type="button" @click="deleteTag(tag)">×</button>
          </p>
        </div>
        <div v-if="overview.capabilities?.canManageEditors">
          <h2>{{ $t("vtt.table.compendium.editors") }}</h2>
          <form @submit.prevent="addEditor">
            <input
              v-model.trim="editorIdentity"
              required
              :placeholder="$t('vtt.table.compendium.userIdentity')"
            /><button>{{ $t("vtt.table.compendium.add") }}</button>
          </form>
          <p v-for="editor in overview.editors" :key="editor.userId">
            {{ editor.username }} ({{ editor.email }})
            <button type="button" @click="removeEditor(editor)">×</button>
          </p>
        </div>
        <div v-if="overview.capabilities?.canAssignOwner">
          <h2>{{ $t("vtt.table.compendium.owner") }}</h2>
          <form @submit.prevent="assignOwner">
            <input
              v-model="ownerUserId"
              type="number"
              min="1"
              :placeholder="$t('vtt.table.compendium.ownerId')"
            />
            <button>{{ $t("vtt.table.compendium.assign") }}</button>
          </form>
        </div>
      </section>
    </template>

    <div v-else class="compendium-workspace__body">
      <aside class="compendium-workspace__navigation">
        <div class="compendium-workspace__filters">
          <div class="compendium-workspace__search">
            <span aria-hidden="true">⌕</span>
            <input
              v-model="filters.q"
              type="search"
              :placeholder="$t('vtt.table.compendium.search')"
              @input="queueLoad"
            />
          </div>
          <select
            v-model="filters.type"
            :aria-label="$t('vtt.table.compendium.allTypes')"
            @change="loadEntries"
          >
            <option value="">{{ $t("vtt.table.compendium.allTypes") }}</option>
            <option
              v-for="type in visibleTypes"
              :key="type.id"
              :value="type.id"
            >
              {{ type.name }}
            </option>
          </select>
          <select
            v-model="filters.tag"
            :aria-label="$t('vtt.table.compendium.allTags')"
            @change="loadEntries"
          >
            <option value="">{{ $t("vtt.table.compendium.allTags") }}</option>
            <option v-for="tag in overview.tags" :key="tag.id" :value="tag.id">
              {{ tag.name }}
            </option>
          </select>
          <select
            v-if="editorial"
            v-model="filters.status"
            :aria-label="$t('vtt.table.compendium.allStatuses')"
            @change="loadEntries"
          >
            <option value="active">
              {{ $t("vtt.table.compendium.active") }}
            </option>
            <option value="archived">
              {{ $t("vtt.table.compendium.archived") }}
            </option>
            <option value="all">
              {{ $t("vtt.table.compendium.allStatuses") }}
            </option>
          </select>
        </div>
        <div class="compendium-workspace__list-header">
          <strong>{{ $t("vtt.table.compendium.results") }}</strong>
          <button
            v-if="
              filters.q || filters.tag || (filters.type && mode !== 'bestiary')
            "
            type="button"
            @click="clearFilters"
          >
            {{ $t("vtt.table.compendium.clear") }}
          </button>
        </div>
        <button
          v-if="editorial"
          type="button"
          class="compendium-workspace__new"
          @click="createNew"
        >
          + {{ $t("vtt.table.compendium.newEntry") }}
        </button>
        <ol
          class="compendium-workspace__list"
          @keydown.up.prevent="selectRelative(-1)"
          @keydown.down.prevent="selectRelative(1)"
        >
          <li
            v-for="item in entries"
            :key="item.id"
            :style="{ '--compendium-depth': hierarchyDepth(item) }"
          >
            <button
              type="button"
              :class="{ active: Number(item.id) === Number(selectedId) }"
              :aria-current="
                Number(item.id) === Number(selectedId) ? 'page' : undefined
              "
              @click="select(item)"
            >
              <span class="compendium-workspace__item-title">{{
                item.title
              }}</span>
              <span class="compendium-workspace__item-meta">
                {{ typeLabel(item.typeId) }}
                <template v-if="item.startOrdinal !== null">
                  · {{ item.startOrdinal }}</template
                >
                <template v-else-if="item.versionNumber">
                  · v{{ item.versionNumber }}</template
                >
              </span>
            </button>
          </li>
        </ol>
        <p
          v-if="!loading && !entries.length"
          class="compendium-workspace__no-results"
        >
          {{ $t("vtt.table.compendium.noResults") }}
        </p>
        <button
          v-if="hasMore"
          type="button"
          class="compendium-workspace__more"
          @click="loadMore"
        >
          {{ $t("vtt.table.compendium.more") }}
        </button>
      </aside>

      <main class="compendium-workspace__content">
        <button
          v-if="compact && compactReading"
          type="button"
          class="compendium-workspace__back"
          @click="showCompactList"
        >
          ← {{ $t("vtt.table.compendium.backToList") }}
        </button>
        <CompendiumEntryEditor
          v-if="editing"
          :key="`editor-${selectedId || 'new'}`"
          :universe-id="universeId"
          :entry="selectedEntry"
          :history="history"
          :entries="entries"
          :types="overview.types || []"
          :tags="overview.tags || []"
          @saved="entrySaved"
          @published="entryPublished"
          @reload="reloadSelected"
          @cancel="editing = false"
        />
        <CompendiumEntryView
          v-else-if="selectedEntry"
          :entry="selectedEntry"
          :types="overview.types || []"
          :campaign-id="campaignId"
          :scene-id="sceneId"
          :token-x="tokenX"
          :token-y="tokenY"
          :editorial="editorial"
          :show-context-links="compact"
          @navigate="openById"
          @edit="editing = true"
          @materialized="$emit('materialized', $event)"
        />
        <div v-else class="compendium-workspace__empty">
          {{ $t("vtt.table.compendium.chooseEntry") }}
        </div>
      </main>

      <aside
        v-if="selectedEntry && !compact"
        class="compendium-workspace__properties"
      >
        <header>
          <span>{{ $t("vtt.table.compendium.properties") }}</span>
          <strong>{{ selectedEntry.title }}</strong>
        </header>
        <dl>
          <dt>{{ $t("vtt.table.compendium.type") }}</dt>
          <dd>{{ typeLabel(selectedEntry.typeId) }}</dd>
          <dt>{{ $t("vtt.table.compendium.visibility") }}</dt>
          <dd>{{ visibilityLabel(selectedEntry.visibility) }}</dd>
          <dt>{{ $t("vtt.table.compendium.version") }}</dt>
          <dd>
            {{
              selectedEntry.versionNumber || $t("vtt.table.compendium.draft")
            }}
          </dd>
          <dt v-if="selectedEntry.startOrdinal !== null">ordinalDay</dt>
          <dd v-if="selectedEntry.startOrdinal !== null">
            {{ selectedEntry.startOrdinal
            }}<template
              v-if="selectedEntry.endOrdinal !== selectedEntry.startOrdinal"
              >–{{ selectedEntry.endOrdinal }}</template
            >
          </dd>
          <dt v-if="selectedEntry.parentEntryId">
            {{ $t("vtt.table.compendium.parent") }}
          </dt>
          <dd v-if="selectedEntry.parentEntryId">
            <button
              type="button"
              @click="openById(selectedEntry.parentEntryId)"
            >
              {{ entryTitle(selectedEntry.parentEntryId) }}
            </button>
          </dd>
        </dl>
        <section v-if="selectedEntry.tags?.length">
          <h3>{{ $t("vtt.table.compendium.tags") }}</h3>
          <div class="compendium-workspace__property-tags">
            <button
              v-for="tag in selectedEntry.tags"
              :key="tag.id"
              type="button"
              @click="filterByTag(tag.id)"
            >
              <i :style="{ background: tag.color || '#64748b' }" />{{
                tag.name
              }}
            </button>
          </div>
        </section>
        <section v-if="selectedEntry.relations?.length">
          <h3>{{ $t("vtt.table.compendium.relations") }}</h3>
          <button
            v-for="relation in selectedEntry.relations"
            :key="`${relation.targetEntryId}:${relation.audience}`"
            type="button"
            class="compendium-workspace__property-link"
            @click="openById(relation.targetEntryId)"
          >
            <small>{{ relation.label }}</small
            >{{ relation.title }}
          </button>
        </section>
        <section v-if="selectedEntry.backlinks?.length">
          <h3>{{ $t("vtt.table.compendium.backlinks") }}</h3>
          <button
            v-for="link in selectedEntry.backlinks"
            :key="link.sourceEntryId"
            type="button"
            class="compendium-workspace__property-link"
            @click="openById(link.sourceEntryId)"
          >
            {{ link.title }}
          </button>
        </section>
        <button
          v-if="editorial && selectedEntry.status === 'active'"
          type="button"
          @click="archiveSelected"
        >
          {{ $t("vtt.table.compendium.archive") }}
        </button>
        <button
          v-if="editorial && selectedEntry.status === 'archived'"
          type="button"
          @click="restoreSelected"
        >
          {{ $t("vtt.table.compendium.restore") }}
        </button>
      </aside>
    </div>
  </section>
</template>

<script>
import { defineAsyncComponent } from "vue";
import CompendiumEntryView from "./CompendiumEntryView.vue";
import { compendiumApiClient } from "@/lib/compendium/compendiumApiClient";

const CompendiumEntryEditor = defineAsyncComponent(
  () =>
    import(
      /* webpackChunkName: "compendium-editor" */ "./CompendiumEntryEditor.vue"
    ),
);

export default {
  name: "CompendiumWorkspace",
  components: { CompendiumEntryEditor, CompendiumEntryView },
  props: {
    campaignId: { type: [Number, String], default: null },
    universeId: { type: [Number, String], default: null },
    compact: { type: Boolean, default: false },
    sceneId: { type: [Number, String], default: null },
    tokenX: { type: Number, default: 0 },
    tokenY: { type: Number, default: 0 },
  },
  emits: ["materialized"],
  data: () => ({
    overview: {},
    entries: [],
    selectedEntry: null,
    selectedId: null,
    compactReading: false,
    history: [],
    loading: false,
    editing: false,
    mode: "articles",
    filters: { q: "", type: "", tag: "", status: "active" },
    page: 1,
    hasMore: false,
    errorMessage: "",
    searchTimer: null,
    typeDraft: null,
    calendarDraft: { name: "", monthsText: "[]", erasText: "[]" },
    newTagName: "",
    newTagColor: "#8b5cf6",
    editorIdentity: "",
    ownerUserId: "",
  }),
  computed: {
    editorial() {
      return Boolean(this.universeId);
    },
    canManageSchema() {
      return this.editorial && this.overview.capabilities?.canManageSchema;
    },
    creatureType() {
      return (this.overview.types || []).find(
        (type) => type.code === "creature",
      );
    },
    visibleTypes() {
      return this.mode === "bestiary" && this.creatureType
        ? [this.creatureType]
        : this.overview.types || [];
    },
  },
  async mounted() {
    await this.initialize();
  },
  beforeUnmount() {
    clearTimeout(this.searchTimer);
  },
  methods: {
    async initialize() {
      this.loading = true;
      this.errorMessage = "";
      try {
        this.overview = this.editorial
          ? await compendiumApiClient.worldOverview(this.universeId)
          : await compendiumApiClient.campaignOverview(this.campaignId);
        this.overview.types = (this.overview.types || []).map((type) => ({
          ...type,
          name: type.builtin
            ? this.$t(`vtt.table.compendium.builtinTypes.${type.code}`)
            : type.name,
        }));
        this.ownerUserId = this.overview.world?.ownerUserId || "";
        this.resetCalendar();
        await this.loadEntries();
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      } finally {
        this.loading = false;
      }
    },
    setMode(mode) {
      this.mode = mode;
      this.selectedEntry = null;
      this.selectedId = null;
      this.editing = false;
      this.compactReading = false;
      this.filters.type =
        mode === "bestiary" ? this.creatureType?.id || "" : "";
      if (["articles", "timeline", "bestiary"].includes(mode))
        this.loadEntries();
    },
    queueLoad() {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => this.loadEntries(), 300);
    },
    async loadEntries(append = false) {
      if (!append) this.page = 1;
      this.loading = true;
      try {
        const query = {
          ...this.filters,
          page: this.page,
          limit: 50,
          ...(this.mode === "timeline" ? { dated: 1, sort: "timeline" } : {}),
        };
        const response = this.editorial
          ? await compendiumApiClient.worldEntries(this.universeId, query)
          : this.mode === "timeline"
            ? await compendiumApiClient.campaignTimeline(this.campaignId, query)
            : await compendiumApiClient.campaignEntries(this.campaignId, query);
        const nextEntries = append
          ? [...this.entries, ...response.items]
          : response.items;
        this.entries = nextEntries;
        this.hasMore = response.hasMore;
        if (!append && !this.compact && !this.editing && nextEntries.length) {
          const selectionExists = nextEntries.some(
            (item) => Number(item.id) === Number(this.selectedId),
          );
          if (!selectionExists) await this.openById(nextEntries[0].id);
        }
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      } finally {
        this.loading = false;
      }
    },
    async loadMore() {
      this.page += 1;
      await this.loadEntries(true);
    },
    select(item) {
      this.openById(item.id);
    },
    async openById(entryId) {
      this.selectedId = Number(entryId);
      this.editing = false;
      if (this.compact) this.compactReading = true;
      try {
        const response = this.editorial
          ? await compendiumApiClient.worldEntry(this.universeId, entryId)
          : await compendiumApiClient.campaignEntry(this.campaignId, entryId);
        this.selectedEntry = response.entry;
        this.history = response.history || [];
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    async reloadSelected() {
      if (this.selectedId) await this.openById(this.selectedId);
      this.editing = true;
    },
    createNew() {
      this.selectedId = null;
      this.selectedEntry = null;
      this.history = [];
      this.editing = true;
      this.compactReading = true;
    },
    async entrySaved(entry) {
      this.selectedEntry = entry;
      this.selectedId = entry.id;
      this.editing = true;
      await this.loadEntries();
    },
    async entryPublished(response) {
      this.selectedEntry = response.entry;
      this.editing = false;
      await this.loadEntries();
    },
    async archiveSelected() {
      await compendiumApiClient.archive(
        this.universeId,
        this.selectedEntry.id,
        this.selectedEntry.revision,
      );
      this.selectedEntry = null;
      await this.loadEntries();
    },
    async restoreSelected() {
      const response = await compendiumApiClient.restore(
        this.universeId,
        this.selectedEntry.id,
        this.selectedEntry.revision,
      );
      this.selectedEntry = response.entry;
      await this.loadEntries();
    },
    typeLabel(id) {
      return (
        (this.overview.types || []).find(
          (type) => Number(type.id) === Number(id),
        )?.name || ""
      );
    },
    visibilityLabel(value) {
      return value === "gm_only"
        ? this.$t("vtt.table.compendium.gmOnly")
        : this.$t("vtt.table.compendium.players");
    },
    entryTitle(id) {
      return (
        this.entries.find((entry) => Number(entry.id) === Number(id))?.title ||
        `#${id}`
      );
    },
    clearFilters() {
      this.filters.q = "";
      this.filters.tag = "";
      this.filters.type =
        this.mode === "bestiary" ? this.creatureType?.id || "" : "";
      this.loadEntries();
    },
    filterByTag(tagId) {
      this.filters.tag = tagId;
      this.loadEntries();
    },
    selectRelative(offset) {
      if (!this.entries.length) return;
      const current = this.entries.findIndex(
        (entry) => Number(entry.id) === Number(this.selectedId),
      );
      const index = Math.min(
        this.entries.length - 1,
        Math.max(0, (current < 0 ? 0 : current) + offset),
      );
      this.openById(this.entries[index].id);
    },
    showCompactList() {
      this.compactReading = false;
      this.editing = false;
    },
    hierarchyDepth(item) {
      let depth = 0;
      let current = item;
      const seen = new Set();
      while (
        current?.parentEntryId &&
        depth < 8 &&
        !seen.has(current.parentEntryId)
      ) {
        seen.add(current.parentEntryId);
        current = this.entries.find(
          (candidate) => Number(candidate.id) === Number(current.parentEntryId),
        );
        depth += 1;
      }
      return depth;
    },
    newType() {
      this.typeDraft = { name: "", code: "", fieldsText: "[]" };
    },
    editType(type) {
      this.typeDraft = {
        ...type,
        fieldsText: JSON.stringify(type.fields || [], null, 2),
      };
    },
    async saveType() {
      try {
        const payload = {
          name: this.typeDraft.name,
          code: this.typeDraft.code,
          fields: JSON.parse(this.typeDraft.fieldsText || "[]"),
        };
        if (this.typeDraft.id)
          await compendiumApiClient.updateType(
            this.universeId,
            this.typeDraft.id,
            payload,
          );
        else await compendiumApiClient.createType(this.universeId, payload);
        this.typeDraft = null;
        await this.initialize();
        this.mode = "types";
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    async deleteType(type) {
      if (!window.confirm(this.$t("vtt.table.compendium.deleteConfirm")))
        return;
      try {
        await compendiumApiClient.deleteType(this.universeId, type.id);
        await this.initialize();
        this.mode = "types";
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    resetCalendar() {
      const calendar = this.overview.calendar || {};
      this.calendarDraft = {
        name: calendar.name || "",
        revision: calendar.revision,
        monthsText: JSON.stringify(calendar.months || [], null, 2),
        erasText: JSON.stringify(calendar.eras || [], null, 2),
      };
    },
    async saveCalendar() {
      try {
        const response = await compendiumApiClient.updateCalendar(
          this.universeId,
          {
            name: this.calendarDraft.name,
            revision: this.calendarDraft.revision,
            months: JSON.parse(this.calendarDraft.monthsText).map(
              ({ name, days }) => ({ name, days }),
            ),
            eras: JSON.parse(this.calendarDraft.erasText).map(
              ({ name, abbreviation, epochOrdinal, direction }) => ({
                name,
                abbreviation,
                epochOrdinal,
                direction,
              }),
            ),
          },
        );
        this.overview.calendar = response.calendar;
        this.resetCalendar();
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    async addTag() {
      try {
        const response = await compendiumApiClient.createTag(this.universeId, {
          name: this.newTagName,
          color: this.newTagColor,
        });
        this.overview.tags = [...(this.overview.tags || []), response.tag].sort(
          (left, right) => left.name.localeCompare(right.name),
        );
        this.newTagName = "";
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    async deleteTag(tag) {
      try {
        await compendiumApiClient.deleteTag(this.universeId, tag.id);
        this.overview.tags = this.overview.tags.filter(
          (item) => item.id !== tag.id,
        );
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    async addEditor() {
      try {
        const response = await compendiumApiClient.addEditor(
          this.universeId,
          this.editorIdentity,
        );
        this.overview.editors = response.editors;
        this.editorIdentity = "";
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    async removeEditor(editor) {
      try {
        const response = await compendiumApiClient.removeEditor(
          this.universeId,
          editor.userId,
        );
        this.overview.editors = response.editors;
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    async assignOwner() {
      try {
        this.overview = await compendiumApiClient.assignOwner(
          this.universeId,
          this.ownerUserId ? Number(this.ownerUserId) : null,
        );
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
  },
};
</script>

<style src="./compendium.css"></style>
