export default {
  name: "AdminCharactersTab",
  props: {
    characters: { type: Array, required: true },
    campaigns: { type: Array, required: true },
    characterCampaigns: { type: Array, required: true },
    characterOwners: { type: Array, required: true },
    gameMasters: { type: Array, required: true },
    busyKey: { type: String, default: "" },
    error: { type: String, default: "" },
  },
  emits: ["campaign-change", "owner-change"],
  data: () => ({ query: "", draggedCharacterId: 0 }),
  computed: {
    filteredCharacters() {
      const query = this.query.toLocaleLowerCase();
      return query
        ? this.characters.filter((item) =>
            item.name.toLocaleLowerCase().includes(query),
          )
        : this.characters;
    },
  },
  methods: {
    character(id) {
      return this.characters.find((item) => item.id === Number(id));
    },
    campaignsFor(characterId) {
      return this.characterCampaigns.filter(
        (item) => item.characterId === characterId,
      );
    },
    ownersFor(characterId) {
      return this.characterOwners.filter(
        (item) => item.characterId === characterId,
      );
    },
    charactersForCampaign(campaignId) {
      const ids = new Set(
        this.characterCampaigns
          .filter((item) => item.campaignId === campaignId)
          .map((item) => item.characterId),
      );
      return this.characters.filter((item) => ids.has(item.id));
    },
    charactersForOwner(gameMaster) {
      const ids = new Set(
        this.characterOwners
          .filter(
            (item) =>
              item.campaignId === gameMaster.campaignId &&
              item.userId === gameMaster.userId,
          )
          .map((item) => item.characterId),
      );
      return this.characters.filter((item) => ids.has(item.id));
    },
    campaignName(campaignId) {
      return this.campaigns.find((item) => item.id === campaignId)?.name || "";
    },
    initials(name) {
      return String(name || "?")
        .trim()
        .split(/\s+/u)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toLocaleUpperCase();
    },
    startDrag(character, event) {
      this.draggedCharacterId = character.id;
      event.dataTransfer?.setData("text/plain", String(character.id));
      if (event.dataTransfer) event.dataTransfer.effectAllowed = "copy";
    },
    draggedCharacter() {
      return this.character(this.draggedCharacterId);
    },
    dropOnCampaign(campaign) {
      const character = this.draggedCharacter();
      if (character) this.changeCampaign(character, campaign.id, true);
    },
    dropOnOwner(gameMaster) {
      const character = this.draggedCharacter();
      if (character) this.changeOwner(character, gameMaster, true);
    },
    changeCampaign(character, campaignId, assigned) {
      const exists = this.characterCampaigns.some(
        (item) =>
          item.characterId === character.id && item.campaignId === campaignId,
      );
      if (exists !== assigned) {
        this.$emit("campaign-change", { character, campaignId, assigned });
      }
    },
    changeOwner(character, gameMaster, assigned) {
      const exists = this.characterOwners.some(
        (item) =>
          item.characterId === character.id &&
          item.campaignId === gameMaster.campaignId &&
          item.userId === gameMaster.userId,
      );
      if (exists !== assigned) {
        this.$emit("owner-change", { character, gameMaster, assigned });
      }
    },
    isBusy(type, campaignId, userId = "") {
      const suffix = `:${campaignId}${userId ? `:${userId}` : ""}`;
      return (
        this.busyKey.startsWith(`${type}:`) && this.busyKey.endsWith(suffix)
      );
    },
  },
};
