<template>
  <section
    class="professions-content"
    :class="{ 'professions-content--compact': compact }"
    :aria-label="$t('vtt.table.professions.title')"
  >
    <header class="professions-content__toolbar">
      <nav
        class="professions-content__context"
        :aria-label="$t('vtt.table.professions.views')"
      >
        <button
          type="button"
          :class="{ 'is-active': state.view === 'catalog' }"
          :aria-pressed="state.view === 'catalog' ? 'true' : 'false'"
          @click="setView('catalog')"
        >
          {{ $t("vtt.table.professions.catalog") }}
        </button>
        <button
          type="button"
          :class="{ 'is-active': state.view === 'character' }"
          :aria-pressed="state.view === 'character' ? 'true' : 'false'"
          @click="setView('character')"
        >
          {{ $t("vtt.table.professions.characterProfession") }}
        </button>
      </nav>
    </header>

    <div v-if="state.view === 'catalog'" class="professions-content__workspace">
      <ProfessionList
        v-show="!compact || !selected"
        class="professions-content__list"
        :campaign-id="campaignId"
        @open-window="$emit('open-window')"
      />
      <ProfessionDetails
        v-if="selected"
        class="professions-content__details"
        :compact="compact"
        @open-window="$emit('open-window')"
      />
      <div
        v-else-if="!compact && state.phase === 'ready'"
        class="professions-content__welcome"
      >
        <span aria-hidden="true">⌘</span>
        <h3>{{ $t("vtt.table.professions.selectTitle") }}</h3>
        <p>{{ $t("vtt.table.professions.selectBody") }}</p>
      </div>
    </div>

    <CharacterProfessions
      v-else
      :campaign-id="campaignId"
      :character-id="characterId"
    />
  </section>
</template>

<script>
import CharacterProfessions from "./CharacterProfessions.vue";
import ProfessionDetails from "./ProfessionDetails.vue";
import ProfessionList from "./ProfessionList.vue";

export default {
  name: "ProfessionsContent",
  components: { CharacterProfessions, ProfessionDetails, ProfessionList },
  props: {
    campaignId: { type: [Number, String], required: true },
    characterId: { type: [Number, String], default: null },
    compact: { type: Boolean, default: false },
  },
  emits: ["open-window"],
  computed: {
    state() {
      return this.$store.state.professions;
    },
    selected() {
      return this.$store.getters["professions/selected"];
    },
  },
  watch: {
    campaignId: {
      immediate: true,
      handler() {
        this.loadCatalog();
        this.loadCharacter();
      },
    },
    characterId() {
      this.loadCharacter();
    },
  },
  methods: {
    setView(view) {
      this.$store.commit("professions/SET_VIEW", view);
      if (view === "character") this.loadCharacter();
    },
    loadCatalog() {
      this.$store.dispatch("professions/loadCatalog", {
        campaignId: this.campaignId,
      });
    },
    loadCharacter() {
      this.$store.dispatch("professions/loadCharacter", {
        campaignId: this.campaignId,
        characterId: this.characterId,
      });
    },
  },
};
</script>

<style src="./professions.css"></style>
