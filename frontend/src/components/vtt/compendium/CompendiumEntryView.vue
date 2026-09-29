<template>
  <article v-if="entry" class="compendium-entry">
    <header class="compendium-entry__header">
      <div>
        <p class="compendium-entry__eyebrow">
          {{ typeName }} · {{ entry.slug }}
        </p>
        <h2>{{ entry.title }}</h2>
        <p v-if="entry.excerpt">{{ entry.excerpt }}</p>
      </div>
      <div class="compendium-entry__actions">
        <button
          v-if="entry.sourceBacked && campaignId"
          type="button"
          :title="$t('vtt.table.compendium.favorite')"
          :aria-pressed="entry.favorite"
          @click="toggleFavorite"
        >
          {{ entry.favorite ? "★" : "☆" }}
        </button>
        <button
          v-if="entry.sourceBacked && entry.capabilities?.canPin"
          type="button"
          @click="pinEntry"
        >
          {{ $t("vtt.table.compendium.pin") }}
        </button>
        <button
          v-if="
            allowCampaignReveal &&
            entry.sourceBacked &&
            entry.capabilities?.canReveal &&
            !entry.reveal
          "
          type="button"
          @click="revealEntry"
        >
          {{ $t("vtt.table.compendium.reveal") }}
        </button>
        <button
          v-if="
            allowCampaignReveal &&
            entry.sourceBacked &&
            entry.capabilities?.canReveal &&
            entry.reveal
          "
          type="button"
          @click="revokeReveal"
        >
          {{ $t("vtt.table.compendium.revokeReveal") }}
        </button>
        <button
          v-if="entry.capabilities?.canEdit && editorial"
          type="button"
          @click="$emit('edit')"
        >
          {{ $t("vtt.table.compendium.edit") }}
        </button>
        <button v-if="canCreateNpc" type="button" @click="showNpc = true">
          {{ $t("vtt.table.compendium.createNpc") }}
        </button>
      </div>
    </header>

    <div v-if="entry.tags?.length" class="compendium-entry__tags">
      <span v-for="tag in entry.tags" :key="tag.id">{{ tag.name }}</span>
    </div>

    <div v-if="entry.categories?.length" class="compendium-entry__tags">
      <span
        v-for="category in entry.categories"
        :key="`category-${category.id}`"
        >{{ category.name }}</span
      >
    </div>

    <nav v-if="entry.capabilities?.canSeeGm" class="compendium-entry__tabs">
      <button
        v-if="entry.sourceBacked"
        type="button"
        :class="{ active: audience === 'source' }"
        @click="audience = 'source'"
      >
        {{ $t("vtt.table.compendium.sourceContent") }}
      </button>
      <button
        type="button"
        :class="{ active: audience === 'public' }"
        @click="audience = 'public'"
      >
        {{ $t("vtt.table.compendium.content") }}
      </button>
      <button
        v-if="showCharacterKnowledge"
        type="button"
        :class="{ active: audience === 'knowledge' }"
        @click="audience = 'knowledge'"
      >
        {{ $t("vtt.table.compendium.bestiaryKnowledgeTab") }}
        <span
          v-if="knowledgeAssignmentCount"
          class="compendium-entry__tab-count"
        >
          {{ knowledgeAssignmentCount }}
        </span>
        <span
          v-if="knowledgeDirtyCount"
          class="compendium-entry__tab-draft"
          :aria-label="
            $t('vtt.table.compendium.bestiaryUnsavedCount', {
              count: knowledgeDirtyCount,
            })
          "
        >
          · {{ knowledgeDirtyCount }}
        </span>
      </button>
      <button
        type="button"
        :class="{ active: audience === 'gm' }"
        @click="audience = 'gm'"
      >
        {{ $t("vtt.table.compendium.gmNotes") }}
      </button>
    </nav>

    <CompendiumBestiaryKnowledge
      v-if="showCharacterKnowledge"
      v-show="audience === 'knowledge'"
      :campaign-id="campaignId"
      :entry="entry"
      @navigate="$emit('navigate', $event)"
      @dirty-count="knowledgeDirtyCount = $event"
      @assignment-count="knowledgeAssignmentCount = $event"
      @section-keys="knowledgeSectionKeys = $event"
    />

    <template v-if="audience !== 'knowledge'">
      <dl v-if="visibleFields.length" class="compendium-entry__fields">
        <template v-for="field in visibleFields" :key="field.key">
          <dt>{{ field.label }}</dt>
          <dd>{{ formatField(field.value) }}</dd>
        </template>
      </dl>
      <template v-if="entry.sourceBacked">
        <CompendiumSourceDocument
          v-if="showSourceDocument && entry.sourceHtml"
          :html="entry.sourceHtml"
          :links="entry.wikiLinks || []"
          @navigate="$emit('navigate', $event)"
        />
        <div
          v-else-if="showSourceDocument"
          class="compendium-entry__empty-section"
        >
          {{ $t("vtt.table.compendium.noSelectedPlayerContent") }}
        </div>
        <template v-else-if="audience === 'public'">
          <CompendiumSourceDocument
            v-if="showCharacterKnowledge && curatedSourceHtml"
            :html="curatedSourceHtml"
            :links="entry.wikiLinks || []"
            @navigate="$emit('navigate', $event)"
          />
          <div
            v-else-if="showCharacterKnowledge"
            class="compendium-entry__empty-section"
          >
            {{ $t("vtt.table.compendium.bestiaryNoSelectedContent") }}
          </div>
          <CompendiumDocument
            v-else-if="hasPublicRichContent"
            :content="entry.publicContent"
            :assets="visibleAssets"
            :mentions="entry.mentions || []"
            :campaign-id="campaignId"
            @navigate="$emit('navigate', $event)"
          />
          <div
            v-else-if="entry.playerDescription"
            class="compendium-entry__plain-text"
          >
            {{ entry.playerDescription }}
          </div>
          <div v-else class="compendium-entry__empty-section">
            {{ $t("vtt.table.compendium.noPlayerDescription") }}
          </div>
        </template>
        <template v-else-if="audience === 'gm'">
          <div v-if="hasGmRichContent" class="compendium-entry__gm-note">
            <CompendiumDocument
              :content="entry.gmContent"
              :assets="visibleAssets"
              :mentions="entry.mentions || []"
              :campaign-id="campaignId"
              @navigate="$emit('navigate', $event)"
            />
          </div>
          <div
            v-else-if="entry.gmNotes"
            class="compendium-entry__plain-text compendium-entry__gm-note"
          >
            {{ entry.gmNotes }}
          </div>
          <div v-else class="compendium-entry__empty-section">
            {{ $t("vtt.table.compendium.noGmMaterials") }}
          </div>
        </template>
        <div
          v-if="
            audience === 'source' &&
            (corpusImages.length || corpusDocuments.length)
          "
          class="compendium-entry__gallery"
        >
          <figure v-for="asset in corpusImages" :key="asset.id">
            <img
              v-if="corpusAssetUrls[asset.id]"
              :src="corpusAssetUrls[asset.id]"
              :alt="asset.filename"
              loading="lazy"
            />
            <figcaption>{{ asset.filename }}</figcaption>
          </figure>
          <a
            v-for="asset in corpusDocuments"
            :key="asset.id"
            :href="corpusAssetUrls[asset.id]"
            target="_blank"
            rel="noopener noreferrer"
          >
            {{ asset.filename }}
          </a>
        </div>
        <p v-if="actionMessage" class="compendium-entry__action-message">
          {{ actionMessage }}
        </p>
      </template>
      <CompendiumDocument
        v-else
        :content="audience === 'gm' ? entry.gmContent : entry.publicContent"
        :assets="visibleAssets"
        :mentions="entry.mentions || []"
        :campaign-id="campaignId"
        @navigate="$emit('navigate', $event)"
      />

      <section
        v-if="showContextLinks && entry.relations?.length"
        class="compendium-entry__links"
      >
        <h3>{{ $t("vtt.table.compendium.relations") }}</h3>
        <button
          v-for="relation in entry.relations"
          :key="`${relation.targetEntryId}:${relation.audience}`"
          type="button"
          @click="$emit('navigate', relation.targetEntryId)"
        >
          {{ relation.label }}: {{ relation.title }}
        </button>
      </section>
      <section
        v-if="showContextLinks && entry.backlinks?.length"
        class="compendium-entry__links"
      >
        <h3>{{ $t("vtt.table.compendium.backlinks") }}</h3>
        <button
          v-for="link in entry.backlinks"
          :key="link.sourceEntryId"
          type="button"
          @click="$emit('navigate', link.sourceEntryId)"
        >
          {{ link.title }}
        </button>
      </section>
    </template>

    <dialog :open="showNpc" class="compendium-npc-dialog">
      <form @submit.prevent="createNpc">
        <h3>{{ $t("vtt.table.compendium.createNpc") }}</h3>
        <label
          >{{ $t("vtt.table.compendium.npcName")
          }}<input
            v-model.trim="npcName"
            required
            minlength="2"
            maxlength="150"
        /></label>
        <label v-if="sceneId"
          ><input v-model="createToken" type="checkbox" />
          {{ $t("vtt.table.compendium.addToken") }}</label
        >
        <p v-if="npcError" class="compendium-entry__error">{{ npcError }}</p>
        <div>
          <button type="submit" :disabled="npcBusy">
            {{ $t("vtt.table.compendium.create") }}</button
          ><button type="button" @click="showNpc = false">
            {{ $t("vtt.table.compendium.cancel") }}
          </button>
        </div>
      </form>
    </dialog>
  </article>
