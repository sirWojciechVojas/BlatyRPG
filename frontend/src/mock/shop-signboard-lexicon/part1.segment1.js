export const createRuntimePart1Segment1 = (runtime) => {
  const uniq = (items = []) =>
    Array.from(
      new Set(items.map((entry) => String(entry || "").trim()).filter(Boolean)),
    );
  Object.assign(runtime, {
    uniq,
  });
  const merge = (...sets) => runtime.uniq(sets.flat());
  Object.assign(runtime, {
    merge,
  });
  const PATTERNS = [
    "CANONICAL",
    "CANONICAL",
    "CANONICAL",
    "POD_SYMBOL",
    "POD_SYMBOL",
    "POD_SYMBOL",
    "EMBLEMATIC_PAIR",
    "EMBLEMATIC_PAIR",
    "NUMBERED_EMBLEM",
    "U_OWNER_NAME",
    "U_OWNER_ROLE_SURNAME",
  ];
  Object.assign(runtime, {
    PATTERNS,
  });
  const COMMON_SURNAMES = runtime.uniq([
    "Huppa",
    "Kressa",
    "Ruppa",
    "Volla",
    "Dorna",
    "Kullera",
    "Grella",
    "Muntza",
    "Jörga",
    "Ralfa",
    "Witta",
    "Dörnera",
    "Albrechta",
    "Menza",
    "Otta",
    "Brandta",
    "Vossa",
    "Klemma",
    "Rudego",
    "Lenza",
    "Pölla",
    "Morka",
    "Tönna",
    "Kriega",
    "Egona",
    "Wurma",
    "Janka",
    "Felsego",
    "Kitta",
    "Gerta",
    "Lothara",
    "Steinera",
    "Brandla",
    "Kramera",
    "Volka",
    "Reissa",
    "Adlera",
    "Mathiasa",
    "Rufusa",
    "Rotha",
    "Groffa",
    "Fynna",
    "Osrika",
    "Bazyla",
    "Josefa",
  ]);
  Object.assign(runtime, {
    COMMON_SURNAMES,
  });
  const LOCATION_ADDONS = {
    metropolia: [
      "przy Wielkim Rynku",
      "pod KamiennÄ… BramÄ…",
      "w Dzielnicy Kupieckiej",
    ],
    miasto: ["przy Rynku", "pod BramÄ… MiejskÄ…"],
    miasteczko: ["przy Ratuszu", "pod Starym Murem", "przy Targowym Placu"],
    wies: ["przy Trakcie", "pod LipÄ…", "obok StodoĹ‚y"],
    jarmark: ["na Jarmarku", "pod Namiotem", "przy Straganie"],
    port: ["przy NabrzeĹĽu", "pod PortowÄ… BramÄ…", "przy Przystani"],
    port_morski: [
      "przy Falochronie",
      "pod MewiÄ… WieĹĽÄ…",
      "nad Basenem Portowym",
    ],
    port_rzeczny: ["nad RzekÄ…", "przy Przeprawie", "pod KĹ‚adkÄ…"],
    forteca: ["pod BasztÄ…", "przy Garnizonie", "u StraĹĽnej Bramy"],
    przy_trakcie: ["przy GoĹ›ciĹ„cu", "na Rozstaju", "pod Zajazdem"],
    obrzeza: ["na ObrzeĹĽach", "za StarÄ… BramÄ…", "przy Cegielni"],
    dzielnica_bogata: [
      "w Dzielnicy ZĹ‚otnikĂłw",
      "przy Arkadach",
      "pod Marmurowym Ĺukiem",
    ],
    dzielnica_biedna: [
      "w Czynszowych ZauĹ‚kach",
      "przy Tylnej Bramie",
      "na Ciemnym PodwĂłrzu",
    ],
    strefa_swiatynna: ["przy Kaplicy", "pod OĹ‚tarzem", "u KruĹĽgankĂłw"],
    strefa_cechowa: [
      "przy Gildii",
      "pod CechowÄ… WieĹĽÄ…",
      "w Dzielnicy WarsztatĂłw",
    ],
  };
  Object.assign(runtime, {
    LOCATION_ADDONS,
  });
  const SEASONAL_ADDONS = {
    caloroczny: [],
    sezonowy: ["na czas targowy", "w porze handlowej", "na ten sezon"],
    wiosna: ["na nowalijki", "wiosennym targiem", "na porÄ™ siewu"],
    lato: ["na czas ĹĽniw", "w letnim handlu", "na kupieckie lato"],
    jesien: ["na jesienne zbiory", "w porze beczek", "na czas zapasĂłw"],
    zima: ["na zimowy jarmark", "w mroĹşnym sezonie", "pod zimowÄ… gwiazdÄ…"],
    zniwa: ["na ĹĽniwny czas", "w porze snopĂłw", "na targ ĹĽniwny"],
    jarmark: ["na jarmarczne dni", "na czas odpustu", "w kupiecki tydzieĹ„"],
    swieta: ["na Ĺ›wiÄ™te dni", "na zimowe Ĺ›wiÄ™ta", "na czas kolÄ™d"],
  };
  Object.assign(runtime, {
    SEASONAL_ADDONS,
  });
  const WORLD_PROFILE_ADDONS = {
    standard: ["", "przy GĹ‚Ăłwnym Trakcie"],
    kampania_tysiac_tronow_v3: [
      "przy Tylnej Bramie",
      "pod CichÄ… LatarniÄ…",
      "w Mrocznym ZauĹ‚ku",
    ],
    roznice_swiatow_v1: [
      "na sezonowym jarmarku",
      "przy Wozowej Drodze",
      "na trakcie kupieckim",
    ],
  };
  Object.assign(runtime, {
    WORLD_PROFILE_ADDONS,
  });
  return {
    uniq,
    merge,
    PATTERNS,
    COMMON_SURNAMES,
    LOCATION_ADDONS,
    SEASONAL_ADDONS,
    WORLD_PROFILE_ADDONS,
  };
};
