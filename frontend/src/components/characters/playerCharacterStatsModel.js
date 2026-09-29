const EMPTY_VALUE = "—";

const objectValue = (value) =>
  value && typeof value === "object" && !Array.isArray(value) ? value : {};

const ownValue = (source, aliases) => {
  const object = objectValue(source);
  for (const alias of aliases) {
    if (
      Object.prototype.hasOwnProperty.call(object, alias) &&
      object[alias] !== null &&
      object[alias] !== ""
    ) {
      return object[alias];
    }
  }
  return undefined;
};

const displayValue = (value) => {
  if (value === undefined || value === null || value === "") return EMPTY_VALUE;
  if (typeof value === "number" && !Number.isFinite(value)) return EMPTY_VALUE;
  return String(value);
};

const numericValue = (value) => {
  if (value === undefined || value === null || value === "") return null;
  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : null;
};

const TRAIT_ALIASES = {
  ww: ["ww", "weaponSkill", "weapon_skill", "WEAPONSKILL"],
  us: ["us", "ballisticSkill", "ballistic_skill", "BALLISTICSKILL"],
  k: ["k", "strength", "STRENGTH"],
  odp: ["odp", "toughness", "TOUGHNESS"],
  zr: ["zr", "agility", "AGILITY"],
  int: ["int", "intelligence", "INTELLIGENCE"],
  sw: ["sw", "willpower", "WILLPOWER"],
  ogd: ["ogd", "fellowship", "FELLOWSHIP"],
  a: ["a", "attack", "attacks", "ATTACK"],
  zyw: ["zyw", "hp", "wounds", "WOUNDS"],
  sz: ["sz", "movement", "move", "MOVEMENT"],
  mag: ["mag", "magic", "MAGIC"],
  s: ["s", "strengthBonus", "strength_bonus", "STRENGTHBONUS"],
  wt: ["wt", "toughnessBonus", "toughness_bonus", "TOUGHNESSBONUS"],
  po: ["po", "fatePoints", "fate_points", "FATEPOINTS"],
  pp: ["pp", "fortunePoints", "fortune_points", "FORTUNEPOINTS"],
};

const MAIN_TRAITS = ["ww", "us", "k", "odp", "zr", "int", "sw", "ogd"];
const SECONDARY_TRAITS = ["zyw", "a", "sz", "mag"];

const traitLayer = (attributes, aliases) => {
  for (const alias of aliases) {
    const layer = objectValue(attributes[alias]);
    if (Object.keys(layer).length) return layer;
  }
  return {};
};

const legacyTrait = (data, key) => {
  for (const alias of TRAIT_ALIASES[key]) {
    const value = objectValue(data[alias]);
    if (Object.keys(value).length) return value;
  }
  return {};
};

const layerTraitValue = (layer, key) => ownValue(layer, TRAIT_ALIASES[key]);

const createTrait = (data, key, step) => {
  const attributes = objectValue(data.attributes);
  const start = traitLayer(attributes, ["start", "initial", "base"]);
  const advances = traitLayer(attributes, ["advances", "advance"]);
  const actual = traitLayer(attributes, ["actual", "current"]);
  const legacy = legacyTrait(data, key);
  const base = layerTraitValue(start, key) ?? ownValue(legacy, ["in", "base"]);
  const advance =
    layerTraitValue(advances, key) ?? ownValue(legacy, ["adv", "advance"]);
  const total =
    layerTraitValue(actual, key) ?? ownValue(legacy, ["cur", "current"]);
  const advanceNumber = numericValue(advance);
  return {
    key,
    labelKey: `vtt.table.characterStats.traits.${key}`,
    base: displayValue(base),
    advance: displayValue(advance),
    total: displayValue(total),
    rank:
      advanceNumber === null
        ? 0
        : Math.max(0, Math.min(8, Math.floor(advanceNumber / step))),
    step,
  };
};

const detailValue = (data, aliases) =>
  displayValue(
    ownValue(objectValue(data.details), aliases) ?? ownValue(data, aliases),
  );

const optionalDetailValue = (data, aliases) => {
  const value =
    ownValue(objectValue(data.details), aliases) ?? ownValue(data, aliases);
  return value === undefined || value === null || value === ""
    ? null
    : String(value);
};

const normalizeEntry = (entry, talent = false) => {
  if (typeof entry === "string" || typeof entry === "number") {
    return {
      name: displayValue(entry),
      description: "",
      status: 1,
    };
  }
  const source = objectValue(entry);
  const status = Math.max(
    1,
    Math.min(
      talent ? 2 : 3,
      Number(source.status ?? source.rank ?? source.level) || 1,
    ),
  );
  return {
    name: displayValue(source.name ?? source.label ?? source.title),
    description: String(source.description ?? source.details ?? ""),
    status,
  };
};

const normalizeEntries = (entries, talent = false) =>
  (Array.isArray(entries) ? entries : []).map((entry) =>
    normalizeEntry(entry, talent),
  );

