import iconSourceNames from "@/data/trade/inventory-icon-source-names";
import { getIconMetadataOverrides } from "@/lib/trade/iconMetadataRegistry";

const rule = (id, iconClass, patterns, options = {}) => ({
  id,
  iconClass,
  patterns,
  ...options,
});

// One semantic catalogue is used by suggestions, templates, instances and
// ItemIcon. Patterns are Polish word stems so inflected item names still match.
const ICON_RULES = Object.freeze([
  rule("pavise", "v1233", ["pawez"], { source: ["shield large"] }),
  rule("buckler", "v1252", ["pukler", "puklerz"], {
    source: ["buckler", "shield small"],
  }),
  rule("shield", "v1240", ["tarc", "shield"], {
    genres: ["SHIELD"],
    source: ["shield", "buckler"],
    excludeSource: ["potion"],
  }),
  rule("helmet", "v0496", ["helm", "przylbic", "helmet"], {
    genres: ["HEAD"],
    source: ["helm", "helmet"],
  }),
  rule("gauntlets", "v0441", ["rekawic plyt", "naramien", "gauntlet"], {
    source: ["gauntlet", "glove"],
  }),
  rule("gloves", "v0443", ["rekawic", "glove"], {
    source: ["gauntlet", "glove"],
  }),
  rule("boots", "v0318", ["but", "nagolenn", "boots"], {
    source: ["boots"],
  }),
  rule("mail-armor", "v0619", ["kolczug", "zbroj kolcz", "mail armor"], {
    source: ["mail armor"],
  }),
  rule("leather-armor", "v0597", ["pancerz skor", "zbroj skor"], {
    source: ["leather armor"],
  }),
  rule("plate-armor", "v0328", ["kirys", "napier", "zbroj plyt"], {
    source: ["breastplate", "plate armor"],
  }),
  rule("armor", "v0328", ["zbroj", "pancerz"], {
    genres: ["BODY"],
    source: ["armor", "breastplate"],
  }),
  rule("cloak", "v0360", ["plaszcz", "peleryn", "cloak"], {
    source: ["cloak", "cape", "mantle"],
  }),
  rule("hood", "v0475", ["kaptur", "hood"], { source: ["hood"] }),
  rule("robe", "v1160", ["szat", "tunik", "kaftan", "robe"], {
    source: ["robe", "outfit"],
  }),
  rule("belt", "v0222", ["pas", "belt"], { source: ["belt"] }),
  rule("bag", "v0738", ["sakiew", "sakw", "torb", "worek", "bag"], {
    source: ["bag", "pouch", "satchel"],
  }),
  rule("ammunition", "v0189", ["strzal", "belt kusz", "beltow", "amunic"], {
    source: ["arrow", "arrows", "bolt", "ammunition"],
  }),
  rule("arrow-parts", "v0189", ["grot", "lotk strzal"], {
    source: ["arrow", "arrows", "bolt", "bow"],
  }),
  rule("bow-string", "v0543", ["cieciw"], {
    source: ["hunting bow", "war bow"],
  }),
  rule("crossbow", "v0170", ["kusz", "arbalest", "crossbow"], {
    source: ["crossbow", "arbalest"],
  }),
  rule("bow", "v0543", ["luk", "bow"], {
    source: ["hunting bow", "war bow"],
    excludeSource: ["crossbow", "arbalest"],
  }),
  rule("rapier", "v1114", ["rapier", "szpad"], {
    source: ["rapier", "estoc"],
  }),
  rule("dagger", "v0400", ["sztylet", "noz", "kord", "dagger", "knife"], {
    source: ["dagger", "knife", "stiletto"],
  }),
  rule("sword", "v1289", ["miecz", "ostrze", "sword", "blade"], {
    source: ["sword", "estoc", "rapier", "blade"],
  }),
  rule("polearm", "v1262", ["wloczn", "pik", "halabard", "glewi", "spear"], {
    source: ["spear", "pollaxe", "quarterstaff"],
  }),
  rule("axe", "v0199", ["topor", "berdysz", "axe", "hatchet"], {
    source: ["axe", "hatchet", "pollaxe"],
  }),
  rule("war-hammer", "v1347", ["mlot boj", "morgenstern", "war hammer"], {
    source: ["war hammer", "morning star"],
  }),
  rule("mace", "v0610", ["buzdygan", "maczug", "kiscien", "mace", "flail"], {
    source: ["mace", "flail", "club"],
  }),
  rule("staff", "v1105", ["kostur", "lask", "quarterstaff"], {
    source: ["quarterstaff", "staff"],
  }),
  rule("firearm", "v0665", ["pistolet", "rusznic", "garla", "firearm"], {
    source: ["pistol", "blunderbuss", "firearm"],
  }),
  rule("gunpowder", "v0467", ["proch", "gunpowder"], {
    source: ["gunpowder"],
  }),
  rule("poison", "v1075", ["truciz", "jad", "toksyn", "poison"], {
    source: ["potion", "poison", "venom"],
  }),
  rule("salve", "v0694", ["masc", "balsam", "unguent", "salve"], {
    source: ["salve", "unguent", "potion"],
  }),
  rule("incense", "v1015", ["kadzidl", "incense"], {
    source: ["incense"],
  }),
  rule("potion", "v1074", ["mikstur", "eliksir", "odtrut", "nalewk", "tonik"], {
    genres: ["POTION", "HEALING", "TOXINS"],
    source: ["potion", "bottle"],
  }),
  rule("herbal-drink", "v1074", ["kropl", "syrop", "tinktur", "napar"], {
    source: ["potion", "bottle"],
  }),
  rule(
    "alchemy-vessel",
    "v1074",
    ["alembik", "retort", "mozdzierz", "tygiel"],
    {
      source: ["potion", "bottle"],
    },
  ),
  rule(
    "reagent",
    "v1032",
    ["odczynnik", "kwas", "saletr", "siark", "proszek", "pyl wegl"],
    {
      source: ["powder", "dust", "potion"],
    },
  ),
  rule("bottle", "v1374", ["fiolk", "butelk", "buklak", "olej"], {
    source: ["bottle", "potion", "spirits"],
  }),
  rule("bread", "v0112", ["chleb", "bul", "bochen", "wypiek", "loaf"], {
    genres: ["BAKERY"],
    source: ["loaf", "bread"],
  }),
  rule("cheese", "v0109", ["ser", "cheese"], { source: ["cheese"] }),
  rule("meat", "v0104", ["mies", "wedlin", "dziczyz", "meat"], {
    source: ["meat"],
  }),
  rule("fish", "v0110", ["ryb", "sledz", "fish"], { source: ["fish"] }),
  rule("stew", "v0093", ["gulasz", "potraw", "zup", "kasz", "stew"], {
    genres: ["MEALS"],
    source: ["stew", "dish"],
  }),
  rule("beer", "v0082", ["piw", "ale", "beer"], {
    source: ["beer", "ale", "drinking horn"],
  }),
  rule("spirits", "v0118", ["win", "miod pit", "cydr", "okowit"], {
    genres: ["DRINKS"],
    source: ["spirits", "beer", "ale", "bottle"],
  }),
  rule("fruit", "v0119", ["owoc", "jagod", "fruit"], { source: ["fruit"] }),
  rule("vegetables", "v1329", ["warzyw", "ogork", "vegetable"], {
    source: ["vegetable"],
  }),
  rule("grain", "v0457", ["zboz", "owies", "ziarn", "grain"], {
    source: ["grain"],
  }),
  rule("spice", "v1032", ["sol", "przypraw", "spice"], {
    source: ["powder", "dust"],
  }),
  rule("herbs", "v0197", ["ziol", "korzen", "krwawnik", "grzyb", "herb"], {
    source: ["root", "plant", "herb"],
  }),
  rule("book", "v0244", ["ksieg", "ksiazk", "modlitewn", "grymuar", "book"], {
    source: ["book", "grimoire", "tome"],
  }),
  rule("cards", "v1368", ["kart", "talia", "runiczn kosci"], {
    source: ["dice", "book"],
  }),
  rule("map", "v1230", ["map", "plan traktu"], { source: ["map"] }),
  rule("quill", "v0963", ["pior", "stalowk", "atrament", "quill"], {
    source: ["quill", "ink"],
  }),
  rule("seal", "v0640", ["pieczec", "plomb", "lak", "seal"], {
    source: ["seal"],
  }),
  rule("scroll", "v1195", ["pergamin", "zwoj", "dokument", "list", "scroll"], {
    source: ["scroll", "document", "letter"],
  }),
  rule("silver-ring", "v1151", ["piersc srebr", "silver ring"], {
    source: ["ring silver"],
  }),
  rule("ring", "v1127", ["piersc", "sygnet", "ring"], { source: ["ring"] }),
  rule("brooch", "v0585", ["brosz", "brooch"], { source: ["brooch"] }),
  rule("bracelet", "v0685", ["bransolet", "bracelet"], {
    source: ["bracelet"],
  }),
  rule("amulet", "v0127", ["amulet", "medalik", "medalion", "talizman"], {
    source: ["amulet", "necklace", "medallion"],
  }),
  rule("lockpick", "v0609", ["wytrych", "lockpick"], { source: ["lockpick"] }),
  rule("key", "v0555", ["klucz", "key"], { source: ["key"] }),
  rule("lantern", "v1041", ["latar", "lamp", "lantern"], {
    source: ["lantern"],
  }),
  rule("torch", "v1304", ["pochod", "swiec", "torch"], {
    source: ["torch", "candle"],
  }),
  rule("flint", "v0437", ["krzesiw", "hubk", "flint"], {
    source: ["flint", "tinder"],
  }),
  rule("rope", "v1030", ["lin", "powroz", "sznur", "rope"], {
    source: ["rope"],
  }),
  rule("pulley", "v1030", ["blok kraz", "bloczek"], { source: ["rope"] }),
  rule("snare", "v1362", ["sidla", "pulapk", "snare", "trap"], {
    source: ["snare", "trap"],
  }),
  rule("work-hammer", "v1058", ["mlot", "mlotek", "hammer"], {
    source: ["workmans hammer", "hammer chisel"],
  }),
  rule(
    "ironwork",
    "v0451",
    ["sztab", "gwozd", "nit", "pret", "okuci", "ingot"],
    {
      source: ["ingot"],
    },
  ),
  rule("lock", "v0555", ["klodk", "zamek", "zawias"], {
    source: ["key", "lock"],
  }),
  rule("crate", "v1042", ["skrzyn", "pudel", "puzder", "beczk", "crate"], {
    source: ["crate", "box"],
  }),
  rule("dice", "v1368", ["kosci", "dice"], { source: ["dice"] }),
  rule("horse-harness", "v1373", ["uprzaz", "siodl", "uzd", "harness"], {
    source: ["harness", "horse"],
  }),
  rule("horse", "v0653", ["kon", "mul", "wierzch", "horse"], {
    source: ["horse"],
  }),
  rule("dog", "v0650", ["pies", "ogar", "dog"], {
    source: ["beagle", "lab", "fafik"],
  }),
  rule("bird", "v1259", ["ges", "ptak", "bird"], { source: ["songbird"] }),
  rule("cloth", "v1361", ["plotn", "filc", "wsteg", "wstaz", "koc"], {
    source: ["cloth", "outfit", "cloak"],
  }),
  rule("wax", "v1304", ["wosk", "knot"], { source: ["torch", "candle"] }),
  rule("mirror", "v1088", ["lustro", "lusterk", "zwierciadl"], {
    source: ["mirror"],
  }),
  rule("chain", "v0802", ["lancuch", "lancuszek"], {
    source: ["chain", "necklace"],
  }),
  rule("tableware", "v0739", ["kubek", "mis", "garnek", "kielich", "chalice"], {
    source: ["chalice", "dish"],
  }),
]);

