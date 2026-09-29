import {
  createCharacterDraft,
  humanizeCharacterKey,
  parseCharacterJson,
  serializeCharacterData,
} from "@/lib/character/characterSheet";
import { resolveCharacterPortrait } from "@/lib/trade/characterAvatar";
import AuthenticatedImage from "@/components/ui/AuthenticatedImage.vue";
import CharacterDevelopmentEditor from "../CharacterDevelopmentEditor.vue";
import CharacterWalletEditor from "../CharacterWalletEditor.vue";

const WFRP_ATTRIBUTE_GROUPS = [
  {
    id: "primary",
    labelKey: "characters.sections.primaryAttributes",
    keys: ["ww", "us", "k", "odp", "zr", "int", "sw", "ogd"],
  },
  {
    id: "secondary",
    labelKey: "characters.sections.secondaryAttributes",
    keys: ["a", "zyw", "s", "wt", "sz", "mag", "po", "pp"],
  },
];

const WFRP_ATTRIBUTE_KEYS = WFRP_ATTRIBUTE_GROUPS.flatMap(
  (group) => group.keys,
);

const normalizedAttributeKey = (key) => String(key || "").toLowerCase();

const DETAIL_DICTIONARIES = {
  sex: {
    mode: "closed",
    options: ["male", "female", "nonBinary", "unspecified"],
  },
  race: {
    mode: "suggested",
    options: [
      "human",
      "dwarf",
      "elf",
      "highElf",
      "woodElf",
      "halfling",
      "gnome",
    ],
  },
  deity: {
    mode: "suggested",
    options: [
      "sigmar",
      "ulric",
      "verena",
      "shallya",
      "morr",
      "myrmidia",
      "ranald",
      "taal",
      "rhya",
      "manann",
    ],
  },
  eye_color: {
    mode: "suggested",
    options: [
      "brownEyes",
      "blueEyes",
      "greyEyes",
      "greenEyes",
      "hazelEyes",
      "blackEyes",
      "violetEyes",
      "greyBlueEyes",
    ],
  },
  hair_color: {
    mode: "suggested",
    options: [
      "blackHair",
      "darkBrownHair",
      "brownHair",
      "lightBrownHair",
      "blondeHair",
      "redHair",
      "copperHair",
      "greyHair",
      "whiteHair",
      "bald",
    ],
  },
  birthplace: {
    mode: "suggested",
    options: [
      "altdorf",
      "nuln",
      "marienburg",
      "middenheim",
      "talabheim",
      "kislev",
      "erengrad",
      "karakKadrin",
    ],
  },
  star_sign: {
    mode: "suggested",
    options: ["twoBullocks", "eveningStar", "dragonDragomas"],
  },
};

export default {
  name: "CharacterSheetEditor",
  components: {
    AuthenticatedImage,
    CharacterDevelopmentEditor,
    CharacterWalletEditor,
  },
  emits: ["save", "delete"],
  props: {
    campaignId: { type: [Number, String], default: null },
    character: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    error: { type: String, default: "" },
  },
  data: () => ({
    draft: null,
    relatedSaving: false,
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
    formSaving() {
      return this.saving || this.relatedSaving;
    },
    effectiveCampaignId() {
      return Number(this.campaignId || this.character?.campaignId) || null;
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
        if (key === "profession_id") return false;
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
    attributeKeyMap() {
      return new Map(
        this.attributeKeys.map((key) => [normalizedAttributeKey(key), key]),
      );
    },
    usesWfrpAttributeTable() {
      return (
        WFRP_ATTRIBUTE_KEYS.filter((key) => this.attributeKeyMap.has(key))
          .length >= 12
      );
    },
    attributeGroups() {
      if (!this.usesWfrpAttributeTable) return [];
      return WFRP_ATTRIBUTE_GROUPS.map((group) => ({
        ...group,
        keys: group.keys
          .map((key) => this.attributeKeyMap.get(key))
          .filter(Boolean),
      })).filter((group) => group.keys.length);
    },
    genericAttributeKeys() {
      if (!this.usesWfrpAttributeTable) return this.attributeKeys;
      const grouped = new Set(
        this.attributeGroups.flatMap((group) => group.keys),
      );
      return this.attributeKeys.filter((key) => !grouped.has(key));
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
      this.jsonText = serializeCharacterData(this.draft.data);
      this.jsonError = "";
      this.$nextTick(() => {
        this.$refs.walletEditor?.load();
        this.$refs.developmentEditor?.loadProfession();
      });
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
    attributeLabel(key) {
      const normalized = normalizedAttributeKey(key);
      const translation = `characters.attributeLabels.${normalized}`;
      return this.$te(translation) ? this.$t(translation) : key.toUpperCase();
    },
    attributeInputId(key) {
      const safeKey = String(key).replace(/[^a-z0-9_-]+/giu, "-");
      return `character-${this.character?.id || "draft"}-attribute-${safeKey}`;
    },
    detailDictionary(key) {
      return DETAIL_DICTIONARIES[String(key || "").toLowerCase()] || null;
    },
    hasClosedDictionary(key) {
      return this.detailDictionary(key)?.mode === "closed";
    },
    hasSuggestedDictionary(key) {
      return this.detailDictionary(key)?.mode === "suggested";
    },
    dictionaryOptions(key) {
      const dictionary = this.detailDictionary(key);
      if (!dictionary) return [];
      const options = dictionary.options.map((option) =>
        this.$t(`characters.dictionaryOptions.${option}`),
      );
      const current = String(this.draft?.data?.details?.[key] || "").trim();
      const containsCurrent = options.some(
        (option) =>
          option.localeCompare(current, undefined, { sensitivity: "base" }) ===
          0,
      );
      return current && !containsCurrent ? [current, ...options] : options;
    },
    detailDatalistId(key) {
      const safeKey = String(key).replace(/[^a-z0-9_-]+/giu, "-");
      return `character-${this.character?.id || "draft"}-details-${safeKey}`;
    },
    isLongField(key, value) {
      return key === "history" || String(value || "").length > 100;
    },
    setProfessionId(id) {
      if (!this.draft?.data?.details) return;
      this.draft.data.details.profession_id = Number(id) || null;
    },
    refreshJson() {
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
      this.refreshJson();
    },
    async save() {
      if (!this.draft || !this.canEdit) return;
      if (this.draft.name.trim().length < 2) {
        this.jsonError = this.$t("characters.errors.name");
        return;
      }
      this.relatedSaving = true;
      this.jsonError = "";
      try {
        const professionSaved = await this.$refs.developmentEditor?.save();
        if (professionSaved === false) return;
        const walletResult = await this.$refs.walletEditor?.save();
        if (walletResult === false) return;
        if (walletResult?.updatedAt) {
          this.draft.updatedAt = walletResult.updatedAt;
          this.draft.revision = Math.max(
            1,
            Number(walletResult.revision) || this.draft.revision,
          );
        }
        this.$emit("save", this.draft);
      } finally {
        this.relatedSaving = false;
      }
    },
  },
};
