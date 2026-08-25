import { characterApiClient } from "@/lib/character/characterApiClient";
import { characterErrorKey } from "@/lib/character/characterErrorKey";

const campaignIdOf = (vm) => Number(vm.campaignId) || null;

export const tableCharacterCreationMethods = {
  configureCharacterCreation(result) {
    this.canCreate = result.capabilities.canCreate === true;
    if (!this.canCreate) this.showingCreate = false;
  },
  openCreate() {
    if (!this.canCreate || this.creating) return;
    this.createError = "";
    this.showingCreate = true;
  },
  async createCharacter(draft) {
    if (!this.canCreate || this.creating) return;
    const campaignId = campaignIdOf(this);
    const systemId = Number(this.campaign?.systemId) || null;
    const universeId = Number(this.campaign?.universeId) || null;
    if (!campaignId || !systemId || !universeId) {
      this.createError = this.$t("characters.errors.campaign_game");
      return;
    }
    const sequence = ++this.createRequestSequence;
    this.creating = true;
    this.createError = "";
    try {
      const character = await characterApiClient.create(campaignId, {
        ...draft,
        systemId,
        universeId,
      });
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
    this.createError = "";
  },
};
