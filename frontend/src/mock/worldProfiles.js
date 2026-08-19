export const worldProfiles = [
  {
    id: "standard",
    labelPl: "Standard",
    labelEn: "Standard",
    description:
      "Domyslny profil swiata. Uzywa pelnej puli legalnych i powszechnych typow sklepow.",
    impactSummaryPl:
      "Balans legalnych i codziennych towarow, bez skrajnych odchylen.",
    modifiers: {
      classBoosts: {},
      genreBoosts: {},
      tagBoosts: [],
      legalityBias: {
        legal: 6,
        licensed: 4,
        grey: -2,
        illegal: -12,
        mixed: 0,
      },
      priceTierBoosts: {
        cheap: 4,
        mid: 4,
        high: 0,
        luxury: -2,
      },
      seasonalityBoosts: {},
    },
  },
  {
    id: "kampania_tysiac_tronow_v3",
    labelPl: "Kampania Tysiac Tronow v3",
    labelEn: "Thousand Thrones Campaign v3",
    description:
      "Profil o podwyzszonym udziale czarnego rynku, uslug granicznych i ryzykownych transakcji.",
    impactSummaryPl:
      "Wiecej towarow granicznych, szarej strefy, trucizn i narzedzi niejawnych.",
    modifiers: {
      classBoosts: {
        ALCHEMY: 10,
        WEAPON: 6,
        TOOL: 4,
      },
      genreBoosts: {
        POTION: 8,
      },
      tagBoosts: [
        { tag: "zakaz", score: 14 },
        { tag: "truciz", score: 18 },
        { tag: "przemyt", score: 20 },
        { tag: "nielegal", score: 14 },
      ],
      legalityBias: {
        legal: 0,
        licensed: 2,
        grey: 12,
        illegal: 20,
        mixed: 10,
      },
      priceTierBoosts: {
        cheap: 2,
        mid: 6,
        high: 4,
        luxury: -4,
      },
      seasonalityBoosts: {
        sezonowy: 4,
      },
    },
  },
  {
    id: "roznice_swiatow_v1",
    labelPl: "Roznice Swiatow v1",
    labelEn: "World Differences v1",
    description:
      "Profil mieszany: handel ogolny i sezonowy ma wyzszy priorytet niz luksus i rzadkie uslugi.",
    impactSummaryPl:
      "Promuje handel ogolny, sezonowy i zywnosciowy; ogranicza luksus i rzadkie uslugi.",
    modifiers: {
      classBoosts: {
        FOOD: 10,
        TOOL: 6,
        STATIONERY: 3,
      },
      genreBoosts: {
        DRINKS: 6,
        MEALS: 8,
        BAKERY: 8,
        PRESERVES: 7,
      },
      tagBoosts: [
        { tag: "jarmark", score: 12 },
        { tag: "sezon", score: 10 },
        { tag: "zniw", score: 10 },
        { tag: "swiate", score: 8 },
      ],
      legalityBias: {
        legal: 8,
        licensed: 6,
        grey: -2,
        illegal: -10,
        mixed: 2,
      },
      priceTierBoosts: {
        cheap: 10,
        mid: 8,
        high: -3,
        luxury: -12,
      },
      seasonalityBoosts: {
        sezonowy: 10,
        jarmark: 8,
        zniwa: 8,
        swieta: 6,
      },
    },
  },
];

export default worldProfiles;
