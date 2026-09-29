<template>
  <form class="compendium-editor" @submit.prevent="saveNow">
    <header class="compendium-editor__header">
      <input
        v-model.trim="draft.title"
        required
        maxlength="180"
        :placeholder="$t('vtt.table.compendium.title')"
        @input="changed"
      />
      <span class="compendium-editor__save-state">{{ saveStateLabel }}</span>
    </header>

    <div class="compendium-editor__properties">
      <label>
        {{ $t("vtt.table.compendium.type") }}
        <select v-model.number="draft.typeId" required @change="typeChanged">
          <option v-for="type in types" :key="type.id" :value="type.id">
            {{ type.name }}
          </option>
        </select>
      </label>
      <label>
        {{ $t("vtt.table.compendium.visibility") }}
        <select v-model="draft.visibility" @change="changed">
          <option value="players">
            {{ $t("vtt.table.compendium.players") }}
          </option>
          <option value="gm_only">
            {{ $t("vtt.table.compendium.gmOnly") }}
          </option>
        </select>
      </label>
      <label>
        {{ $t("vtt.table.compendium.parent") }}
        <select v-model="draft.parentEntryId" @change="changed">
          <option :value="null">—</option>
          <option
            v-for="target in relationTargets"
            :key="target.id"
            :value="target.id"
          >
            {{ target.title }}
          </option>
        </select>
      </label>
      <label>
        {{ $t("vtt.table.compendium.aliases") }}
        <input v-model="aliasesText" @input="aliasesChanged" />
      </label>
      <label class="compendium-editor__wide">
        {{ $t("vtt.table.compendium.excerpt") }}
        <textarea
          v-model="draft.excerpt"
          maxlength="500"
          rows="2"
          @input="changed"
        />
      </label>
      <label class="compendium-editor__wide">
        {{ $t("vtt.table.compendium.chronologyJson") }}
        <textarea
          v-model="chronologyText"
          rows="3"
          :placeholder="$t('vtt.table.compendium.chronologyHint')"
          @input="changed"
        />
      </label>
    </div>

    <fieldset v-if="tags.length" class="compendium-editor__tags">
      <legend>{{ $t("vtt.table.compendium.tags") }}</legend>
      <label v-for="tag in tags" :key="tag.id">
        <input
          v-model="draft.tagIds"
          type="checkbox"
          :value="tag.id"
          @change="changed"
        />
        {{ tag.name }}
      </label>
    </fieldset>

    <section v-if="currentFields.length" class="compendium-editor__fields">
      <label v-for="field in currentFields" :key="field.key">
        {{ field.label }}
        <small>{{
          field.audience === "gm" ? $t("vtt.table.compendium.gmNotes") : ""
        }}</small>
        <input
          v-if="field.type === 'text' || field.type === 'number'"
          v-model="fieldValues[field.audience][field.key]"
          :type="field.type === 'number' ? 'number' : 'text'"
          :required="field.required"
          @input="fieldChanged(field)"
        />
        <input
          v-else-if="field.type === 'boolean'"
          v-model="fieldValues[field.audience][field.key]"
          type="checkbox"
          @change="changed"
        />
        <select
          v-else-if="field.type === 'select'"
          v-model="fieldValues[field.audience][field.key]"
          :required="field.required"
          @change="changed"
        >
          <option value="">—</option>
          <option v-for="option in field.options" :key="option" :value="option">
            {{ option }}
          </option>
        </select>
        <select
          v-else-if="field.type === 'multiselect'"
          v-model="fieldValues[field.audience][field.key]"
          multiple
          @change="changed"
        >
          <option v-for="option in field.options" :key="option" :value="option">
            {{ option }}
          </option>
        </select>
        <select
          v-else-if="field.type === 'relation'"
          v-model.number="fieldValues[field.audience][field.key]"
          @change="changed"
        >
          <option value="">—</option>
          <option
            v-for="target in relationTargets"
            :key="target.id"
            :value="target.id"
          >
            {{ target.title }}
          </option>
        </select>
        <textarea
          v-else
          v-model="dateFields[field.audience][field.key]"
          rows="2"
          placeholder='{"eraId":1,"year":1,"monthId":1,"day":1}'
          @input="changed"
        />
      </label>
    </section>

    <div class="compendium-editor__tabs">
      <button
        type="button"
        :class="{ active: contentTab === 'public' }"
        @click="contentTab = 'public'"
      >
        {{ $t("vtt.table.compendium.content") }}
      </button>
      <button
        type="button"
        :class="{ active: contentTab === 'gm' }"
        @click="contentTab = 'gm'"
      >
        {{ $t("vtt.table.compendium.gmNotes") }}
      </button>
    </div>
    <HandoutEditor
      v-if="contentTab === 'public'"
      :model-value="editorDocuments.public"
      :mention-targets="mentionTargets"
      allow-mentions
      @update:model-value="documentChanged('public', $event)"
      @upload-image="uploadAsset('public', $event)"
      @error="errorMessage = $event"
    />
    <HandoutEditor
      v-else
      :model-value="editorDocuments.gm"
      :mention-targets="mentionTargets"
      allow-mentions
      @update:model-value="documentChanged('gm', $event)"
      @upload-image="uploadAsset('gm', $event)"
      @error="errorMessage = $event"
    />

    <details class="compendium-editor__relations">
      <summary>{{ $t("vtt.table.compendium.relations") }}</summary>
      <div
        v-for="(relation, index) in draft.relations"
        :key="index"
        class="compendium-editor__relation"
      >
        <select v-model.number="relation.targetEntryId" @change="changed">
          <option
            v-for="target in relationTargets"
            :key="target.id"
            :value="target.id"
          >
            {{ target.title }}
          </option>
        </select>
        <input v-model="relation.label" maxlength="180" @input="changed" />
        <select v-model="relation.audience" @change="changed">
          <option value="public">Public</option>
          <option value="gm">MG</option>
        </select>
        <button type="button" @click="removeRelation(index)">×</button>
      </div>
      <button
        type="button"
        :disabled="!relationTargets.length"
        @click="addRelation"
      >
        {{ $t("vtt.table.compendium.addRelation") }}
      </button>
    </details>

    <details
      v-if="currentType?.code === 'creature'"
      class="compendium-editor__statblocks"
    >
      <summary>{{ $t("vtt.table.compendium.statBlocks") }}</summary>
      <textarea v-model="statBlocksText" rows="8" @input="changed" />
    </details>

    <p v-if="errorMessage" class="compendium-editor__error">
      {{ errorMessage }}
    </p>
    <section v-if="conflict" class="compendium-editor__conflict" role="alert">
      <p>{{ $t("vtt.table.compendium.revisionConflict") }}</p>
      <button type="button" @click="$emit('reload')">
        {{ $t("vtt.table.compendium.loadLatest") }}
      </button>
      <button type="button" @click="copyLocal">
        {{ $t("vtt.table.compendium.copyMine") }}
      </button>
    </section>

    <footer class="compendium-editor__actions">
      <button type="submit" :disabled="saving">
        {{ $t("vtt.table.compendium.save") }}
      </button>
      <button
        v-if="entry?.id"
        type="button"
        :disabled="saving || conflict"
        @click="publish"
      >
        {{ $t("vtt.table.compendium.publish") }}
      </button>
      <button type="button" @click="$emit('cancel')">
        {{ $t("vtt.table.compendium.cancel") }}
      </button>
    </footer>

    <details v-if="history.length" class="compendium-editor__history">
      <summary>{{ $t("vtt.table.compendium.history") }}</summary>
      <ol>
        <li v-for="version in history" :key="version.versionId">
          v{{ version.versionNumber }} — {{ version.publishedAt }}
          <button type="button" @click="restoreVersion(version)">
            {{ $t("vtt.table.compendium.restoreVersion") }}
          </button>
        </li>
      </ol>
    </details>
  </form>