const createHistory = (data) => {
  const raw =
    ownValue(objectValue(data.details), ["history"]) ??
    ownValue(data, ["history", "HISTORY"]);
  const history = Array.isArray(raw) ? raw : raw ? [raw] : [];
  const addition = optionalDetailValue(data, [
    "history_addition",
    "historyAddition",
  ]);
  const secret = optionalDetailValue(data, ["secret", "character_secret"]);
  if (addition && !history[1]) history[1] = addition;
  if (secret && !history[2]) history[2] = secret;
  return history.map(displayValue);
};

const createWallet = (character) => {
  const brass = Math.max(0, Math.floor(Number(character.brass) || 0));
  const bretonnian = String(character.primaryCurrencyCode || "")
    .toLowerCase()
    .includes("breton");
  if (bretonnian) {
    return {
      system: "bretonnia",
      crown: Math.floor(brass / 80),
      shilling: Math.floor(((brass % 80) * 120) / 80),
      penny: null,
    };
  }
  return {
    system: "empire",
    crown: Math.floor(brass / 240),
    shilling: Math.floor((brass % 240) / 12),
    penny: brass % 12,
  };
};

const armorValue = (data, aliases) => {
  const armor = objectValue(data.armor);
  return displayValue(
    ownValue(armor, aliases) ??
      ownValue(objectValue(data.details), aliases) ??
      ownValue(data, aliases),
  );
};

export const createPlayerCharacterStatsModel = (character = {}) => {
  const data = objectValue(character.data);
  const attributes = objectValue(data.attributes);
  const trueName = detailValue(data, ["true_name", "trueName", "NAME"]);
  const usedName = detailValue(data, ["name", "used_name", "USEDNAME"]);
  return {
    id: Number(character.id) || null,
    name: displayValue(character.name),
    trueName:
      trueName === EMPTY_VALUE ? displayValue(character.name) : trueName,
    usedName:
      usedName === EMPTY_VALUE ? displayValue(character.name) : usedName,
    mainTraits: MAIN_TRAITS.map((key) => createTrait(data, key, 5)),
    secondaryTraits: SECONDARY_TRAITS.map((key) => createTrait(data, key, 1)),
    points: {
      strengthBonus: createTrait(data, "s", 1).total,
      toughnessBonus: createTrait(data, "wt", 1).total,
      fatePoints: createTrait(data, "po", 1).total,
      fortunePoints: createTrait(data, "pp", 1).total,
      insanityPoints: detailValue(data, [
        "insanity_points",
        "insanityPoints",
        "INSANITYPOINTS",
      ]),
      motivationPoints: detailValue(data, [
        "motivation_points",
        "motivationPoints",
        "MOTIVATEPOINTS",
      ]),
    },
    armor: {
      head: armorValue(data, ["head", "HEAD"]),
      body: armorValue(data, ["body", "BODY"]),
      rightArm: armorValue(data, ["right_arm", "rightArm", "RIGHTHAND"]),
      leftArm: armorValue(data, ["left_arm", "leftArm", "LEFTHAND"]),
      rightLeg: armorValue(data, ["right_leg", "rightLeg", "RIGHTLEG"]),
      leftLeg: armorValue(data, ["left_leg", "leftLeg", "LEFTLEG"]),
    },
    details: {
      currentProfession: detailValue(data, [
        "current_profession",
        "currentProfession",
        "profession",
        "profession_name",
        "profession_id",
      ]),
      previousProfession: detailValue(data, [
        "previous_profession",
        "previousProfession",
      ]),
      race: detailValue(data, ["race", "breed", "BREED"]),
      sex: detailValue(data, ["sex", "SEX"]),
      deity: detailValue(data, ["deity", "god", "WORSHIPGOD"]),
      eyes: detailValue(data, ["eye_color", "eyes", "EYESCOLOUR"]),
      hair: detailValue(data, ["hair_color", "hair", "HAIRCOLOUR"]),
      birthplace: detailValue(data, ["birthplace", "place", "BIRTHPLACE"]),
      age: detailValue(data, ["age", "AGE"]),
      height: detailValue(data, ["height", "HEIGHT"]),
      weight: detailValue(data, ["weight", "WEIGHT"]),
      siblings: detailValue(data, ["siblings", "SIBLINGS"]),
      starSign: detailValue(data, ["star_sign", "starSign", "STARSIGN"]),
      disorders: detailValue(data, ["disorders", "DISORDERS"]),
      specialSigns: detailValue(data, [
        "special_signs",
        "specialSigns",
        "SPECIALSIGNS",
      ]),
      scars: detailValue(data, [
        "wounds_and_scars",
        "woundsAndScars",
        "WOUNDS&SCARS",
      ]),
    },
    history: createHistory(data),
    skills: normalizeEntries(attributes.skills ?? data.skills),
    talents: normalizeEntries(attributes.talents ?? data.talents, true),
    wallet: createWallet(character),
  };
};

export { EMPTY_VALUE };