const CLASS_FALLBACKS = Object.freeze({
  ALCHEMY: "v1074",
  ANIMAL: "v0653",
  ARMAMENT: "v0189",
  ARMOR: "v0328",
  CLOTH: "v0360",
  CUTLERY: "v0739",
  FOOD: "v0093",
  FORAGE: "v0197",
  GADGET: "v1042",
  JEWELLERY: "v0127",
  MAGIC: "v0244",
  MISC: "v1042",
  POTION: "v1074",
  POWDER: "v0467",
  STATIONERY: "v0244",
  TOOL: "v1058",
  WEAPON: "v1289",
});

const LEGACY_GENERIC_ICONS = new Set([
  "v0001",
  "v0170",
  "v0619",
  "v0724",
  "v1030",
  "v1041",
  "v1089",
  "v1148",
]);

const normalizeText = (value) =>
  String(value || "")
    .replace(/[łŁ]/g, "l")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, " ")
    .trim();

const tokensFor = (value) => normalizeText(value).split(/\s+/).filter(Boolean);
const matchesPattern = (tokens, pattern) => {
  const expected = tokensFor(pattern);
  return (
    expected.length > 0 &&
    expected.every((stem) => tokens.some((token) => token.startsWith(stem)))
  );
};

const itemContext = (item = {}) => ({
  name: String(
    item.PERSONAL_PSEU ||
      item.NAME ||
      item.name ||
      item.displayName ||
      item.templateName ||
      item.label ||
      "",
  ),
  description: String(
    item.PERSONAL_DESC || item.DESCRIPTION || item.description || "",
  ),
  itemClass: String(item.ITEM_CLASS || item.itemClass || item.classKey || "")
    .trim()
    .toUpperCase(),
  itemGenre: String(item.ITEM_GENRE || item.itemGenre || item.genreKey || "")
    .trim()
    .toUpperCase(),
  currentIcon: String(item.IMG_CLASS || item.imgClass || "")
    .trim()
    .toLowerCase(),
});