</template>

<script>
import HandoutEditor from "@/components/vtt/handout/HandoutEditor.vue";
import { compendiumApiClient } from "@/lib/compendium/compendiumApiClient";

const EMPTY = { type: "doc", content: [{ type: "paragraph", content: [] }] };
const clone = (value) => JSON.parse(JSON.stringify(value ?? null));
const mapDocument = (node, direction, labels = {}, assetUrls = {}) => {
  if (!node || typeof node !== "object") return node;
  const result = { ...node, attrs: node.attrs ? { ...node.attrs } : undefined };
  if (direction === "editor" && result.type === "compendiumMention") {
    result.type = "mention";
    result.attrs = {
      targetType: "entry",
      targetId: Number(result.attrs.entryId),
      label: labels[Number(result.attrs.entryId)] || "…",
    };
  } else if (direction === "api" && result.type === "mention") {
    result.type = "compendiumMention";
    result.attrs = { entryId: Number(result.attrs.targetId) };
  }
  if (result.type === "image" && result.attrs) {
    if (direction === "api") delete result.attrs.src;
    else
      result.attrs.src =
        assetUrls[Number(result.attrs.assetId)] || result.attrs.src || "";
  }
  if (Array.isArray(result.content))
    result.content = result.content.map((child) =>
      mapDocument(child, direction, labels, assetUrls),
    );
  return result;
};

