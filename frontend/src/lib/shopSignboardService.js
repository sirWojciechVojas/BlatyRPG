import { generateShopSignboard } from "@/lib/shopSignboardGenerator";

const TYPE_ID_ALIASES = {
  aptekarz: "apothecary",
  dom_kupiecki: "import_caravan",
  drukarz: "bookshop",
  kantor: "pawnbroker",
  karczma: "brewery_tavern",
  korzennik: "spice_import",
  kowal: "blacksmith",
  kram: "general_stall",
  paserska_komora: "fence_goods",
  piekarnia: "bakery",
  platnerz: "armorer",
  sklad_cechowy: "general_stall",
  sklad_kontrabandy: "fence_goods",
  sklad_soli: "salt_depot",
  sklad_towarow: "general_stall",
  skryptorium: "scribe",
  sukiennik: "cloth_merchant",
  zajazd: "inn",
  zlotnik: "goldsmith",
};

const TYPE_MATCHERS = [
  [/piekar/, "bakery"],
  [/mlyn|młyn/, "miller"],
  [/spich|zboz|zboż/, "grain_warehouse"],
  [/wedzar|wędzar/, "smokehouse"],
  [/jatk|rzezn|rzeźn|miesn|mięsn/, "butcher"],
  [/warzyw/, "vegetable_stall"],
  [/owoc|sadown/, "fruit_stall"],
  [/serow|nabial|nabiał|mlecz/, "dairy"],
  [/rybar|rybn/, "fish_market"],
  [/karcz|oberz|oberż|gospod|szynk|tawern|piwiar|miodosyt/, "brewery_tavern"],
  [/zajazd|nocleg|goscinn|gościnn|stancj/, "inn"],
  [/winiar|sklad_win|skład_win/, "wine_cellar"],
  [/gorzel|destylar/, "distillery"],
  [/korzenn|przypraw/, "spice_import"],
  [/ciesl|cieśl/, "carpenter"],
  [/stolar/, "woodworker"],
  [/bednar/, "cooper"],
  [/garncar/, "potter"],
  [/garbar|skornik|skórnik/, "tanner"],
  [/rymar|siodlar/, "saddler"],
  [/krawiec/, "tailor"],
  [/sukien|tkacz|lniarz/, "cloth_merchant"],
  [/szewc/, "shoemaker"],
  [/pasamon/, "haberdashery"],
  [/kowal|zelaznik|żelaznik/, "blacksmith"],
  [/slusar|ślusar/, "locksmith"],
  [/nozown|nożown|ostrzar/, "knifemaker"],
  [/platner|płatner|zbrojown|tarczown/, "armorer"],
  [/rusznik/, "gunsmith"],
  [/luczar|łuczar|kusznik/, "bowyer"],
  [/zlotnik|złotnik|jubiler|srebrnik/, "goldsmith"],
  [/cyrulik/, "barber_surgeon"],
  [/aptekar/, "apothecary"],
  [/zielarz/, "herbalist"],
  [/alchem/, "alchemist"],
  [/truciciel/, "poison_vendor"],
  [/skryb|skryptor|drukar|pergamenn|inkaust/, "scribe"],
  [/ksiegar|księgar|antykwariusz/, "bookshop"],
  [/kartograf/, "cartographer"],
  [/stajni/, "stable"],
  [/koni|konny|końsk/, "horse_trader"],
  [/pasz/, "fodder_shop"],
  [/powrozn|powroźn/, "rope_maker"],
  [/dewocjon|swiatynn|świątynn|relikwi/, "devotional_shop"],
  [/kadziel/, "incense_depot"],
  [/wroz|wróż/, "fortune_teller"],
  [/paser|kontraband|przemyt|melin|mordown/, "fence_goods"],
  [/pachnid|perfum/, "perfumer"],
  [/amulet|talizman/, "amulet_vendor"],
  [/artefakt|osobliw/, "artifact_depot"],
  [/kram|stragan|targow/, "general_stall"],
  [/kupiec|kupiecki|karawan/, "import_caravan"],
];

const normalized = (value) =>
  String(value || "")
    .trim()
    .toLocaleLowerCase("pl-PL")
    .replace(/\s+/g, "_");

export const localizedShopTypeLabel = (type = {}, locale = "pl") => {
  const polish = String(locale || "pl")
    .toLowerCase()
    .startsWith("pl");
  return String(
    (polish ? type.labelPl : type.labelEn) ||
      type.labelPl ||
      type.labelEn ||
      type.name ||
      type.id ||
      "",
  ).trim();
};

export const resolveSignboardGeneratorTypeId = (typeId, typeName = "") => {
  const sourceId = normalized(typeId);
  if (TYPE_ID_ALIASES[sourceId]) return TYPE_ID_ALIASES[sourceId];
  const searchable = `${sourceId} ${normalized(typeName)}`;
  return (
    TYPE_MATCHERS.find(([matcher]) => matcher.test(searchable))?.[1] || sourceId
  );
};

export const drawShopSignboard = (
  {
    typeId = "",
    typeOptions = [],
    locale = "pl",
    profile = {},
    ownerName = "",
    existingNames = [],
  } = {},
  options = {},
) => {
  const selectedType = typeOptions.find(
    (type) => String(type.id) === String(typeId),
  );
  const typeName = localizedShopTypeLabel(selectedType, locale);
  const generatorTypeId = resolveSignboardGeneratorTypeId(typeId, typeName);
  const result = generateShopSignboard(
    {
      typeId: generatorTypeId,
      typeName,
      groupName: selectedType?.category || "",
      domainName: selectedType?.category || "",
      locationType: profile.locationType || "miasto",
      worldProfileId: profile.worldProfileId || "standard",
      legalStatus: profile.legalStatus || "legal",
      wealthTier: profile.wealthTier || "standard",
      reputation: profile.reputation || "neutralna",
      seasonality: profile.seasonality || "caloroczny",
      ownerCode: profile.ownerCode || "NPC",
      ownerName: ownerName || profile.ownerName || "",
      existingNames,
    },
    { mode: "mixed", style: "medieval_lore", ...options },
  );
  return {
    ...result,
    meta: {
      ...result.meta,
      requestedTypeId: String(typeId || ""),
      generatorTypeId,
      typeName,
    },
  };
};

export default drawShopSignboard;
