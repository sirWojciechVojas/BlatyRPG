import CharacterCreateDialog from "@/components/characters/CharacterCreateDialog.vue";
import CharacterList from "@/components/characters/CharacterList.vue";
import CharacterSheetEditor from "@/components/characters/CharacterSheetEditor.vue";
import { characterApiClient } from "@/lib/character/characterApiClient";
import { characterCatalogApiClient } from "@/lib/character/characterCatalogApiClient";

const errorKey = (error, scope) => {
  if (error?.status === 401) return "characters.errors.session";
  if (error?.status === 403) return "characters.errors.forbidden";
  if (error?.status === 409 || error?.code === "character_conflict") {
    return "characters.errors.conflict";
  }
  if (error?.network) return "characters.errors.network";
  return `characters.errors.${scope}`;
};

export default {
  name: "CharacterWorkspaceView",
  components: { CharacterCreateDialog, CharacterList, CharacterSheetEditor },
  data: () => ({
    characters: [],
    games: [],
    selectedId: null,
    selectedCharacter: null,
    canCreate: false,
    showingCreate: false,
    loading: false,
    loadingSheet: false,
    saving: false,
    creating: false,
    deleting: false,
    loadError: "",
    saveError: "",
    createError: "",
    notice: "",
    listRequestSequence: 0,
    sheetRequestSequence: 0,
    saveRequestSequence: 0,
    createRequestSequence: 0,
    deleteRequestSequence: 0,
  }),
  computed: {
    campaignId() {
      return Number(this.$route.params.campaignId) || null;
    },
  },
  watch: {
    campaignId: {
      immediate: true,
      handler(next, previous) {
        if (!next || next === previous) return;
        this.resetForCampaign();
        this.loadCharacters();
      },
    },
  },
  methods: {
    resetForCampaign() {
      this.listRequestSequence += 1;
      this.sheetRequestSequence += 1;
      this.saveRequestSequence += 1;
      this.createRequestSequence += 1;
      this.deleteRequestSequence += 1;
      this.characters = [];
      this.games = [];
      this.selectedId = null;
      this.selectedCharacter = null;
      this.canCreate = false;
      this.showingCreate = false;
      this.saving = false;
      this.creating = false;
      this.deleting = false;
      this.loadError = "";
      this.saveError = "";
      this.notice = "";
    },
    async loadCharacters() {
      const campaignId = this.campaignId;
      if (!campaignId) return;
      const sequence = ++this.listRequestSequence;
      this.loading = true;
      this.loadError = "";
      this.notice = "";
      try {
        const result = await characterApiClient.list(campaignId);
        if (!this.isCurrentList(sequence, campaignId)) return;
        this.characters = result.characters;
        this.canCreate = result.capabilities.canCreate;
        if (this.canCreate) await this.loadGames(sequence, campaignId);
        const nextId = this.characters[0]?.id || null;
        if (nextId) await this.selectCharacter(nextId);
      } catch (error) {
        if (this.isCurrentList(sequence, campaignId)) {
          this.loadError = this.$t(errorKey(error, "load"));
        }
      } finally {
        if (this.isCurrentList(sequence, campaignId)) this.loading = false;
      }
    },
    async loadGames(sequence, campaignId) {
      try {
        const games = await characterCatalogApiClient.listGames();
        if (this.isCurrentList(sequence, campaignId)) this.games = games;
      } catch (error) {
        if (this.isCurrentList(sequence, campaignId)) {
          this.createError = this.$t(errorKey(error, "catalog"));
        }
      }
    },
    async selectCharacter(id) {
      const campaignId = this.campaignId;
      const characterId = Number(id);
      if (!characterId || !campaignId) return;
      const sequence = ++this.sheetRequestSequence;
      this.selectedId = characterId;
      this.loadingSheet = true;
      this.saveError = "";
      this.notice = "";
      try {
        const character = await characterApiClient.get(campaignId, characterId);
        if (!this.isCurrentSheet(sequence, campaignId, characterId)) return;
        this.selectedCharacter = character;
        this.replaceCharacter(character);
      } catch (error) {
        if (this.isCurrentSheet(sequence, campaignId, characterId)) {
          this.saveError = this.$t(errorKey(error, "load"));
        }
      } finally {
        if (this.isCurrentSheet(sequence, campaignId, characterId)) {
          this.loadingSheet = false;
        }
      }
    },
    async saveCharacter(draft) {
      const campaignId = this.campaignId;
      const characterId = this.selectedId;
      if (!characterId || this.isMutating()) return;
      const sequence = ++this.saveRequestSequence;
      this.saving = true;
      this.saveError = "";
      this.notice = "";
      try {
        const character = await characterApiClient.update(
          campaignId,
          characterId,
          draft,
        );
        if (!this.isCurrentSave(sequence, campaignId, characterId)) return;
        this.selectedCharacter = character;
        this.replaceCharacter(character);
        this.notice = this.$t("characters.notices.saved");
      } catch (error) {
        if (this.isCurrentSave(sequence, campaignId, characterId)) {
          this.saveError = this.$t(errorKey(error, "save"));
        }
      } finally {
        if (sequence === this.saveRequestSequence) this.saving = false;
      }
    },
    openCreate() {
      if (this.isMutating()) return;
      this.createError = this.games.length
        ? ""
        : this.$t("characters.errors.catalog");
      this.showingCreate = true;
    },
    async createCharacter(draft) {
      const campaignId = this.campaignId;
      if (!campaignId || this.isMutating()) return;
      const sequence = ++this.createRequestSequence;
      this.creating = true;
      this.createError = "";
      try {
        const character = await characterApiClient.create(campaignId, draft);
        if (!this.isCurrentCreate(sequence, campaignId)) return;
        this.replaceCharacter(character);
        this.selectedId = character.id;
        this.selectedCharacter = character;
        this.showingCreate = false;
        this.notice = this.$t("characters.notices.created");
      } catch (error) {
        if (this.isCurrentCreate(sequence, campaignId)) {
          this.createError = this.$t(errorKey(error, "create"));
        }
      } finally {
        if (sequence === this.createRequestSequence) this.creating = false;
      }
    },
    async deleteCharacter() {
      const character = this.selectedCharacter;
      const campaignId = this.campaignId;
      const characterId = character?.id;
      if (!characterId || this.isMutating()) return;
      const message = this.$t("characters.delete.confirm", {
        name: character.name,
      });
      if (!window.confirm(message)) return;
      const sequence = ++this.deleteRequestSequence;
      this.deleting = true;
      this.saveError = "";
      try {
        await characterApiClient.delete(campaignId, characterId);
        if (!this.isCurrentDelete(sequence, campaignId, characterId)) return;
        this.characters = this.characters.filter(
          (item) => item.id !== characterId,
        );
        this.notice = this.$t("characters.notices.deleted");
        this.selectedId = null;
        this.selectedCharacter = null;
        const nextId = this.characters[0]?.id;
        if (nextId) await this.selectCharacter(nextId);
      } catch (error) {
        if (this.isCurrentDelete(sequence, campaignId, characterId)) {
          this.saveError = this.$t(errorKey(error, "delete"));
        }
      } finally {
        if (sequence === this.deleteRequestSequence) this.deleting = false;
      }
    },
    replaceCharacter(character) {
      const index = this.characters.findIndex(
        (item) => item.id === character.id,
      );
      if (index < 0) this.characters.push(character);
      else this.characters.splice(index, 1, character);
      this.characters.sort((left, right) =>
        left.name.localeCompare(right.name),
      );
    },
    isCurrentList(sequence, campaignId) {
      return (
        sequence === this.listRequestSequence && campaignId === this.campaignId
      );
    },
    isCurrentSheet(sequence, campaignId, characterId) {
      return (
        sequence === this.sheetRequestSequence &&
        campaignId === this.campaignId &&
        characterId === this.selectedId
      );
    },
    isMutating() {
      return this.saving || this.creating || this.deleting;
    },
    isCurrentSave(sequence, campaignId, characterId) {
      return (
        sequence === this.saveRequestSequence &&
        campaignId === this.campaignId &&
        characterId === this.selectedId
      );
    },
    isCurrentCreate(sequence, campaignId) {
      return (
        sequence === this.createRequestSequence &&
        campaignId === this.campaignId
      );
    },
    isCurrentDelete(sequence, campaignId, characterId) {
      return (
        sequence === this.deleteRequestSequence &&
        campaignId === this.campaignId &&
        characterId === this.selectedId
      );
    },
  },
};
