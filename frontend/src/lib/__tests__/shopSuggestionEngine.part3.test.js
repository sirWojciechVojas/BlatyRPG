/* eslint-disable no-unused-vars -- split test keeps the shared canonical fixture */
import { describe, expect, it } from "vitest";
import {
  generateShopSuggestionBundle,
  generateShopSuggestions,
} from "@/lib/shopSuggestionEngine";
import { shopCatalogNetwork } from "@/mock/shopCatalogNetwork";
import { mockTemplates } from "@/mock/shopData";
const baseProfile = {
  shopId: 1,
  typeId: "apothecary",
  worldProfileId: "standard",
  locationType: "miasto",
  legalStatus: "legal",
  wealthTier: "standard",
  reputation: "neutralna",
  seasonality: "caloroczny",
  signboardName: "Alechemik Bazyl",
  signboardAltNames: ["alchemik", "bazyl"],
  categoryTags: ["typ:apothecary", "profil:aptekarz", "lok:miasto"],
};
const alchemistProfile = {
  ...baseProfile,
  typeId: "alchemist",
  signboardName: "Pod Szklaną Gwiazdą",
  signboardAltNames: ["alchemik", "retorta"],
  categoryTags: ["typ:alchemist", "profil:alchemia", "lok:miasto"],
};
const carpenterProfile = {
  ...baseProfile,
  typeId: "carpenter",
  signboardName: "Stolarnia pod Toporem",
  signboardAltNames: ["ciesla", "stolarnia"],
  categoryTags: ["typ:carpenter", "profil:ciesla", "lok:miasto"],
};
const blacksmithProfile = {
  ...baseProfile,
  typeId: "blacksmith",
  signboardName: "Kuznia pod Iskra",
  signboardAltNames: ["kowal", "kuznia"],
  categoryTags: ["typ:blacksmith", "profil:kowal", "lok:miasto"],
};
const fortuneTellerProfile = {
  ...baseProfile,
  typeId: "fortune_teller",
  signboardName: "Pod Srebrnym Okiem",
  signboardAltNames: ["wrozbita", "karty"],
  categoryTags: ["typ:fortune_teller", "profil:wrozbita", "lok:jarmark"],
};
const blockedSuggestionNamePattern =
  /\b(cechowy|cechowa|cechowe|seria|partia|pakiet|dostawa|edycja|wariant|zapas|zestaw)\b/i;
const hasBlockedSuggestionNameToken = (value) =>
  blockedSuggestionNamePattern.test(String(value || ""));
const generatedMaterialPrefixPattern =
  /^(stalowy|miedziany|skorzany|lniany|drewniany|mosiezny|srebrny|zelazny|szklany|granitowy|woskowany|debowy)\s/i;
const obviousMaterialGrammarErrorsPattern =
  /\b(miedziany|stalowy|drewniany|lniany|skorzany|mosiezny)\s+(szkatulka|sakiewka|pudelko)\b/i;
const hasMaterialMarker = (value) =>
  /\b(miedzian|stalow|skorzan|lnian|drewnian|mosiezn|srebrn|zelazn|szklan|granitow|woskowan|debow|z miedzi|ze stali|ze skory|z drewna|z mosiadzu)\b/i.test(
    String(value || ""),
  );
const bannedSuffixPattern =
  /\b(z uchwytem|do pasa|z zamkiem|do podrozy|do warsztatu|z pokrowcem|na pasek|z zaczepem)\b/i;

