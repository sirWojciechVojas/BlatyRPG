<template>
  <section class="character-development">
    <fieldset class="character-development__profession">
      <legend>{{ $t("characters.sections.profession") }}</legend>
      <div class="character-development__profession-row">
        <ProfessionAutocomplete
          v-model="selectedProfessionId"
          :input-id="`sheet-profession-${characterId}`"
          :professions="professionOptions"
          :disabled="!canManageProfession || professionSaving"
        />
      </div>
      <small v-if="professionChanged && !professionMessage" class="is-pending">
        {{ $t("characters.development.professionPending") }}
      </small>
      <small v-if="professionMessage" :class="`is-${professionMessage.type}`">
        {{ $t(professionMessage.key) }}
      </small>
    </fieldset>

    <fieldset>
      <legend>{{ $t("characters.sections.skills") }}</legend>
      <CharacterDefinitionPicker
        :model-value="skills"
        :options="skillOptions"
        :placeholder="$t('characters.development.searchSkill')"
        :disabled="disabled"
        :loading="definitionsLoading"
        @update:model-value="$emit('update:skills', $event)"
      />
    </fieldset>

    <fieldset>
      <legend>{{ $t("characters.sections.talents") }}</legend>
      <CharacterDefinitionPicker
        :model-value="talents"
        :options="talentOptions"
        :placeholder="$t('characters.development.searchTalent')"
        :disabled="disabled"
        :loading="definitionsLoading"
        @update:model-value="$emit('update:talents', $event)"
      />
    </fieldset>

    <p v-if="definitionsError" class="character-sheet-error" role="alert">
      {{ $t("characters.development.catalogError") }}
    </p>
  </section>
</template>

<script>
import ProfessionAutocomplete from "@/components/vtt/professions/ProfessionAutocomplete.vue";
import { displayProfessionName } from "@/components/vtt/professions/professionPresentation";
import { characterDevelopmentApiClient } from "@/lib/character/characterDevelopmentApiClient";
import CharacterDefinitionPicker from "./CharacterDefinitionPicker.vue";

export default {
  name: "CharacterDevelopmentEditor",
  components: { CharacterDefinitionPicker, ProfessionAutocomplete },
  props: {
    campaignId: { type: [Number, String], default: null },
    characterId: { type: [Number, String], default: null },
    systemId: { type: [Number, String], default: null },
    skills: { type: Array, default: () => [] },
    talents: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
  },
  emits: ["update:skills", "update:talents", "profession-changed"],
  data: () => ({
    selectedProfessionId: null,
    professionSaving: false,
    professionMessage: null,
    definitionsLoading: false,
    definitionsError: false,
    skillOptions: [],
    talentOptions: [],
    definitionRequest: 0,
  }),
  computed: {
    professionState() {
      return this.$store.state.professions.character;
    },
    canManageProfession() {
      return (
        !this.disabled &&
        this.professionState.capabilities?.canManageProfession === true
      );
    },
    professionOptions() {
      return [...this.$store.state.professions.items].sort((left, right) =>
        displayProfessionName(left.name).localeCompare(
          displayProfessionName(right.name),
          "pl",
          { sensitivity: "base" },
        ),
      );
    },
    professionChanged() {
      return (
        Number(this.selectedProfessionId) > 0 &&
        Number(this.selectedProfessionId) !==
          Number(this.professionState.current?.professionId)
      );
    },
  },
  watch: {
    campaignId: { handler: "loadProfession" },
    characterId: { immediate: true, handler: "loadProfession" },
    systemId: { immediate: true, handler: "loadDefinitions" },
    "professionState.current.professionId": {
      immediate: true,
      handler(value) {
        this.selectedProfessionId = value ? Number(value) : null;
      },
    },
  },
  methods: {
    save() {
      return this.saveProfession();
    },
    async loadProfession() {
      if (!Number(this.campaignId) || !Number(this.characterId)) return;
      this.professionMessage = null;
      await Promise.all([
        this.$store.dispatch("professions/loadCatalog", {
          campaignId: this.campaignId,
        }),
        this.$store.dispatch("professions/loadCharacter", {
          campaignId: this.campaignId,
          characterId: this.characterId,
        }),
      ]);
    },
    async loadDefinitions() {
      const systemId = Number(this.systemId);
      const request = ++this.definitionRequest;
      this.skillOptions = [];
      this.talentOptions = [];
      this.definitionsError = false;
      if (!systemId) return;
      this.definitionsLoading = true;
      try {
        const [skills, talents] = await Promise.all([
          characterDevelopmentApiClient.definitions(systemId, "umiejetnosc"),
          characterDevelopmentApiClient.definitions(systemId, "zdolnosc"),
        ]);
        if (request !== this.definitionRequest) return;
        this.skillOptions = skills;
        this.talentOptions = talents;
      } catch (_error) {
        if (request === this.definitionRequest) this.definitionsError = true;
      } finally {
        if (request === this.definitionRequest) this.definitionsLoading = false;
      }
    },
    async saveProfession() {
      if (!this.canManageProfession || !this.professionChanged) return true;
      if (this.professionSaving) return false;
      this.professionSaving = true;
      this.professionMessage = null;
      try {
        await this.$store.dispatch("professions/changeCharacterProfession", {
          campaignId: this.campaignId,
          characterId: this.characterId,
          professionId: this.selectedProfessionId,
        });
        this.professionMessage = {
          type: "success",
          key: "characters.development.professionChanged",
        };
        this.$emit("profession-changed", this.selectedProfessionId);
        return true;
      } catch (_error) {
        this.professionMessage = {
          type: "error",
          key: "characters.development.professionError",
        };
        return false;
      } finally {
        this.professionSaving = false;
      }
    },
  },
};
</script>
