export const createRuntimePart2 = (runtime) => {
  const TAVERN_SIGN_ROOTS = [
    "pior",
    "grot",
    "kruk",
    "psem",
    "osiol",
    "dlon",
    "awelin",
    "gospod",
    "szans",
    "sigmar",
    "puszk",
    "pandor",
    "swiec",
    "kotwic",
    "smok",
    "kuc",
    "wilk",
    "ksiezyc",
    "rog",
    "deb",
    "lis",
    "podkow",
    "dzban",
    "koron",
    "dzwon",
    "lucznik",
    "troll",
    "ksiec",
    "labedz",
    "kaptur",
    "latar",
    "gryf",
    "jednoroz",
    "fortun",
    "most",
    "ulryk",
    "morr",
    "shally",
    "dyszel",
    "beczk",
    "kufel",
    "kociol",
    "ges",
    "karp",
    "kaplon",
    "wedrow",
    "bram",
    "trakt",
    "siostr",
    "braci",
    "miecz",
    "winorosl",
    "brzask",
    "postoj",
    "brod",
    "pielgrzym",
  ];
  Object.assign(runtime, {
    TAVERN_SIGN_ROOTS,
  });
  const TYPE_ANCHORS_BY_ID = {
    bakery: ["bochen", "chleb", "maka", "piec"],
    miller: ["mlyn", "maka", "kasz"],
    grain_warehouse: ["zboz", "kasz", "spich"],
    oil_press: ["olej", "lnian", "kropla"],
    smokehouse: ["dym", "wedz", "hak"],
    butcher: ["tasak", "rzez", "mies"],
    salt_depot: ["sol", "bryla", "pekl"],
    vegetable_stall: ["warzy", "ziol", "wiazka"],
    fruit_stall: ["owoc", "jabl", "sad"],
    dairy: ["mlek", "masl", "ser"],
    fish_market: ["ryb", "lusk", "port"],
    brewery_tavern: [
      "browar",
      "karcz",
      "wyszynk",
      ...runtime.TAVERN_SIGN_ROOTS,
    ],
    wine_cellar: ["kielich", "wino", "miod"],
    distillery: ["alemb", "okowit", "nalew"],
    inn: ["karcz", "gosc", "nocleg", ...runtime.TAVERN_SIGN_ROOTS],
    spice_import: ["szafr", "korzen", "przypraw"],
    carpenter: ["dlut", "belk", "ciesl"],
    woodworker: ["deska", "stolar", "law"],
    cooper: ["obrecz", "becz", "kad"],
    potter: ["glina", "piec", "dzban"],
    tanner: ["skor", "garbar", "rzemien"],
    saddler: ["siod", "uzd", "rymar"],
    glue_maker: ["klej", "zywic", "sloj"],
    stonemason: ["kamie", "klin", "mur"],
    chimney_sweep: ["komin", "sadza", "kaptur"],
    tailor: ["igla", "kraw", "sukn"],
    cloth_merchant: ["sukn", "plotn", "zw"],
    shoemaker: ["but", "szew", "lap"],
    haberdashery: ["wstaz", "guz", "kokard"],
    blacksmith: ["kowadl", "iskr", "kowal", "gwozd"],
    locksmith: ["zamek", "klucz", "zasuw"],
    knifemaker: ["noz", "ostrz", "czub"],
    armorer: ["pancer", "helm", "piers"],
    guild_armorer: ["cech", "zbroj", "mistrz"],
    gunsmith: ["lufa", "proch", "lont"],
    bowyer: ["luk", "cieciw", "kusz", "lotek"],
    goldsmith: ["zlot", "piersc", "waga"],
    pawnbroker: ["zastaw", "pieczec", "lomb"],
    barber_surgeon: ["brzyt", "miednic", "cyrul"],
    physician: ["pigul", "doktor", "mikstur"],
    apothecary: ["fiolk", "apte", "masc"],
    herbalist: ["ziol", "napar", "pokrzyw"],
    alchemist: ["alemb", "odczyn", "eliks"],
    powder_depot: ["proch", "saletr", "siark"],
    poison_vendor: ["truci", "kropl", "odtrut"],
    scribe: ["pior", "skryb", "kontrakt"],
    bookshop: ["ksieg", "regal", "litera"],
    cartographer: ["map", "wiatr", "portow"],
    notary: ["notarius", "wsteg", "pieczec"],
    stable: ["kon", "uzd", "stajn"],
    horse_trader: ["kopyt", "kon", "mul"],
    fodder_shop: ["pasz", "otreb", "sian"],
    rope_maker: ["lin", "powroz", "wez"],
    tent_maker: ["plotn", "namiot", "dach"],
    lamp_supplier: ["lamp", "knot", "oliw"],
    devotional_shop: ["swiec", "wiar", "wot"],
    incense_depot: ["kadzid", "dym", "oltarz"],
    funeral_brotherhood: ["pogrz", "calun", "trumn"],
    fortune_teller: ["wroz", "kart", "taliz"],
    amulet_vendor: ["amulet", "talizm", "runa"],
    bathhouse: ["laz", "balj", "kamie"],
    courier: ["kurier", "but", "list"],
    tool_repair: ["napraw", "trzon", "majstr"],
    fence_goods: ["skryt", "paser", "most"],
    forged_documents: ["falsz", "pieczec", "dokument"],
    illegal_tools: ["wlam", "klucz", "zasuw"],
    black_book_vendor: ["ksieg", "oklad", "zakaz"],
    jewelry_salon: ["klejnot", "waga", "zl"],
    perfumer: ["flakon", "perfum", "roza"],
    luthier: ["lutni", "strun", "dzwiek"],
    magic_components: ["proch", "komponent", "rytual"],
    true_amulets: ["run", "amulet", "pieczec"],
    curse_identifier: ["klatw", "wsteg", "uczony"],
    artifact_depot: ["artefakt", "skrzyn", "monet"],
    general_stall: ["kram", "worek", "lyzka"],
    soap_lye: ["mydl", "lug", "bania"],
    farm_tools: ["lemiesz", "narzed", "stodol"],
    pottery_stall: ["dzban", "garnk", "mis"],
    holiday_stall: ["swiat", "gwiazd", "piernik"],
    harvest_stall: ["zniw", "snop", "prowiant"],
    import_caravan: ["import", "karawan", "kufer"],
  };
  Object.assign(runtime, {
    TYPE_ANCHORS_BY_ID,
  });
  const FAMILY_HINTS = [
    {
      id: "family_food",
      match: ["piek", "mlyn", "zboz", "mies", "ryb", "nabial", "miod"],
    },
    {
      id: "family_tavern",
      match: ["karcz", "browar", "wini", "gorzel", "wyszynk"],
    },
    {
      id: "family_craft",
      match: ["ciesl", "stolar", "bednar", "garbar", "garncar", "warsztat"],
    },
    {
      id: "family_cloth",
      match: ["kraw", "sukno", "tkacz", "szew", "pasman"],
    },
    {
      id: "family_metal",
      match: ["kowal", "slus", "zbroj", "platn", "rusznik", "zlotnik"],
    },
    {
      id: "family_medicine",
      match: ["cyrulik", "medyk", "apte", "ziel", "alchem", "truc"],
    },
    {
      id: "family_books",
      match: ["skryb", "ksieg", "papier", "map", "notar"],
    },
    {
      id: "family_transport",
      match: ["staj", "kon", "woz", "przewoz", "powroz", "namiot"],
    },
    {
      id: "family_religion",
      match: ["kaplic", "swie", "kadzid", "zakon", "wroz", "astrolog"],
    },
    {
      id: "family_city",
      match: ["laz", "studnia", "kurier", "ratusz", "pogrzeb", "miasto"],
    },
    {
      id: "family_shadow",
      match: [
        "czarny rynek",
        "paser",
        "falszer",
        "nielegal",
        "przemyt",
        "zakaz",
      ],
    },
    {
      id: "family_luxury",
      match: ["luksus", "perfum", "salon", "sztuka", "jubiler"],
    },
    {
      id: "family_magic",
      match: ["magi", "artefakt", "rytua", "run", "klatw"],
    },
  ];
  Object.assign(runtime, {
    FAMILY_HINTS,
  });
  const hasOwnerName = (ownerName) => runtime.toWords(ownerName).length > 0;
  Object.assign(runtime, {
    hasOwnerName,
  });
  const toHistoryKey = (context = {}, familyId = "family_fallback") => {
    const typeId = runtime.toText(context.typeId);
    if (typeId) {
      return `type:${runtime.toLower(typeId)}`;
    }
    const typeName = runtime.toText(context.typeName);
    if (typeName) {
      return `typename:${runtime.toComparable(typeName)}`;
    }
    return `family:${runtime.toLower(familyId)}`;
  };
  Object.assign(runtime, {
    toHistoryKey,
  });
  const getOrCreateHistory = (historyKey) => {
    if (!historyKey) {
      return new Set();
    }
    if (!runtime.ROLL_HISTORY_BY_TYPE.has(historyKey)) {
      runtime.ROLL_HISTORY_BY_TYPE.set(historyKey, new Set());
    }
    return runtime.ROLL_HISTORY_BY_TYPE.get(historyKey);
  };
  Object.assign(runtime, {
    getOrCreateHistory,
  });
  const roleHintForType = (
    context = {},
    family = {},
    randomFn = Math.random,
  ) => {
    const typeId = runtime.toText(context.typeId);
    const fromMap = runtime.TYPE_ROLE_BY_ID[typeId];
    if (fromMap) {
      return fromMap;
    }
    const normalizedType = runtime.toComparable(context.typeName);
    const roles = Array.isArray(family.ownerRoleGenitives)
      ? family.ownerRoleGenitives
      : [];
    const matchedRole = roles.find((role) => {
      const comparableRole = runtime.toComparable(role);
      return (
        comparableRole.length >= 4 &&
        normalizedType.includes(comparableRole.slice(0, 4))
      );
    });
    return matchedRole || runtime.pickRandom(roles, randomFn, "Kupca");
  };
  Object.assign(runtime, {
    roleHintForType,
  });
  const anchorsForType = (context = {}, family = {}) => {
    const typeId = runtime.toText(context.typeId);
    if (typeId && Array.isArray(runtime.TYPE_ANCHORS_BY_ID[typeId])) {
      return runtime.TYPE_ANCHORS_BY_ID[typeId];
    }
    const dynamicFromType = runtime
      .toWords(context.typeName)
      .map((word) => runtime.toComparable(word))
      .filter((word) => word.length >= 4);
    const familyRoots = Array.isArray(family.extraRoots)
      ? family.extraRoots
      : [];
    return runtime.uniq([...dynamicFromType, ...familyRoots]).slice(0, 14);
  };
  Object.assign(runtime, {
    anchorsForType,
  });
  const nameMatchesTypeAnchors = (name, anchors = []) => {
    if (!anchors.length) {
      return true;
    }
    const comparableName = runtime.toComparable(name);
    return anchors.some((anchor) => {
      const comparableAnchor = runtime.toComparable(anchor);
      if (!comparableAnchor) {
        return false;
      }
      return comparableName.includes(comparableAnchor);
    });
  };
  Object.assign(runtime, {
    nameMatchesTypeAnchors,
  });
  const shortTypeLabel = (typeName, fallback = "Sklep") => {
    const normalized = runtime.titleCase(typeName);
    if (!normalized) {
      return fallback;
    }
    const parts = normalized.split(" ").filter(Boolean);
    return parts.length > 2 ? parts.slice(0, 2).join(" ") : normalized;
  };
  Object.assign(runtime, {
    shortTypeLabel,
  });
  const ownerLabel = (ownerName) => runtime.titleCase(ownerName);
  Object.assign(runtime, {
    ownerLabel,
  });
  return {
    TAVERN_SIGN_ROOTS,
    TYPE_ANCHORS_BY_ID,
    FAMILY_HINTS,
    hasOwnerName,
    toHistoryKey,
    getOrCreateHistory,
    roleHintForType,
    anchorsForType,
    nameMatchesTypeAnchors,
    shortTypeLabel,
    ownerLabel,
  };
};
