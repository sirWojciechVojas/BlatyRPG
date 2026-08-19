export const createRuntimePart2 = (runtime) => {
  const TOKEN_TRANSLATIONS = {
    adra: "adra",
    agate: "agat",
    ale: "ale",
    amethyst: "ametyst",
    ammunition: "amunicja",
    antidotum: "antidotum",
    antitoxin: "antytoksyna",
    antivenom: "antyjad",
    arbalest: "arbalest",
    arrow: "strzała",
    arrows: "strzały",
    axe: "topór",
    bag: "torba",
    battle: "bojowy",
    beer: "piwo",
    belt: "pas",
    black: "czarny",
    blade: "ostrze",
    blunderbuss: "garłacz",
    boat: "łódź",
    bone: "kość",
    book: "księga",
    boots: "buty",
    bottle: "butelka",
    bow: "łuk",
    bracelet: "bransoleta",
    brass: "mosiężny",
    breastplate: "napierśnik",
    brigandine: "brygantyna",
    bronze: "brązowy",
    buckler: "puklerz",
    bullet: "pocisk",
    candle: "świeca",
    cape: "peleryna",
    cheese: "ser",
    cloak: "płaszcz",
    club: "maczuga",
    coin: "moneta",
    copper: "miedziany",
    coral: "koral",
    crate: "skrzynia",
    crossbow: "kusza",
    crown: "korona",
    dagger: "sztylet",
    dart: "lotka",
    diamond: "diament",
    dice: "kości",
    document: "dokument",
    drink: "napój",
    dust: "pył",
    egg: "jajo",
    emerald: "szmaragd",
    exceptional: "wyjątkowy",
    fang: "kieł",
    fine: "dobry",
    fire: "ogień",
    fish: "ryba",
    flail: "kiścień",
    food: "jedzenie",
    fruit: "owoce",
    gauntlet: "rękawica",
    gauntlets: "rękawice",
    gem: "klejnot",
    glass: "szkło",
    glove: "rękawica",
    gloves: "rękawice",
    gold: "złoto",
    golden: "złoty",
    grimoire: "grimuar",
    gunpowder: "proch",
    hammer: "młot",
    harness: "uprząż",
    hat: "kapelusz",
    hatchet: "toporek",
    helm: "hełm",
    helmet: "hełm",
    hide: "skóra",
    horn: "róg",
    horse: "koń",
    ingot: "sztabka",
    ink: "atrament",
    ivory: "kość słoniowa",
    jasper: "jaspis",
    key: "klucz",
    knife: "nóż",
    lantern: "latarnia",
    leather: "skóra",
    letter: "list",
    mace: "buzdygan",
    mail: "kolczuga",
    map: "mapa",
    meat: "mięso",
    mead: "miód pitny",
    moonstone: "kamień księżycowy",
    necklace: "naszyjnik",
    note: "notatka",
    old: "stary",
    onyx: "onyks",
    opal: "opal",
    outfit: "strój",
    pearl: "perła",
    peridot: "perydot",
    pistol: "pistolet",
    plate: "płyta",
    poison: "trucizna",
    potion: "mikstura",
    powder: "proszek",
    primal: "pierwotny",
    quill: "pióro",
    rapier: "rapier",
    reagent: "odczynnik",
    ring: "pierścień",
    robe: "szata",
    ruby: "rubin",
    sabre: "szabla",
    sapphire: "szafir",
    scale: "łuska",
    scroll: "zwój",
    shield: "tarcza",
    silver: "srebrny",
    small: "mały",
    spear: "włócznia",
    spirits: "okowita",
    staff: "kostur",
    standard: "standardowy",
    stiletto: "sztylet",
    stone: "kamień",
    sword: "miecz",
    tome: "tom",
    tool: "narzędzie",
    tooth: "ząb",
    topaz: "topaz",
    torch: "pochodnia",
    trap: "pułapka",
    trinket: "drobiazg",
    turquoise: "turkus",
    tusk: "kieł",
    venom: "jad",
    wand: "różdżka",
    war: "wojenny",
    water: "woda",
    weapon: "broń",
    wine: "wino",
    wood: "drewno",
  };
  Object.assign(runtime, {
    TOKEN_TRANSLATIONS,
  });
  const PRIMARY_RULES = [
    ...runtime.primaryRulesPart1,
    ...runtime.primaryRulesPart2,
    ...runtime.primaryRulesPart3,
  ];
  Object.assign(runtime, {
    PRIMARY_RULES,
  });
  const MANUAL_METADATA = {
    v0018: {
      name: "Zielony klejnot",
      description:
        "Ikona zielonego kamienia szlachetnego rozpoznana w porównaniu katalogów Starych Blatów.",
      specialMarks: "zielony klejnot, kamień szlachetny, perydot",
      typeKeys: ["FORAGE"],
      subtypeKeys: ["ORES_MINERALS"],
      itemClasses: ["FORAGE"],
      itemGenres: ["UTILITY"],
    },
  };
  Object.assign(runtime, {
    MANUAL_METADATA,
  });
  function toTokens(sourceName) {
    return String(sourceName || "")
      .replace(/\.[^.]+$/u, "")
      .replace(/([a-z])([A-Z])/gu, "$1_$2")
      .replace(/\(\d+\)/gu, "")
      .replace(/[^a-zA-Z0-9]+/gu, "_")
      .toLowerCase()
      .split("_")
      .map((token) => token.trim())
      .filter(Boolean)
      .filter((token) => !/^\d+(?:px)?$/u.test(token))
      .filter((token) => !runtime.STOP_TOKENS.has(token));
  }
  Object.assign(runtime, {
    toTokens,
  });
  function hasAny(tokenSet, tokens) {
    return tokens.some((token) => tokenSet.has(token));
  }
  Object.assign(runtime, {
    hasAny,
  });
  function findRule(tokens) {
    const tokenSet = new Set(tokens);
    return (
      runtime.PRIMARY_RULES.find((entry) =>
        runtime.hasAny(tokenSet, entry.tokens),
      ) || {
        tokens: [],
        name: "przedmiot",
        typeKeys: ["MISC"],
        subtypeKeys: ["OTHER"],
        tags: ["przedmiot"],
      }
    );
  }
  Object.assign(runtime, {
    findRule,
  });
  function translateToken(token) {
    return runtime.TOKEN_TRANSLATIONS[token] || token;
  }
  Object.assign(runtime, {
    translateToken,
  });
  function uniqueEntries(entries) {
    return Array.from(
      new Set(
        entries.map((entry) => String(entry || "").trim()).filter(Boolean),
      ),
    );
  }
  Object.assign(runtime, {
    uniqueEntries,
  });
  function capitalize(value) {
    const text = String(value || "").trim();
    if (!text) {
      return "";
    }
    return text.charAt(0).toUpperCase() + text.slice(1);
  }
  Object.assign(runtime, {
    capitalize,
  });
  return {
    TOKEN_TRANSLATIONS,
    PRIMARY_RULES,
    MANUAL_METADATA,
    toTokens,
    hasAny,
    findRule,
    translateToken,
    uniqueEntries,
    capitalize,
  };
};
