<template>
  <section
    class="character-bestiary"
    :class="`character-bestiary--${resolvedVariant}`"
    :aria-label="$t('vtt.bestiary.title')"
    :aria-busy="loading || entryLoading ? 'true' : 'false'"
  >
    <header class="character-bestiary__header">
      <div>
        <p>{{ $t("vtt.bestiary.kicker") }}</p>
        <h2>{{ $t("vtt.bestiary.catalogTitle") }}</h2>
      </div>
      <label class="character-bestiary__search">
        <span class="bestiary-visually-hidden">{{
          $t("vtt.bestiary.searchLabel")
        }}</span>
        <input
          v-model.trim="search"
          type="search"
          :placeholder="$t('vtt.bestiary.search')"
        />
      </label>
      <p v-if="resolvedVariant !== 'wood'" class="character-bestiary__progress">
        {{
          $t("vtt.bestiary.progress", {
            encountered: counts.encountered,
            total: counts.total,
          })
        }}
      </p>
      <div
        v-else
        class="character-bestiary__metrics"
        :aria-label="
          $t('vtt.bestiary.progress', {
            encountered: counts.encountered,
            total: counts.total,
          })
        "
      >
        <span :title="$t('vtt.bestiary.levelFull')">
          <strong>{{ counts.encountered }}</strong>
          <small>{{ $t("vtt.bestiary.levelFull") }}</small>
        </span>
        <span :title="$t('vtt.bestiary.levelSummary')">
          <strong>{{ counts.summary }}</strong>
          <small>{{ $t("vtt.bestiary.levelSummary") }}</small>
        </span>
        <span :title="$t('vtt.bestiary.levelUnknown')">
          <strong>{{ counts.unknown }}</strong>
          <small>{{ $t("vtt.bestiary.levelUnknown") }}</small>
        </span>
      </div>
    </header>

    <div v-if="!characterId" class="character-bestiary__state" role="status">
      <strong>{{ $t("vtt.bestiary.noCharacterTitle") }}</strong>
      <span>{{ $t("vtt.bestiary.noCharacterBody") }}</span>
    </div>
    <div v-else-if="loading" class="character-bestiary__state" role="status">
      {{ $t("vtt.bestiary.loading") }}
    </div>
    <div
      v-else-if="listError"
      class="character-bestiary__state character-bestiary__state--error"
      role="alert"
    >
      <strong>{{ $t("vtt.bestiary.loadFailed") }}</strong>
      <button type="button" @click="loadBestiary">
        {{ $t("vtt.bestiary.retry") }}
      </button>
    </div>

    <div v-else class="character-bestiary__body">
      <aside
        class="character-bestiary__index"
        :aria-label="$t('vtt.bestiary.indexLabel')"
      >
        <section class="character-bestiary__group">
          <h3>
            <span>{{ $t("vtt.bestiary.encountered") }}</span>
            <small>{{ filteredEncountered.length }}</small>
          </h3>
          <p v-if="!filteredEncountered.length" class="bestiary-empty-group">
            {{ $t("vtt.bestiary.noEncountered") }}
          </p>
          <button
            v-for="item in filteredEncountered"
            :key="`encountered-${item.id}`"
            type="button"
            class="bestiary-entry-link"
            :class="{
              'bestiary-entry-link--active': selectedId === item.id,
            }"
            @click="selectEntry(item.id)"
          >
            <span class="bestiary-entry-link__mark" aria-hidden="true">◆</span>
            <span>
              <strong>{{ item.title }}</strong>
              <small>{{ $t("vtt.bestiary.levelFull") }}</small>
            </span>
          </button>
        </section>

        <section
          class="character-bestiary__group character-bestiary__group--locked"
        >
          <h3>
            <span>{{ $t("vtt.bestiary.remaining") }}</span>
            <small>{{ filteredRemaining.length }}</small>
          </h3>
          <p v-if="!filteredRemaining.length" class="bestiary-empty-group">
            {{
              $t(
                counts.total
                  ? "vtt.bestiary.noRemaining"
                  : "vtt.bestiary.noSharedCreatures",
              )
            }}
          </p>
          <button
            v-for="item in filteredRemaining"
            :key="`remaining-${item.id}`"
            type="button"
            class="bestiary-entry-link"
            :class="{
              'bestiary-entry-link--locked': item.level === 'unknown',
              'bestiary-entry-link--active': lockedId === item.id,
            }"
            :aria-disabled="item.level === 'unknown' ? 'true' : undefined"
            @click="selectRemaining(item)"
          >
            <span class="bestiary-entry-link__mark" aria-hidden="true">{{
              item.level === "summary" ? "◇" : "▣"
            }}</span>
            <span>
              <strong>{{ itemTitle(item) }}</strong>
              <small>{{ itemLevelLabel(item) }}</small>
            </span>
          </button>
        </section>
      </aside>

      <main class="character-bestiary__document">
        <div
          v-if="entryLoading"
          class="character-bestiary__state"
          role="status"
        >
          {{ $t("vtt.bestiary.entryLoading") }}
        </div>
        <div
          v-else-if="entryError"
          class="character-bestiary__state character-bestiary__state--error"
          role="alert"
        >
          <strong>{{ $t("vtt.bestiary.entryLoadFailed") }}</strong>
          <button type="button" @click="reloadEntry">
            {{ $t("vtt.bestiary.retry") }}
          </button>
        </div>
        <div
          v-else-if="lockedSelection"
          class="character-bestiary__locked-card"
        >
          <span aria-hidden="true">?</span>
          <p>{{ $t("vtt.bestiary.lockedKicker") }}</p>
          <h3>{{ itemTitle(lockedSelection) }}</h3>
          <div aria-hidden="true">♜</div>
          <strong>{{ $t("vtt.bestiary.lockedTitle") }}</strong>
          <small>{{ $t("vtt.bestiary.lockedBody") }}</small>
        </div>
        <article
          v-else-if="summarySelection"
          class="character-bestiary__summary-card"
        >
          <p>{{ $t("vtt.bestiary.summaryKicker") }}</p>
          <h3>{{ summarySelection.title }}</h3>
          <p class="character-bestiary__summary-text">
            {{
              summarySelection.excerpt || $t("vtt.bestiary.summaryUnavailable")
            }}
          </p>
          <small>{{ $t("vtt.bestiary.summaryHint") }}</small>
        </article>
        <CompendiumEntryView
          v-else-if="entry"
          :entry="entry"
          :types="types"
          :campaign-id="campaignId"
          :character-id="characterId"
          :show-context-links="false"
          @navigate="navigateEntry"
        />
        <div v-else class="character-bestiary__welcome">
          <span aria-hidden="true">♞</span>
          <h3>{{ $t("vtt.bestiary.selectTitle") }}</h3>
          <p>{{ $t("vtt.bestiary.selectBody") }}</p>
        </div>
      </main>
    </div>

    <footer class="character-bestiary__footer">
      <span>{{ character?.name || "—" }}</span>
      <span>{{ $t("vtt.bestiary.footerHint") }}</span>
    </footer>
  </section>
