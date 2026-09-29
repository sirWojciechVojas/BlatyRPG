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
          v-if="entry.capabilities?.canEdit && editorial"
          type="button"
          @click="$emit('edit')"
        >
          {{ $t("vtt.table.compendium.edit") }}
        </button>
        <button
          v-if="isCreature && entry.capabilities?.canMaterialize && campaignId"
          type="button"
          @click="showNpc = true"
        >
          {{ $t("vtt.table.compendium.createNpc") }}
        </button>
      </div>
    </header>

    <div v-if="entry.tags?.length" class="compendium-entry__tags">
      <span v-for="tag in entry.tags" :key="tag.id">{{ tag.name }}</span>
    </div>

    <nav v-if="entry.capabilities?.canSeeGm" class="compendium-entry__tabs">
      <button
        type="button"
        :class="{ active: audience === 'public' }"
        @click="audience = 'public'"
      >
        {{ $t("vtt.table.compendium.playerKnowledge") }}
      </button>
      <button
        type="button"
        :class="{ active: audience === 'gm' }"
        @click="audience = 'gm'"
      >
        {{ $t("vtt.table.compendium.gmNotes") }}
      </button>
    </nav>

    <dl v-if="visibleFields.length" class="compendium-entry__fields">
      <template v-for="field in visibleFields" :key="field.key">
        <dt>{{ field.label }}</dt>
        <dd>{{ formatField(field.value) }}</dd>
      </template>
    </dl>
    <CompendiumDocument
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
import { compendiumApiClient } from "@/lib/compendium/compendiumApiClient";

export default {
  name: "CompendiumEntryView",
  components: { CompendiumDocument },
  props: {
    entry: { type: Object, required: true },
    types: { type: Array, default: () => [] },
    campaignId: { type: [Number, String], default: null },
    sceneId: { type: [Number, String], default: null },
    tokenX: { type: Number, default: 0 },
    tokenY: { type: Number, default: 0 },
    editorial: { type: Boolean, default: false },
    showContextLinks: { type: Boolean, default: true },
  },
  emits: ["navigate", "edit", "materialized"],
  data() {
    return {
      audience: "public",
      showNpc: false,
      npcName: this.entry.title,
      createToken: Boolean(this.sceneId),
      npcBusy: false,
      npcError: "",
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
    visibleFields() {
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
  },
  watch: {
    entry(next) {
      this.npcName = next.title;
      this.audience = "public";
      this.showNpc = false;
    },
  },
  methods: {
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
  },
};
</script>
