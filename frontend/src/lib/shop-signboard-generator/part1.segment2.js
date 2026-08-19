export const createRuntimePart1Segment2 = (runtime) => {
  const toRoman = (value) => {
    const num = Number(value);
    if (!Number.isFinite(num) || num <= 0) {
      return "II";
    }
    const map = [
      [1000, "M"],
      [900, "CM"],
      [500, "D"],
      [400, "CD"],
      [100, "C"],
      [90, "XC"],
      [50, "L"],
      [40, "XL"],
      [10, "X"],
      [9, "IX"],
      [5, "V"],
      [4, "IV"],
      [1, "I"],
    ];
    let n = Math.floor(num);
    let out = "";
    map.forEach(([unit, glyph]) => {
      while (n >= unit) {
        out += glyph;
        n -= unit;
      }
    });
    return out || "II";
  };
  Object.assign(runtime, {
    toRoman,
  });
  const LOW_QUALITY_PATTERNS = [
    /\bRzetelny\s+Pod\b/i,
    /\bPod\s+Znak\b/i,
    /\bCienisty\s+Sklad\s+z\s+Cienia\b/i,
    /\bz\s+Cienia\b.*\bCien/i,
    /\bZly\s+Pod\b/i,
    /\bKarczmiany\b/i,
    /\bBeczyną\b/i,
    /\bPod\s+Lukiem\b/i,
    /\bU\s+(?:Ręki|Grete|Vanna|Peeka|Cichego)\b/i,
    /\bSzyld\b/i,
  ];
  Object.assign(runtime, {
    LOW_QUALITY_PATTERNS,
  });
  const BAD_DOUBLE_PATTERNS = [
    { pattern: /\bPod\s+Pod\b/gi, replacement: "Pod" },
    { pattern: /\bprzy\s+przy\b/gi, replacement: "przy" },
  ];
  Object.assign(runtime, {
    BAD_DOUBLE_PATTERNS,
  });
  const ROLL_HISTORY_BY_TYPE = new Map();
  Object.assign(runtime, {
    ROLL_HISTORY_BY_TYPE,
  });
  const TYPE_ROLE_BY_ID = {
    bakery: "Piekarza",
    miller: "Młynarza",
    grain_warehouse: "Kupca",
    oil_press: "Olejarza",
    smokehouse: "Wędzarza",
    butcher: "Rzeźnika",
    salt_depot: "Solnika",
    vegetable_stall: "Ogrodnika",
    fruit_stall: "Sadownika",
    dairy: "Mleczarza",
    fish_market: "Rybaka",
    brewery_tavern: "Browarnika",
    wine_cellar: "Winiarza",
    distillery: "Gorzelnika",
    inn: "Karczmarza",
    spice_import: "Korzennika",
    carpenter: "Cieśli",
    woodworker: "Stolarza",
    cooper: "Bednarza",
    potter: "Garncarza",
    tanner: "Garbarza",
    saddler: "Rymarza",
    glue_maker: "Klejarza",
    stonemason: "Kamieniarza",
    chimney_sweep: "Kominiarza",
    tailor: "Krawca",
    cloth_merchant: "Sukiennika",
    shoemaker: "Szewca",
    haberdashery: "Pasamonika",
    blacksmith: "Kowala",
    locksmith: "Ślusarza",
    knifemaker: "Nożownika",
    armorer: "Płatnerza",
    guild_armorer: "Zbrojmistrza",
    gunsmith: "Rusznikarza",
    bowyer: "Łuczarza",
    goldsmith: "Złotnika",
    pawnbroker: "Lichwiarza",
    barber_surgeon: "Cyrulika",
    physician: "Doktora",
    apothecary: "Aptekarza",
    herbalist: "Zielarza",
    alchemist: "Alchemika",
    powder_depot: "Prochmistrza",
    poison_vendor: "Truciciela",
    scribe: "Skryby",
    bookshop: "Księgarza",
    cartographer: "Kartografa",
    notary: "Notariusza",
    stable: "Stajennego",
    horse_trader: "Kupca",
    fodder_shop: "Paszarza",
    rope_maker: "Powroźnika",
    tent_maker: "Namiotnika",
    lamp_supplier: "Wytwórcy Latarni",
    devotional_shop: "Świecarza",
    incense_depot: "Kadzidlarza",
    funeral_brotherhood: "Grabarza",
    fortune_teller: "Wróżbity",
    amulet_vendor: "Sprzedawcy Amuletów",
    bathhouse: "Łaziebnika",
    courier: "Posłańca",
    tool_repair: "Majstra",
    fence_goods: "Paserza",
    forged_documents: "Fałszerza",
    illegal_tools: "Włamywacza",
    black_book_vendor: "Księgarza Nocnego",
    jewelry_salon: "Mistrza",
    perfumer: "Perfumiarza",
    luthier: "Lutnika",
    magic_components: "Mistrza",
    true_amulets: "Mistrza Run",
    curse_identifier: "Uczonego",
    artifact_depot: "Kupca Osobliwości",
    general_stall: "Kramarza",
    soap_lye: "Mydlarza",
    farm_tools: "Kupca",
    pottery_stall: "Garncarza",
    holiday_stall: "Piernikarza",
    harvest_stall: "Żniwiarza",
    import_caravan: "Kupca Zamorskiego",
  };
  Object.assign(runtime, {
    TYPE_ROLE_BY_ID,
  });
  return {
    toRoman,
    LOW_QUALITY_PATTERNS,
    BAD_DOUBLE_PATTERNS,
    ROLL_HISTORY_BY_TYPE,
    TYPE_ROLE_BY_ID,
  };
};