const scoreRule = (iconRule, context) => {
  const nameTokens = tokensFor(context.name);
  const descriptionTokens = tokensFor(context.description);
  let best = Number.NEGATIVE_INFINITY;
  iconRule.patterns.forEach((pattern) => {
    const weight = tokensFor(pattern).length * 12;
    if (matchesPattern(nameTokens, pattern))
      best = Math.max(best, 400 + weight);
    else if (matchesPattern(descriptionTokens, pattern)) {
      best = Math.max(best, 80 + weight);
    }
  });
  if (iconRule.genres?.includes(context.itemGenre)) best = Math.max(best, 300);
  return best;
};

const matchingRule = (context) => {
  let bestRule = null;
  let bestScore = Number.NEGATIVE_INFINITY;
  ICON_RULES.forEach((iconRule) => {
    const score = scoreRule(iconRule, context);
    if (score > bestScore) {
      bestRule = iconRule;
      bestScore = score;
    }
  });
  return Number.isFinite(bestScore) ? bestRule : null;
};

const sourceMatchesRule = (iconClass, iconRule) => {
  const source = normalizeText(iconSourceNames[iconClass] || "");
  if (!source || !iconRule) return false;
  if ((iconRule.excludeSource || []).some((term) => source.includes(term))) {
    return false;
  }
  const sourceTokens = tokensFor(source);
  return (iconRule.source || []).some((pattern) =>
    matchesPattern(sourceTokens, pattern),
  );
};

