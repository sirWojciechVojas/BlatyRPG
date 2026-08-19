export const createRuntimePart1 = (runtime) => {
  const toLower = (value) => String(value || "").toLowerCase();
  Object.assign(runtime, {
    toLower,
  });
  const toUpper = (value) => String(value || "").toUpperCase();
  Object.assign(runtime, {
    toUpper,
  });
  const priceTierToBrass = {
    cheap: 24,
    mid: 120,
    high: 720,
    luxury: 2400,
  };
  Object.assign(runtime, {
    priceTierToBrass,
  });
  const SUGGESTIONS_TARGET_DEFAULT = 24;
  Object.assign(runtime, {
    SUGGESTIONS_TARGET_DEFAULT,
  });
  const SUGGESTIONS_TARGET_MIN = 20;
  Object.assign(runtime, {
    SUGGESTIONS_TARGET_MIN,
  });
  const SUGGESTIONS_TARGET_MAX = 30;
  Object.assign(runtime, {
    SUGGESTIONS_TARGET_MAX,
  });
  const RECOMMENDATION_MULTIPLIER = 5;
  Object.assign(runtime, {
    RECOMMENDATION_MULTIPLIER,
  });
  const RECOMMENDATIONS_MAX = 180;
  Object.assign(runtime, {
    RECOMMENDATIONS_MAX,
  });
  const worldProfileMap = new Map(
    (runtime.worldProfiles || []).map((entry) => [String(entry.id), entry]),
  );
  Object.assign(runtime, {
    worldProfileMap,
  });
  const locationFallbackMap = {
    metropolia: ["metropolia", "miasto"],
    miasto: ["miasto"],
    miasteczko: ["miasteczko", "miasto"],
    wies: ["wies", "miasteczko"],
    jarmark: ["jarmark", "miasteczko", "miasto"],
    port: ["port", "miasto"],
    port_morski: ["port", "metropolia", "miasto"],
    port_rzeczny: ["port", "miasteczko", "miasto"],
    forteca: ["metropolia", "miasto"],
    przy_trakcie: ["wies", "miasteczko", "miasto"],
    obrzeza: ["wies", "miasteczko", "miasto"],
    dzielnica_bogata: ["metropolia", "miasto"],
    dzielnica_biedna: ["metropolia", "miasto"],
    strefa_swiatynna: ["metropolia", "miasto", "miasteczko"],
    strefa_cechowa: ["metropolia", "miasto"],
  };
  Object.assign(runtime, {
    locationFallbackMap,
  });
  const seasonKeywordMap = {
    caloroczny: [],
    sezonowy: [
      "sezon",
      "swiez",
      "susz",
      "kiszon",
      "owoc",
      "grzyb",
      "jarmark",
      "miod",
    ],
    wiosna: ["ziol", "nowal", "mlod", "kwiat"],
    lato: ["owoc", "piwo", "chlod", "ryb", "jagod"],
    jesien: ["grzyb", "kiszon", "dziczyz", "korzen", "miod"],
    zima: ["wedzon", "suszon", "grzan", "korzen", "swiat"],
    zniwa: ["zboz", "maka", "kasz", "chleb", "sierp", "snop"],
    jarmark: ["jarmark", "kram", "ozdob", "piernik", "wstaz"],
    swieta: ["piernik", "miod", "wosk", "swiec", "kadzidl", "ozdob"],
  };
  Object.assign(runtime, {
    seasonKeywordMap,
  });
  const legalSignals = {
    illegal: [
      "truciz",
      "przemyt",
      "wytrych",
      "krad",
      "zakaz",
      "falsz",
      "nocny",
      "cichy",
    ],
    grey: ["lombard", "zastaw", "podejrz", "pod lada", "dyskret"],
    legal: ["cech", "licenc", "urzad", "notariusz", "zakon"],
  };
  Object.assign(runtime, {
    legalSignals,
  });
  const wealthPriceWeights = {
    nedzny: {
      cheap: 28,
      mid: 2,
      high: -20,
      luxury: -36,
    },
    biedny: {
      cheap: 22,
      mid: 8,
      high: -18,
      luxury: -34,
    },
    standard: {
      cheap: 8,
      mid: 12,
      high: 2,
      luxury: -8,
    },
    bogaty: {
      cheap: -4,
      mid: 8,
      high: 18,
      luxury: 20,
    },
    elitarny: {
      cheap: -16,
      mid: 2,
      high: 18,
      luxury: 30,
    },
    luksusowy: {
      cheap: -22,
      mid: -4,
      high: 16,
      luxury: 36,
    },
  };
  Object.assign(runtime, {
    wealthPriceWeights,
  });
  const reputationBehavior = {
    fatalna: {
      legalBonus: -14,
      illegalBonus: 18,
      qualityWeight: -10,
    },
    zla: {
      legalBonus: -8,
      illegalBonus: 12,
      qualityWeight: -5,
    },
    podejrzana: {
      legalBonus: -4,
      illegalBonus: 8,
      qualityWeight: -2,
    },
    neutralna: {
      legalBonus: 2,
      illegalBonus: 0,
      qualityWeight: 0,
    },
    dobra: {
      legalBonus: 8,
      illegalBonus: -8,
      qualityWeight: 6,
    },
    znakomita: {
      legalBonus: 14,
      illegalBonus: -18,
      qualityWeight: 10,
    },
  };
  Object.assign(runtime, {
    reputationBehavior,
  });
  const legalCompatibilityScore = {
    legal: {
      legal: 18,
      licensed: 12,
      mixed: 4,
      grey: -18,
      illegal: -45,
    },
    licensed: {
      legal: 12,
      licensed: 18,
      mixed: 8,
      grey: -12,
      illegal: -35,
    },
    grey: {
      legal: 8,
      licensed: 10,
      mixed: 14,
      grey: 16,
      illegal: -10,
    },
    illegal: {
      legal: -8,
      licensed: -6,
      mixed: 10,
      grey: 16,
      illegal: 26,
    },
    mixed: {
      legal: 6,
      licensed: 8,
      mixed: 12,
      grey: 6,
      illegal: 4,
    },
  };
  Object.assign(runtime, {
    legalCompatibilityScore,
  });
  const draftImgClass = {
    FOOD: "v0093",
    TOOL: "v1058",
    WEAPON: "v1289",
    ARMOR: "v0328",
    ALCHEMY: "v1074",
    POTION: "v1074",
    GADGET: "v1042",
    STATIONERY: "v0244",
  };
  Object.assign(runtime, {
    draftImgClass,
  });
  const classGenreExamples = {
    "FOOD:DRINKS": ["Piwo jasne", "Piwo ciemne", "Miód pitny", "Cydr"],
    "FOOD:MEALS": ["Gulasz", "Potrawka", "Polewka cebulowa", "Kasza z mięsem"],
    "FOOD:BAKERY": ["Chleb razowy", "Podpłomyk", "Bajgiel", "Placek miodowy"],
    "FOOD:PRESERVES": [
      "Kiszone ogórki",
      "Kapusta kiszona",
      "Śledź solony",
      "Suszone mięso",
    ],
    "ALCHEMY:POTION": [
      "Mikstura leczenia",
      "Nalewka wzmacniająca",
      "Eliksir odporności",
      "Tonik gorączkowy",
    ],
    "TOOL:UTILITY": [
      "Latarnia olejna",
      "Lina konopna",
      "Krzesiwo",
      "Łopata żelazna",
    ],
  };
  Object.assign(runtime, {
    classGenreExamples,
  });
  const locationKeywordMap = {
    metropolia: ["import", "salon", "mistrz", "cech", "luks", "egzot"],
    miasto: ["cech", "warsztat", "miejski", "targ", "straż"],
    miasteczko: ["trakt", "jarmark", "wóz", "staj", "napraw"],
    wies: ["wiej", "rol", "siano", "owies", "podk", "beczk", "narzęd"],
    jarmark: ["jarmark", "kram", "ozdob", "wstęg", "wróż", "amulet"],
    port: ["port", "morsk", "rzeczn", "sieć", "lina", "beczk", "kotwic"],
    forteca: ["fort", "warown", "straż", "kusz", "zbroj", "oblęż"],
    przy_trakcie: ["trakt", "podróż", "woz", "koń", "staj", "lina"],
    obrzeza: ["napraw", "używ", "szary", "prosty", "tanio"],
    dzielnica_bogata: ["złoc", "srebr", "jedwab", "salon", "import", "szlache"],
    dzielnica_biedna: ["tani", "prosty", "napraw", "używ", "szary"],
    strefa_swiatynna: ["świę", "rytua", "kadzid", "relik", "ochron", "obrz"],
    strefa_cechowa: ["cech", "mistrz", "warsztat", "licenc", "rzemieś"],
  };
  Object.assign(runtime, {
    locationKeywordMap,
  });
  return {
    toLower,
    toUpper,
    priceTierToBrass,
    SUGGESTIONS_TARGET_DEFAULT,
    SUGGESTIONS_TARGET_MIN,
    SUGGESTIONS_TARGET_MAX,
    RECOMMENDATION_MULTIPLIER,
    RECOMMENDATIONS_MAX,
    worldProfileMap,
    locationFallbackMap,
    seasonKeywordMap,
    legalSignals,
    wealthPriceWeights,
    reputationBehavior,
    legalCompatibilityScore,
    draftImgClass,
    classGenreExamples,
    locationKeywordMap,
  };
};