export default {
  name: "CompendiumEntryEditor",
  components: { HandoutEditor },
  props: {
    universeId: { type: [Number, String], required: true },
    entry: { type: Object, default: null },
    history: { type: Array, default: () => [] },
    entries: { type: Array, default: () => [] },
    types: { type: Array, required: true },
    tags: { type: Array, default: () => [] },
  },
  emits: ["saved", "published", "cancel", "reload"],
  data() {
    return {
      draft: {},
      aliasesText: "",
      chronologyText: "",
      statBlocksText: "[]",
      contentTab: "public",
      editorDocuments: { public: clone(EMPTY), gm: clone(EMPTY) },
      fieldValues: { public: {}, gm: {} },
      dateFields: { public: {}, gm: {} },
      dirty: false,
      saving: false,
      saved: false,
      conflict: false,
      errorMessage: "",
      autosaveTimer: null,
      editorAssetUrls: {},
      assetPreviewGeneration: 0,
      localRevision: this.entry?.revision || 1,
    };
  },
  computed: {
    currentType() {
      return this.types.find(
        (type) => Number(type.id) === Number(this.draft.typeId),
      );
    },
    currentFields() {
      return (this.currentType?.fields || []).filter(
        (field) => !field.deprecated,
      );
    },
    relationTargets() {
      return this.entries.filter(
        (item) => Number(item.id) !== Number(this.entry?.id),
      );
    },
    mentionTargets() {
      return this.relationTargets.map((item) => ({
        type: "entry",
        id: item.id,
        label: item.title,
      }));
    },
    saveStateLabel() {
      if (this.saving) return this.$t("vtt.table.compendium.saving");
      if (this.dirty) return this.$t("vtt.table.compendium.unsaved");
      return this.saved ? this.$t("vtt.table.compendium.saved") : "";
    },
  },
  watch: {
    entry: {
      immediate: true,
      handler() {
        this.reset();
      },
    },
  },
  beforeUnmount() {
    clearTimeout(this.autosaveTimer);
    this.assetPreviewGeneration += 1;
    this.releaseAssetPreviews();
  },
  methods: {
    reset() {
      clearTimeout(this.autosaveTimer);
      const entry = this.entry || {};
      this.draft = {
        typeId: entry.typeId || this.types[0]?.id || null,
        title: entry.title || "",
        excerpt: entry.excerpt || "",
        visibility: entry.visibility || "players",
        parentEntryId: entry.parentEntryId || null,
        tagIds: (entry.tags || []).map((tag) => tag.id),
        relations: clone(entry.relations || []),
      };
      this.aliasesText = (entry.aliases || []).join(", ");
      this.chronologyText = entry.chronology
        ? JSON.stringify(entry.chronology, null, 2)
        : "";
      const mentionLabels = Object.fromEntries(
        (entry.mentions || []).map((mention) => [mention.id, mention.title]),
      );
      this.editorDocuments = {
        public: mapDocument(
          clone(entry.publicContent || EMPTY),
          "editor",
          mentionLabels,
        ),
        gm: mapDocument(
          clone(entry.gmContent || EMPTY),
          "editor",
          mentionLabels,
        ),
      };
      this.fieldValues = {
        public: clone(entry.publicFields || {}),
        gm: clone(entry.gmFields || {}),
      };
      this.dateFields = { public: {}, gm: {} };
      this.currentFields
        .filter((field) => field.type === "date")
        .forEach((field) => {
          this.dateFields[field.audience][field.key] = JSON.stringify(
            this.fieldValues[field.audience][field.key] || {},
          );
        });
      this.statBlocksText = JSON.stringify(entry.statBlocks || [], null, 2);
      this.localRevision = entry.revision || 1;
      this.dirty = false;
      this.saved = false;
      this.conflict = false;
      this.errorMessage = "";
      this.$nextTick(() => this.loadAssetPreviews(entry));
    },
    changed() {
      this.dirty = true;
      this.saved = false;
      this.conflict = false;
      clearTimeout(this.autosaveTimer);
      if (this.entry?.id)
        this.autosaveTimer = setTimeout(() => this.saveNow(), 1500);
    },
    aliasesChanged() {
      this.changed();
    },
    typeChanged() {
      if (this.currentType?.code !== "creature") this.statBlocksText = "[]";
      this.changed();
    },
    fieldChanged(field) {
      if (
        field.type === "number" &&
        this.fieldValues[field.audience][field.key] !== ""
      ) {
        this.fieldValues[field.audience][field.key] = Number(
          this.fieldValues[field.audience][field.key],
        );
      }
      this.changed();
    },
    documentChanged(audience, value) {
      this.editorDocuments[audience] = value;
      this.changed();
    },
    addRelation() {
      this.draft.relations.push({
        targetEntryId: this.relationTargets[0].id,
        label: this.$t("vtt.table.compendium.related"),
        audience: "public",
      });
      this.changed();
    },
    removeRelation(index) {
      this.draft.relations.splice(index, 1);
      this.changed();
    },
    payload() {
      const publicFields = clone(this.fieldValues.public);
      const gmFields = clone(this.fieldValues.gm);
      this.currentFields
        .filter((field) => field.type === "date")
        .forEach((field) => {
          const raw = this.dateFields[field.audience][field.key];
          if (raw)
            publicFields &&
              (field.audience === "public"
                ? (publicFields[field.key] = JSON.parse(raw))
                : (gmFields[field.key] = JSON.parse(raw)));
        });
      return {
        ...this.draft,
        aliases: this.aliasesText
          .split(",")
          .map((value) => value.trim())
          .filter(Boolean),
        publicContent: mapDocument(clone(this.editorDocuments.public), "api"),
        gmContent: mapDocument(clone(this.editorDocuments.gm), "api"),
        publicFields,
        gmFields,
        chronology: this.chronologyText
          ? JSON.parse(this.chronologyText)
          : null,
        statBlocks: JSON.parse(this.statBlocksText || "[]"),
        revision: this.localRevision,
      };
    },
    async saveNow() {
      if (this.saving || (!this.dirty && this.entry?.id)) return;
      clearTimeout(this.autosaveTimer);
      this.saving = true;
      this.errorMessage = "";
      try {
        const response = this.entry?.id
          ? await compendiumApiClient.updateEntry(
              this.universeId,
              this.entry.id,
              this.payload(),
            )
          : await compendiumApiClient.createEntry(
              this.universeId,
              this.payload(),
            );
        this.localRevision = response.entry.revision;
        this.dirty = false;
        this.saved = true;
        this.$emit("saved", response.entry);
      } catch (error) {
        if (error?.status === 409 && error?.code === "revision_conflict")
          this.conflict = true;
        else
          this.errorMessage =
            error?.payload?.message || error?.message || "Error";
      } finally {
        this.saving = false;
      }
    },
    async publish() {
      if (this.dirty) await this.saveNow();
      if (this.conflict || this.dirty) return;
      if (
        !window.confirm(
          this.$t("vtt.table.compendium.publishConfirm", {
            version: Number(this.entry.versionNumber || 0) + 1,
          }),
        )
      )
        return;
      this.saving = true;
      try {
        const response = await compendiumApiClient.publish(
          this.universeId,
          this.entry.id,
          this.localRevision,
        );
        this.localRevision = response.entry.revision;
        this.$emit("published", response);
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      } finally {
        this.saving = false;
      }
    },
    async restoreVersion(version) {
      try {
        const response = await compendiumApiClient.restoreVersion(
          this.universeId,
          this.entry.id,
          version.versionId,
          this.localRevision,
        );
        this.localRevision = response.entry.revision;
        this.$emit("saved", response.entry);
      } catch (error) {
        this.errorMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    async uploadAsset(audience, event) {
      try {
        const response = await compendiumApiClient.uploadAsset(
          this.universeId,
          event.file,
        );
        const asset = response.asset || response;
        asset.audience = audience;
        if (String(asset.mimeType || "").startsWith("image/")) {
          this.assetPreviewGeneration += 1;
          const previewUrl = URL.createObjectURL(event.file);
          this.editorAssetUrls = {
            ...this.editorAssetUrls,
            [Number(asset.id)]: previewUrl,
          };
          asset.previewUrl = previewUrl;
        }
        event.resolve(asset);
      } catch (error) {
        event.reject(error);
      }
    },
    releaseAssetPreviews() {
      Object.values(this.editorAssetUrls).forEach((url) =>
        URL.revokeObjectURL(url),
      );
      this.editorAssetUrls = {};
    },
    async loadAssetPreviews(entry) {
      const generation = this.assetPreviewGeneration + 1;
      this.assetPreviewGeneration = generation;
      this.releaseAssetPreviews();
      const entryId = Number(entry?.id || 0);
      const revision = Number(entry?.revision || 0);
      const imageAssets = (entry?.assets || []).filter((asset) =>
        String(asset.mimeType || "").startsWith("image/"),
      );
      if (!imageAssets.length) return;
      const urls = {};
      await Promise.all(
        imageAssets.map(async (asset) => {
          try {
            const blob = await compendiumApiClient.fetchAssetBlob(asset.id);
            urls[Number(asset.id)] = URL.createObjectURL(blob);
          } catch (_error) {
            // A missing preview does not block editing or autosave.
          }
        }),
      );
      if (
        generation !== this.assetPreviewGeneration ||
        entryId !== Number(this.entry?.id || 0) ||
        revision !== Number(this.entry?.revision || 0) ||
        this.dirty
      ) {
        Object.values(urls).forEach((url) => URL.revokeObjectURL(url));
        return;
      }
      this.editorAssetUrls = urls;
      this.editorDocuments = {
        public: mapDocument(
          clone(this.editorDocuments.public),
          "editor",
          {},
          urls,
        ),
        gm: mapDocument(clone(this.editorDocuments.gm), "editor", {}, urls),
      };
    },
    async copyLocal() {
      try {
        await navigator.clipboard.writeText(
          JSON.stringify(this.payload(), null, 2),
        );
      } catch (_error) {
        this.errorMessage = this.$t("vtt.table.compendium.copyFailed");
      }
    },
  },
};
</script>