const resolveCustomMetadataIcon = (item = {}) => {
  const context = itemContext(item);
  const nameTokens = tokensFor(context.name);
  const descriptionTokens = tokensFor(context.description);
  let winner = "";
  let winnerScore = 0;
  Object.entries(getIconMetadataOverrides()).forEach(
    ([iconClass, metadata]) => {
      let score = 0;
      if ((metadata.itemClasses || []).includes(context.itemClass)) score += 40;
      if ((metadata.itemGenres || []).includes(context.itemGenre)) score += 220;
      const phrases = String(metadata.specialMarks || "")
        .split(/[,;\n]+/u)
        .map((value) => value.trim())
        .filter(Boolean);
      phrases.forEach((phrase) => {
        if (matchesPattern(nameTokens, phrase)) score += 420;
        else if (matchesPattern(descriptionTokens, phrase)) score += 90;
      });
      if (score > winnerScore) {
        winner = iconClass;
        winnerScore = score;
      }
    },
  );
  return winnerScore >= 120 ? winner : "";
};

export const resolveItemIcon = (item = {}) => {
  const customIcon = resolveCustomMetadataIcon(item);
  if (customIcon) {
    return {
      iconClass: customIcon,
      ruleId: "custom-metadata",
      reason: "icon-metadata",
    };
  }
  const context = itemContext(item);
  const iconRule = matchingRule(context);
  if (iconRule) {
    const preserveCurrent = sourceMatchesRule(context.currentIcon, iconRule);
    const iconClass = preserveCurrent
      ? context.currentIcon
      : iconRule.iconClass;
    return {
      iconClass,
      ruleId: iconRule.id,
      changed: Boolean(
        context.currentIcon && context.currentIcon !== iconClass,
      ),
      confidence: "semantic",
    };
  }
  if (
    /^v\d{4}$/u.test(context.currentIcon) &&
    !LEGACY_GENERIC_ICONS.has(context.currentIcon)
  ) {
    return {
      iconClass: context.currentIcon,
      ruleId: "explicit",
      changed: false,
      confidence: "explicit",
    };
  }
  return {
    iconClass: CLASS_FALLBACKS[context.itemClass] || "v0001",
    ruleId: `class:${context.itemClass || "MISC"}`,
    changed: false,
    confidence: "class",
  };
};

export const resolveItemIconClass = (item = {}) =>
  resolveItemIcon(item).iconClass;

export const withResolvedItemIcon = (item = {}) => {
  const iconClass = resolveItemIconClass(item);
  if (Object.prototype.hasOwnProperty.call(item, "imgClass")) {
    return { ...item, imgClass: iconClass };
  }
  return { ...item, IMG_CLASS: iconClass };
};

export const withResolvedSuggestionIcon = (suggestion = {}) => {
  const draft = suggestion?.draftTemplate || null;
  const source = draft || {
    NAME:
      suggestion.displayName ||
      suggestion.templateName ||
      suggestion.label ||
      "",
    DESCRIPTION: suggestion.description || "",
    ITEM_CLASS: suggestion.classKey || "",
    ITEM_GENRE: suggestion.genreKey || "",
    IMG_CLASS: suggestion.imgClass || "",
  };
  const iconClass = resolveItemIconClass(source);
  return {
    ...suggestion,
    imgClass: iconClass,
    ...(draft ? { draftTemplate: { ...draft, IMG_CLASS: iconClass } } : {}),
  };
};

export const withResolvedSuggestionIcons = (suggestions = []) =>
  (Array.isArray(suggestions) ? suggestions : []).map(
    withResolvedSuggestionIcon,
  );

export { CLASS_FALLBACKS, ICON_RULES, LEGACY_GENERIC_ICONS };
