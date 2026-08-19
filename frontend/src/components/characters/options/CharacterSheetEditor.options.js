import {
  createCharacterDraft,
  humanizeCharacterKey,
  listToText,
  parseCharacterJson,
  serializeCharacterData,
  textToList,
} from "@/lib/character/characterSheet";
import { resolveCharacterPortrait } from "@/lib/trade/characterAvatar";

export default {
  name: "CharacterSheetEditor",
  emits: ["save", "delete"],
  props: {
    character: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    error: { type: String, default: "" },
  },
  data: () => ({
    draft: null,
    skillsText: "",
    talentsText: "",
    jsonText: "{}",
    jsonError: "",
  }),
  computed: {
    canEdit() {
      return this.character?.capabilities?.canEdit === true;
    },
    canDelete() {
      return this.character?.capabilities?.canDelete === true;
    },
    portrait() {
      return resolveCharacterPortrait(
        this.character,
        this.character,
        this.character?.name,
      );
    },
    detailKeys() {
      return Object.keys(this.draft?.data?.details || {}).filter((key) => {
        const value = this.draft.data.details[key];
        return (
          ["string", "number", "boolean"].includes(typeof value) ||
          value === null
        );
      });
    },
    attributeKeys() {
      return Object.keys(this.draft?.data?.attributes?.actual || {});
    },
  },
  watch: {
    character: {
      immediate: true,
      handler() {
        this.reset();
      },
    },
  },
  methods: {
    reset() {
      if (!this.character) {
        this.draft = null;
        return;
      }
      this.draft = createCharacterDraft(this.character);
      this.skillsText = listToText(this.draft.data.attributes.skills);
      this.talentsText = listToText(this.draft.data.attributes.talents);
      this.jsonText = serializeCharacterData(this.draft.data);
      this.jsonError = "";
    },
    labelFor(key) {
      const translation = `characters.detailFields.${key}`;
      return this.$te(translation)
        ? this.$t(translation)
        : humanizeCharacterKey(key);
    },
    inputType(value) {
      return typeof value === "number" ? "number" : "text";
    },
    isLongField(key, value) {
      return key === "history" || String(value || "").length > 100;
    },
    syncTextLists() {
      this.draft.data.attributes.skills = textToList(this.skillsText);
      this.draft.data.attributes.talents = textToList(this.talentsText);
    },
    refreshJson() {
      this.syncTextLists();
      this.jsonText = serializeCharacterData(this.draft.data);
      this.jsonError = "";
    },
    applyJson() {
      const parsed = parseCharacterJson(this.jsonText);
      if (!parsed.ok) {
        this.jsonError = this.$t(`characters.errors.${parsed.error}`);
        return;
      }
      this.draft = createCharacterDraft({ ...this.draft, data: parsed.data });
      this.skillsText = listToText(this.draft.data.attributes.skills);
      this.talentsText = listToText(this.draft.data.attributes.talents);
      this.refreshJson();
    },
    save() {
      if (!this.draft || !this.canEdit) return;
      if (this.draft.name.trim().length < 2) {
        this.jsonError = this.$t("characters.errors.name");
        return;
      }
      this.syncTextLists();
      this.$emit("save", this.draft);
    },
  },
};
