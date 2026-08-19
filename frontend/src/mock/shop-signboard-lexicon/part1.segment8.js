export const createRuntimePart1Segment8 = (runtime) => {
  const FAMILY_LUXURY = runtime.makeFamily({
    id: "family_luxury",
    canonicalNames: [
      "ZĹ‚ota Waga",
      "Trzy Flakony",
      "PÄ™kniÄ™ta Lutnia",
      "PÄ™dzel Herbowy",
      "ZĹ‚oty Listek",
      "MĹ‚otek i PieczÄ™Ä‡",
      "Purpurowy ZwĂłj",
      "Kamienny AnioĹ‚",
      "Maska i Dzwonek",
    ],
    symbolPhrases: [
      "Srebrnym Ĺukiem",
      "RĂłĹĽÄ…",
      "DĹşwiÄ™cznym Progiem",
      "Kolorem",
      "Blaskiem",
      "Ratuszem",
      "ZĹ‚otÄ… BramÄ…",
      "DĹ‚utem",
      "Namiotem",
    ],
    emblematicPairs: [
      "PerĹ‚a i Klejnot",
      "Flakon i RĂłĹĽa",
      "Lutnia i Struna",
      "Herb i Proporzec",
      "Listek i Ornament",
      "MĹ‚otek i Waga",
    ],
    numberedEmblems: [
      "Trzy Flakony",
      "Trzy Struny",
      "Dwa Klejnoty",
      "Trzy PÄ™dzle",
    ],
    ownerRoleGenitives: [
      "Mistrza",
      "Perfumiarza",
      "Lutnika",
      "Malarza",
      "ZĹ‚otnika",
      "Aukcjonera",
      "RzeĹşbiarza",
    ],
    extraRoots: [
      "klejnot",
      "galeria",
      "ornament",
      "pozĹ‚otnik",
      "brokat",
      "jedwab",
      "aukcja",
    ],
  });
  Object.assign(runtime, {
    FAMILY_LUXURY,
  });
  const FAMILY_MAGIC = runtime.makeFamily({
    id: "family_magic",
    canonicalNames: [
      "SĹ‚oik z Prochem",
      "Runiczny KrÄ…g",
      "SĂłl i PopiĂłĹ‚",
      "Dwie GĹ‚owy Ryb",
      "Mapa Podziemi",
      "Stara Skrzynia",
      "Czarna WstÄ™ga",
      "BiaĹ‚e KoĹ‚o",
    ],
    symbolPhrases: [
      "GildiÄ…",
      "GwiazdÄ…",
      "KapliczkÄ…",
      "Namiotem",
      "KanaĹ‚em",
      "ĹšwiÄ…tyniÄ…",
      "ZgaszonÄ… LampÄ…",
    ],
    emblematicPairs: [
      "Runa i PieczÄ™Ä‡",
      "Kreda i SĂłl",
      "SzkatuĹ‚a i Moneta",
      "Gwiazda i KrÄ…g",
      "Mapa i Tunel",
      "SzkatuĹ‚a i OsobliwoĹ›Ä‡",
    ],
    numberedEmblems: [
      "Trzy Runy",
      "Dwa Artefakty",
      "Trzy KsiÄ™gi",
      "Dwie Monety",
    ],
    ownerRoleGenitives: [
      "Mistrza",
      "Brata",
      "Kuglarza",
      "Podziemnego Skryby",
      "Kupca OsobliwoĹ›ci",
      "Uczonego",
    ],
    extraRoots: [
      "artefakt",
      "komponent",
      "rytuaĹ‚",
      "klÄ…twa",
      "amulet",
      "talizman",
      "sĂłl ochronna",
    ],
  });
  Object.assign(runtime, {
    FAMILY_MAGIC,
  });
  return {
    FAMILY_LUXURY,
    FAMILY_MAGIC,
  };
};