</template>

<script>
import { bestiaryApiClient } from "@/lib/bestiary/bestiaryApiClient";
import CompendiumEntryView from "@/components/vtt/compendium/CompendiumEntryView.vue";

const normalized = (value) =>
  String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/gu, "")
    .toLocaleLowerCase();

export default {
  name: "BestiaryContent",
  components: { CompendiumEntryView },
  props: {
    campaignId: { type: [Number, String], required: true },
    characterId: { type: [Number, String], default: null },
    variant: {
      type: String,
      default: "parchment",
      validator: (value) => ["parchment", "wood"].includes(value),
    },
  },
  data: () => ({
    loading: false,
    entryLoading: false,
    listError: null,
    entryError: null,
    character: null,
    creatureType: null,
    encountered: [],
    remaining: [],
    counts: { encountered: 0, summary: 0, unknown: 0, remaining: 0, total: 0 },
    entry: null,
    selectedId: null,
    lockedSelection: null,
    summarySelection: null,
    lockedId: null,
    search: "",
    requestSequence: 0,
    entryRequestSequence: 0,
  }),
  computed: {
    resolvedVariant() {
      return this.variant === "wood" ? "wood" : "parchment";
    },
    types() {
      return this.creatureType ? [this.creatureType] : [];
    },
    filteredEncountered() {
      return this.filterItems(this.encountered);
    },
    filteredRemaining() {
      return this.filterItems(this.remaining);
    },
  },
  watch: {
    campaignId: "resetAndLoad",
    characterId: "resetAndLoad",
  },
  mounted() {
    this.loadBestiary();
  },
  beforeUnmount() {
    this.requestSequence += 1;
    this.entryRequestSequence += 1;
  },
  methods: {
    resetAndLoad() {
      this.requestSequence += 1;
      this.entryRequestSequence += 1;
      this.character = null;
      this.creatureType = null;
      this.encountered = [];
      this.remaining = [];
      this.counts = {
        encountered: 0,
        summary: 0,
        unknown: 0,
        remaining: 0,
        total: 0,
      };
      this.entry = null;
      this.selectedId = null;
      this.lockedSelection = null;
      this.summarySelection = null;
      this.lockedId = null;
      this.listError = null;
      this.entryError = null;
      this.loadBestiary();
    },
    async loadBestiary() {
      if (!Number(this.campaignId) || !Number(this.characterId)) return;
      const sequence = ++this.requestSequence;
      this.loading = true;
      this.listError = null;
      try {
        const result = await bestiaryApiClient.list(
          this.campaignId,
          this.characterId,
        );
        if (sequence !== this.requestSequence) return;
        this.character = result.character || null;
        this.creatureType = result.type || null;
        this.encountered = Array.isArray(result.encountered)
          ? result.encountered
          : [];
        this.remaining = Array.isArray(result.remaining)
          ? result.remaining
          : [];
        const summaryCount = this.remaining.filter(
          (item) => item.level === "summary",
        ).length;
        const unknownCount = this.remaining.length - summaryCount;
        const responseCounts = result.counts || {};
        this.counts = {
          encountered:
            Number(responseCounts.encountered) || this.encountered.length,
          summary: Number(responseCounts.summary) || summaryCount,
          unknown: Number(responseCounts.unknown) || unknownCount,
          remaining: Number(responseCounts.remaining) || this.remaining.length,
          total:
            Number(responseCounts.total) ||
            this.encountered.length + this.remaining.length,
        };
        if (
          this.selectedId &&
          !this.encountered.some(
            (item) => Number(item.id) === Number(this.selectedId),
          )
        ) {
          this.entry = null;
          this.selectedId = null;
        }
      } catch (error) {
        if (sequence === this.requestSequence) this.listError = error;
      } finally {
        if (sequence === this.requestSequence) this.loading = false;
      }
    },
    filterItems(items) {
      const query = normalized(this.search);
      if (!query) return items;
      return items.filter((item) =>
        normalized(this.itemTitle(item)).includes(query),
      );
    },
    async selectEntry(entryId) {
      const id = Number(entryId);
      if (!id) return;
      const sequence = ++this.entryRequestSequence;
      this.selectedId = id;
      this.lockedId = null;
      this.lockedSelection = null;
      this.summarySelection = null;
      this.entry = null;
      this.entryError = null;
      this.entryLoading = true;
      try {
        const result = await bestiaryApiClient.entry(
          this.campaignId,
          this.characterId,
          id,
        );
        if (sequence === this.entryRequestSequence) {
          this.entry = result.entry || null;
        }
      } catch (error) {
        if (sequence === this.entryRequestSequence) this.entryError = error;
      } finally {
        if (sequence === this.entryRequestSequence) this.entryLoading = false;
      }
    },
    selectLocked(item) {
      this.entryRequestSequence += 1;
      this.entryLoading = false;
      this.entryError = null;
      this.entry = null;
      this.selectedId = null;
      this.lockedId = Number(item.id);
      this.lockedSelection = item;
      this.summarySelection = null;
    },
    selectSummary(item) {
      this.entryRequestSequence += 1;
      this.entryLoading = false;
      this.entryError = null;
      this.entry = null;
      this.selectedId = null;
      this.lockedId = Number(item.id);
      this.lockedSelection = null;
      this.summarySelection = item;
    },
    selectRemaining(item) {
      if (item?.level === "summary") {
        this.selectSummary(item);
        return;
      }
      this.selectLocked(item);
    },
    itemTitle(item) {
      return item?.level === "unknown" || !item?.title
        ? this.$t("vtt.bestiary.unknownCreature")
        : item.title;
    },
    itemLevelLabel(item) {
      return item?.level === "summary"
        ? this.$t("vtt.bestiary.levelSummary")
        : this.$t("vtt.bestiary.levelUnknown");
    },
    reloadEntry() {
      if (this.selectedId) this.selectEntry(this.selectedId);
    },
    navigateEntry(entryId) {
      const known = this.encountered.find(
        (item) => Number(item.id) === Number(entryId),
      );
      if (known) {
        this.selectEntry(known.id);
        return;
      }
      const locked = this.remaining.find(
        (item) => Number(item.id) === Number(entryId),
      );
      if (locked) this.selectRemaining(locked);
    },
  },
};
</script>

<style src="./bestiary.css"></style>
<style src="../compendium/compendium.css"></style>