describe("shopSuggestionEngine", () => {
  it("changes starter assortment when any single shop parameter changes", () => {
    const parameterTemplates = [
      {
        ID: 30001,
        NAME: "Pergamin portowej wyroczni",
        DESCRIPTION: "Pergamin dla portu i kupców morskich.",
        ITEM_CLASS: "STATIONERY",
        ITEM_GENRE: "DOCUMENTS",
        IMG_CLASS: "v0724",
        PRIZE: 240,
        CHARGE: 50,
      },
      {
        ID: 30002,
        NAME: "Jarmarczna świeca obrzędowa",
        DESCRIPTION: "Towar jarmarczny na święta i sezonowe obrzędy.",
        ITEM_CLASS: "MAGIC",
        ITEM_GENRE: "UTILITY",
        IMG_CLASS: "v1089",
        PRIZE: 90,
        CHARGE: 20,
      },
      {
        ID: 30003,
        NAME: "Elitarny atrament runiczny",
        DESCRIPTION: "Luksusowy atrament dla mistrzów i salonów.",
        ITEM_CLASS: "MAGIC",
        ITEM_GENRE: "BUFFS",
        IMG_CLASS: "v1089",
        PRIZE: 2200,
        CHARGE: 140,
      },
      {
        ID: 30004,
        NAME: "Szary pergamin spod lady",
        DESCRIPTION: "Podejrzany towar dyskretny, sprzedawany nielegalnie.",
        ITEM_CLASS: "STATIONERY",
        ITEM_GENRE: "DOCUMENTS",
        IMG_CLASS: "v0724",
        PRIZE: 60,
        CHARGE: 10,
      },
      {
        ID: 30005,
        NAME: "Katalog cechowych komponentów",
        DESCRIPTION: "Licencjonowany spis cechowych składników i receptur.",
        ITEM_CLASS: "STATIONERY",
        ITEM_GENRE: "BOOKS",
        IMG_CLASS: "v0724",
        PRIZE: 320,
        CHARGE: 70,
      },
      {
        ID: 30006,
        NAME: "Wiosenny susz rytualny",
        DESCRIPTION: "Nowalijny susz kwiatowy do rytuałów wiosny.",
        ITEM_CLASS: "ALCHEMY",
        ITEM_GENRE: "HEALING",
        IMG_CLASS: "v1089",
        PRIZE: 48,
        CHARGE: 18,
      },
      {
        ID: 30007,
        NAME: "Zimowa sól ochronna",
        DESCRIPTION: "Korzenna sól do zimowych i świątecznych oczyszczeń.",
        ITEM_CLASS: "MAGIC",
        ITEM_GENRE: "BUFFS",
        IMG_CLASS: "v1089",
        PRIZE: 180,
        CHARGE: 35,
      },
      {
        ID: 30008,
        NAME: "Mistrzowski tygielek srebrny",
        DESCRIPTION: "Precyzyjne naczynie dla licencjonowanych mistrzów.",
        ITEM_CLASS: "ALCHEMY",
        ITEM_GENRE: "UTILITY",
        IMG_CLASS: "v1089",
        PRIZE: 960,
        CHARGE: 160,
      },
      {
        ID: 30009,
        NAME: "Zużyty filtr płócienny",
        DESCRIPTION: "Tani i prosty filtr do codziennej pracy.",
        ITEM_CLASS: "ALCHEMY",
        ITEM_GENRE: "UTILITY",
        IMG_CLASS: "v1089",
        PRIZE: 24,
        CHARGE: 8,
      },
      {
        ID: 30010,
        NAME: "Nielegalny pył omenów",
        DESCRIPTION: "Zakazany pył do niejawnych praktyk i nocnych odczytów.",
        ITEM_CLASS: "ALCHEMY",
        ITEM_GENRE: "TOXINS",
        IMG_CLASS: "v1089",
        PRIZE: 420,
        CHARGE: 42,
      },
    ];
    const baselineProfile = {
      ...baseProfile,
      typeId: "magic_components",
      signboardName: "Skład pod Gwiazdą",
      categoryTags: ["typ:magic_components", "profil:magia", "lok:miasto"],
    };
    const topLabelsFor = (profile) =>
      (
        generateShopSuggestionBundle({
          templates: parameterTemplates,
          catalogNodes: shopCatalogNetwork,
          profile,
          nextTemplateId: 31000,
        })?.suggestions || []
      )
        .slice(0, 8)
        .map((entry) => String(entry?.displayName || ""));
    const baseline = topLabelsFor(baselineProfile);
    const variants = [
      {
        ...baselineProfile,
        locationType: "port",
      },
      {
        ...baselineProfile,
        worldProfileId: "kampania_tysiac_tronow_v3",
      },
      {
        ...baselineProfile,
        legalStatus: "grey",
      },
      {
        ...baselineProfile,
        wealthTier: "elitarny",
      },
      {
        ...baselineProfile,
        reputation: "fatalna",
      },
      {
        ...baselineProfile,
        seasonality: "wiosna",
      },
    ];
    variants.forEach((profile) => {
      expect(topLabelsFor(profile)).not.toEqual(baseline);
    });
  });
});
