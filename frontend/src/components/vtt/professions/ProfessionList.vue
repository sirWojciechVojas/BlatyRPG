<template>
  <section
    class="profession-list"
    :aria-label="$t('vtt.table.professions.catalog')"
  >
    <div class="profession-list__controls">
      <label class="profession-list__search">
        <span class="profession-visually-hidden">{{
          $t("vtt.table.professions.searchLabel")
        }}</span>
        <input
          :value="state.query"
          type="search"
          :placeholder="$t('vtt.table.professions.search')"
          @input="setQuery($event.target.value)"
        />
      </label>

      <div
        class="profession-list__filters"
        role="group"
        :aria-label="$t('vtt.table.professions.type')"
      >
        <button
          v-for="filter in filters"
          :key="filter.id"
          type="button"
          :class="{ 'is-active': state.type === filter.id }"
          :aria-pressed="state.type === filter.id ? 'true' : 'false'"
          @click="setType(filter.id)"
        >
          <span>{{ $t(filter.labelKey) }}</span>
          <b>{{ filter.count }}</b>
        </button>
      </div>
    </div>

    <div
      v-if="state.phase === 'loading'"
      class="profession-state"
      role="status"
    >
      {{ $t("vtt.table.professions.loading") }}
    </div>
    <div
      v-else-if="state.phase === 'error'"
      class="profession-state profession-state--error"
      role="alert"
    >
      <strong>{{ errorTitle }}</strong>
      <button type="button" @click="retry">
        {{ $t("vtt.table.professions.retry") }}
      </button>
    </div>
    <div
      v-else-if="state.phase === 'ready' && !state.items.length"
      class="profession-state"
    >
      {{ $t("vtt.table.professions.emptyCatalog") }}
    </div>
    <div v-else-if="!filtered.length" class="profession-state">
      <strong>{{ $t("vtt.table.professions.noMatches") }}</strong>
      <button type="button" @click="clearFilters">
        {{ $t("vtt.table.professions.clearFilters") }}
      </button>
    </div>

    <ol
      v-else
      class="profession-list__items"
      :aria-label="$t('vtt.table.professions.results')"
    >
      <li v-for="profession in visible" :key="profession.id">
        <button
          type="button"
          :class="{ 'is-selected': Number(profession.id) === state.selectedId }"
          :aria-pressed="
            Number(profession.id) === state.selectedId ? 'true' : 'false'
          "
          @click="select(profession.id)"
          @dblclick="open(profession.id)"
        >
          <span>
            <strong>{{ displayName(profession.name) }}</strong>
            <small v-if="duplicateCount(profession.name) > 1">
              {{
                $t("vtt.table.professions.entryNumber", { id: profession.id })
              }}
            </small>
          </span>
          <em :class="{ 'is-advanced': profession.is_advanced === true }">
            {{
              $t(
                profession.is_advanced
                  ? "vtt.table.professions.advanced"
                  : "vtt.table.professions.basic",
              )
            }}
          </em>
        </button>
      </li>
    </ol>

    <footer v-if="filtered.length" class="profession-list__pagination">
      <span>{{ resultRange }}</span>
      <div>
        <button
          type="button"
          :disabled="currentPage <= 1"
          :aria-label="$t('vtt.table.professions.previousPage')"
          @click="setPage(currentPage - 1)"
        >
          ‹
        </button>
        <b>{{ currentPage }} / {{ pageCount }}</b>
        <button
          type="button"
          :disabled="currentPage >= pageCount"
          :aria-label="$t('vtt.table.professions.nextPage')"
          @click="setPage(currentPage + 1)"
        >
          ›
        </button>
      </div>
    </footer>
  </section>
</template>

<script>
import {
  displayProfessionName,
  normalizeProfessionText,
  professionCollator,
  professionSearchText,
} from "./professionPresentation";

const PAGE_SIZE = 12;

export default {
  name: "ProfessionList",
  emits: ["open-window"],
  props: {
    campaignId: { type: [Number, String], required: true },
  },
  computed: {
    state() {
      return this.$store.state.professions;
    },
    duplicateNames() {
      return this.state.items.reduce((counts, profession) => {
        const key = normalizeProfessionText(profession.name);
        counts[key] = (counts[key] || 0) + 1;
        return counts;
      }, {});
    },
    searchMatches() {
      const query = normalizeProfessionText(this.state.query);
      return [...this.state.items]
        .filter(
          (profession) =>
            !query || professionSearchText(profession).includes(query),
        )
        .sort(
          (left, right) =>
            professionCollator.compare(left.name, right.name) ||
            Number(left.id) - Number(right.id),
        );
    },
    filtered() {
      return this.searchMatches.filter((profession) => {
        if (this.state.type === "advanced")
          return profession.is_advanced === true;
        if (this.state.type === "basic") return profession.is_advanced !== true;
        return true;
      });
    },
    pageCount() {
      return Math.max(1, Math.ceil(this.filtered.length / PAGE_SIZE));
    },
    currentPage() {
      return Math.min(this.state.page, this.pageCount);
    },
    visible() {
      const offset = (this.currentPage - 1) * PAGE_SIZE;
      return this.filtered.slice(offset, offset + PAGE_SIZE);
    },
    filters() {
      return [
        {
          id: "all",
          labelKey: "vtt.table.professions.all",
          count: this.searchMatches.length,
        },
        {
          id: "basic",
          labelKey: "vtt.table.professions.basicPlural",
          count: this.searchMatches.filter((item) => item.is_advanced !== true)
            .length,
        },
        {
          id: "advanced",
          labelKey: "vtt.table.professions.advancedPlural",
          count: this.searchMatches.filter((item) => item.is_advanced === true)
            .length,
        },
      ];
    },
    resultRange() {
      if (!this.filtered.length)
        return this.$t("vtt.table.professions.zeroResults");
      const first = (this.currentPage - 1) * PAGE_SIZE + 1;
      const last = Math.min(this.currentPage * PAGE_SIZE, this.filtered.length);
      return this.$t("vtt.table.professions.resultRange", {
        first,
        last,
        total: this.filtered.length,
      });
    },
    errorTitle() {
      const status = Number(this.state.error?.status || 0);
      if (status === 401) return this.$t("vtt.table.professions.unauthorized");
      if (status === 403) return this.$t("vtt.table.professions.forbidden");
      if (status === 404)
        return this.$t("vtt.table.professions.systemNotFound");
      if (this.state.error?.network)
        return this.$t("vtt.table.professions.networkError");
      return this.$t("vtt.table.professions.loadFailed");
    },
  },
  watch: {
    pageCount(value) {
      if (this.state.page > value) this.setPage(value);
    },
  },
  methods: {
    displayName: displayProfessionName,
    duplicateCount(name) {
      return this.duplicateNames[normalizeProfessionText(name)] || 0;
    },
    setQuery(query) {
      this.$store.commit("professions/SET_QUERY", query);
    },
    setType(type) {
      this.$store.commit("professions/SET_TYPE", type);
    },
    setPage(page) {
      this.$store.commit("professions/SET_PAGE", page);
    },
    select(id) {
      this.$store.commit("professions/SELECT", id);
    },
    open(id) {
      this.select(id);
      this.$emit("open-window");
    },
    clearFilters() {
      this.setQuery("");
      this.setType("all");
    },
    retry() {
      this.$store.dispatch("professions/loadCatalog", {
        campaignId: this.campaignId,
        force: true,
      });
    },
  },
};
</script>
