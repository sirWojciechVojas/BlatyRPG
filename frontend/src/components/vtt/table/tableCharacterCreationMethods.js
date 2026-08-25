import { characterApiClient } from "@/lib/character/characterApiClient";
import { characterCatalogApiClient } from "@/lib/character/characterCatalogApiClient";
import { characterErrorKey } from "@/lib/character/characterErrorKey";

const campaignIdOf = (vm) => Number(vm.campaignId) || null;

export const tableCharacterCreationMethods = {
  configureCharacterCreation(result, sequence) {
    this.canCreate = result.capabilities.canCreate === true;
    if (!this.canCreate) {
      this.showingCreate = false;
      this.games = [];
      return;
    }
    if (!this.games.length) this.loadCreationCatalog(sequence);
  },
  async loadCreationCatalog(sequence = this.listRequestSequence) {
    if (!this.canCreate || this.catalogLoading) return;
    const campaignId = campaignIdOf(this);
    this.catalogLoading = true;
    this.createError = "";
    try {
      const games = await characterCatalogApiClient.listGames();
      if (this.isCurrentCatalog(sequence, campaignId)) this.games = games;
    } catch (error) {
      if (this.isCurrentCatalog(sequence, campaignId)) {
        this.createError = this.$t(characterErrorKey(error, "catalog"));
      }
    } finally {
      if (this.isCurrentCatalog(sequence, campaignId)) {
        this.catalogLoading = false;
      }
    }
  },
  async openCreate() {
    if (!this.canCreate || this.creating) return;
    this.showingCreate = true;
    if (!this.games.length) await this.loadCreationCatalog();
  },
  async createCharacter(draft) {
    if (!this.canCreate || this.creating) return;
    const campaignId = campaignIdOf(this);
    if (!campaignId) return;
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
      this.$emit("changed", character);
    } catch (error) {
      if (this.isCurrentCreate(sequence, campaignId)) {
        this.createError = this.$t(characterErrorKey(error, "create"));
      }
    } finally {
      if (sequence === this.createRequestSequence) this.creating = false;
    }
  },
  isCurrentCatalog(sequence, campaignId) {
    return (
      sequence === this.listRequestSequence && campaignId === campaignIdOf(this)
    );
  },
  isCurrentCreate(sequence, campaignId) {
    return (
      sequence === this.createRequestSequence &&
      campaignId === campaignIdOf(this)
    );
  },
  resetCharacterCreation() {
    this.createRequestSequence += 1;
    this.canCreate = false;
    this.showingCreate = false;
    this.creating = false;
    this.catalogLoading = false;
    this.createError = "";
    this.games = [];
  },
};