</template>

<script>
import CompendiumDocument from "./CompendiumDocument.vue";
import CompendiumSourceDocument from "./CompendiumSourceDocument.vue";
import CompendiumBestiaryKnowledge from "./CompendiumBestiaryKnowledge.vue";
import { compendiumApiClient } from "@/lib/compendium/compendiumApiClient";
import { selectSourceSections } from "@/lib/compendium/sourceSectionSelection";

const hasTextualContent = (node) => {
  if (!node || typeof node !== "object") return false;
  if (["text", "image", "attachment", "compendiumMention"].includes(node.type))
    return true;
  return Array.isArray(node.content) && node.content.some(hasTextualContent);
};

export default {
  name: "CompendiumEntryView",
  components: {
    CompendiumBestiaryKnowledge,
    CompendiumDocument,
    CompendiumSourceDocument,
  },
  props: {
    entry: { type: Object, required: true },
    types: { type: Array, default: () => [] },
    campaignId: { type: [Number, String], default: null },
    characterId: { type: [Number, String], default: null },
    universeId: { type: [Number, String], default: null },
    sceneId: { type: [Number, String], default: null },
    tokenX: { type: Number, default: 0 },
    tokenY: { type: Number, default: 0 },
    editorial: { type: Boolean, default: false },
    showContextLinks: { type: Boolean, default: true },
    allowCampaignReveal: { type: Boolean, default: true },
    manageCharacterKnowledge: { type: Boolean, default: false },
  },
  emits: ["navigate", "edit", "materialized", "changed"],
  data() {
    return {
      audience:
        this.manageCharacterKnowledge &&
        this.entry.capabilities?.canSeeGm &&
        this.types.some(
          (type) =>
            type.code === "creature" &&
            Number(type.id) === Number(this.entry.typeId),
        )
          ? "knowledge"
          : this.entry.sourceBacked && this.entry.capabilities?.canSeeGm
            ? "source"
            : "public",
      showNpc: false,
      npcName: this.entry.title,
      createToken: Boolean(this.sceneId),
      npcBusy: false,
      npcError: "",
      actionMessage: "",
      corpusAssetUrls: {},
      knowledgeDirtyCount: 0,
      knowledgeAssignmentCount: 0,
      knowledgeSectionKeys: [],
    };
  },
  computed: {
    currentType() {
      return this.types.find(
        (type) => Number(type.id) === Number(this.entry.typeId),
      );
    },
    typeName() {
      return this.currentType?.name || "Compendium";
    },
    isCreature() {
      return this.currentType?.code === "creature";
    },
    showCharacterKnowledge() {
      return (
        this.manageCharacterKnowledge &&
        this.isCreature &&
        Number(this.campaignId) > 0 &&
        this.entry.capabilities?.canSeeGm === true
      );
    },
    canCreateNpc() {
      return (
        this.isCreature &&
        this.entry.capabilities?.canMaterialize &&
        this.campaignId &&
        (this.entry.statBlocks || []).length > 0
      );
    },
    showSourceDocument() {
      return (
        this.entry.sourceBacked &&
        (!this.entry.capabilities?.canSeeGm || this.audience === "source")
      );
    },
    hasPublicRichContent() {
      return hasTextualContent(this.entry.publicContent);
    },
    hasGmRichContent() {
      return hasTextualContent(this.entry.gmContent);
    },
    curatedSourceHtml() {
      return selectSourceSections(
        String(this.entry.sourceHtml || ""),
        this.knowledgeSectionKeys,
      );
    },
    visibleFields() {
      if (this.entry.sourceBacked && !this.entry.capabilities?.canSeeGm) {
        return [];
      }
      const values =
        this.audience === "gm" ? this.entry.gmFields : this.entry.publicFields;
      return (this.currentType?.fields || [])
        .filter(
          (field) =>
            field.audience === this.audience &&
            values &&
            Object.prototype.hasOwnProperty.call(values, field.key),
        )
        .map((field) => ({ ...field, value: values[field.key] }));
    },
    visibleAssets() {
      return (this.entry.assets || []).filter((asset) =>
        this.audience === "gm"
          ? asset.audience === "gm"
          : asset.audience === "public",
      );
    },
    corpusImages() {
      return (this.entry.corpusAssets || []).filter(
        (asset) => asset.available && asset.mimeType?.startsWith("image/"),
      );
    },
    corpusDocuments() {
      return (this.entry.corpusAssets || []).filter(
        (asset) =>
          asset.available &&
          asset.mimeType === "application/pdf" &&
          this.corpusAssetUrls[asset.id],
      );
    },
  },
  watch: {
    entry(next) {
      this.npcName = next.title;
      this.audience = this.showCharacterKnowledge
        ? "knowledge"
        : next.sourceBacked && next.capabilities?.canSeeGm
          ? "source"
          : "public";
      this.showNpc = false;
      this.actionMessage = "";
      this.knowledgeDirtyCount = 0;
      this.knowledgeAssignmentCount = 0;
      this.knowledgeSectionKeys = [];
      this.loadCorpusImages();
    },
  },
  mounted() {
    this.loadCorpusImages();
  },
  beforeUnmount() {
    this.clearCorpusImages();
  },
  methods: {
    clearCorpusImages() {
      Object.values(this.corpusAssetUrls).forEach((url) =>
        URL.revokeObjectURL(url),
      );
      this.corpusAssetUrls = {};
    },
    async loadCorpusImages() {
      this.clearCorpusImages();
      const urls = {};
      await Promise.all(
        (this.entry.corpusAssets || [])
          .filter((asset) => asset.available)
          .map(async (asset) => {
            try {
              const blob = await compendiumApiClient.fetchCorpusAssetBlob(
                asset.id,
                {
                  campaignId: this.campaignId,
                  characterId: this.characterId,
                  universeId: this.editorial ? this.universeId : null,
                },
              );
              urls[asset.id] = URL.createObjectURL(blob);
            } catch (_error) {
              // A missing illustration never blocks reading the article.
            }
          }),
      );
      this.corpusAssetUrls = urls;
    },
    formatField(value) {
      if (typeof value === "boolean")
        return value
          ? this.$t("vtt.table.compendium.yes")
          : this.$t("vtt.table.compendium.no");
      if (Array.isArray(value)) return value.join(", ");
      if (value && typeof value === "object") return JSON.stringify(value);
      return String(value ?? "");
    },
    async createNpc() {
      this.npcBusy = true;
      this.npcError = "";
      try {
        const response = await compendiumApiClient.materialize(
          this.campaignId,
          this.entry.id,
          {
            name: this.npcName,
            createToken: this.createToken,
            sceneId: this.createToken ? Number(this.sceneId) : null,
            x: this.tokenX,
            y: this.tokenY,
          },
        );
        this.showNpc = false;
        this.$emit("materialized", response);
      } catch (error) {
        this.npcError = error?.payload?.message || error?.message || "Error";
      } finally {
        this.npcBusy = false;
      }
    },
    async toggleFavorite() {
      try {
        await compendiumApiClient.favorite(
          this.campaignId,
          this.entry.id,
          !this.entry.favorite,
        );
        this.$emit("changed", this.entry.id);
      } catch (error) {
        this.actionMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    async pinEntry() {
      try {
        await compendiumApiClient.pin(this.campaignId, this.entry.id);
        this.actionMessage = this.$t("vtt.table.compendium.pinned");
      } catch (error) {
        this.actionMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    async revealEntry() {
      try {
        await compendiumApiClient.reveal(this.campaignId, this.entry.id);
        this.$emit("changed", this.entry.id);
      } catch (error) {
        this.actionMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
    async revokeReveal() {
      try {
        await compendiumApiClient.revokeReveal(this.campaignId, this.entry.id);
        this.$emit("changed", this.entry.id);
      } catch (error) {
        this.actionMessage =
          error?.payload?.message || error?.message || "Error";
      }
    },
  },
};
</script>
