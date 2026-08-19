export const createRuntimePart3Segment1 = (runtime) => {
  const fallbackNamesByGenre = Object.freeze({
    UTILITY: [
      "Narzedzie codzienne",
      "Skrzynka warsztatowa",
      "Pojemnik transportowy",
      "Uchwyt stalowy",
    ],
    HEALING: [
      "Napar rumiankowy",
      "Masc ziolowa",
      "Plaster zywiczny",
      "Tonik regeneracyjny",
    ],
    BUFFS: [
      "Eliksir sily",
      "Tonik koncentracji",
      "Nalewka odwagi",
      "Mikstura wytrzymalosci",
    ],
    TOXINS: [
      "Proszek drazniacy",
      "Toksyna usypiajaca",
      "Odczynnik toksyczny",
      "Fiolka neutralizujaca",
    ],
    DRINKS: ["Piwo miodowe", "Cydr jablkowy", "Wino korzenne", "Syrop ziolowy"],
    PRESERVES: [
      "Ogorki kiszone",
      "Sledz solony",
      "Suszone jablka",
      "Sloik marynowany",
    ],
    SPICES_HERBS: [
      "Mieszanka ziol",
      "Korzen aromatyczny",
      "Susz przyprawowy",
      "Liscie lecznicze",
    ],
    AMMUNITION: [
      "Belty kusznicze",
      "Lont palny",
      "Grot hartowany",
      "Lotka strzaly",
    ],
    BODY: [
      "Kaftan ochronny",
      "Naramiennik stalowy",
      "Rekawice pancerne",
      "Nagolennik kuty",
    ],
    DOCUMENTS: [
      "Pergamin urzedowy",
      "Karta zapisowa",
      "Lak pieczetny",
      "Etykieta pergaminowa",
    ],
    WRITING_TOOLS: [
      "Pioro gesie",
      "Atrament roboczy",
      "Stalowka pisarska",
      "Nozyk introligatorski",
    ],
  });
  Object.assign(runtime, {
    fallbackNamesByGenre,
  });
  const fallbackNamesForClassGenre = (itemClass, itemGenre) => {
    const classKey = runtime.toUpper(itemClass || "TOOL");
    const genreKey = runtime.toUpper(itemGenre || "UTILITY");
    const exampleKey = `${classKey}:${genreKey}`;
    const byExample = runtime.classGenreExamples[exampleKey] || [];
    const byClass = runtime.fallbackNamesByClass[classKey] || [];
    const byGenre = runtime.fallbackNamesByGenre[genreKey] || [];
    return runtime.uniqueArray([...byExample, ...byClass, ...byGenre]);
  };
  Object.assign(runtime, {
    fallbackNamesForClassGenre,
  });
  const recommendationByScore = (score) => {
    const value = Number(score || 0);
    if (value >= 120) {
      return {
        code: "add",
        labelPl: "Dodaj",
        reasonPl: "Wysokie dopasowanie do profilu sklepu.",
        weight: 3,
      };
    }
    if (value >= 50) {
      return {
        code: "consider",
        labelPl: "Rozwaz",
        reasonPl: "Czesciowe dopasowanie; zalezne od stylu sklepu.",
        weight: 2,
      };
    }
    return {
      code: "skip",
      labelPl: "Pomin",
      reasonPl: "Niskie dopasowanie do aktualnego profilu sklepu.",
      weight: 1,
    };
  };
  Object.assign(runtime, {
    recommendationByScore,
  });
  const fallbackSuggestionRecommendation = {
    code: "consider",
    labelPl: "Rozwaz",
    reasonPl: "Awaryjna sugestia dla typu sklepu o rzadkim profilu.",
    weight: 2,
  };
  Object.assign(runtime, {
    fallbackSuggestionRecommendation,
  });
  const representativeSuggestionRecommendation = {
    code: "consider",
    labelPl: "Rozwaz",
    reasonPl: "Wybrano jako reprezentatywna pozycje dla profilu sklepu.",
    weight: 2,
  };
  Object.assign(runtime, {
    representativeSuggestionRecommendation,
  });
  const balancedSuggestionRecommendation = {
    code: "consider",
    labelPl: "Rozwaz",
    reasonPl: "Wybrano dla zbalansowania zestawu sugestii sklepu.",
    weight: 2,
  };
  Object.assign(runtime, {
    balancedSuggestionRecommendation,
  });
  const examplesForClassGenre = (itemClass, itemGenre) => {
    const key = `${runtime.toUpper(itemClass)}:${runtime.toUpper(itemGenre)}`;
    return runtime.classGenreExamples[key] || [];
  };
  Object.assign(runtime, {
    examplesForClassGenre,
  });
  const tokenize = (value) =>
    String(value || "")
      .toLowerCase()
      .split(/[^\p{L}\p{N}]+/u)
      .map((part) => part.trim())
      .filter((part) => part.length >= 4);
  Object.assign(runtime, {
    tokenize,
  });
  const uniqueArray = (values = []) =>
    Array.from(new Set(values.filter(Boolean)));
  Object.assign(runtime, {
    uniqueArray,
  });
  return {
    fallbackNamesByGenre,
    fallbackNamesForClassGenre,
    recommendationByScore,
    fallbackSuggestionRecommendation,
    representativeSuggestionRecommendation,
    balancedSuggestionRecommendation,
    examplesForClassGenre,
    tokenize,
    uniqueArray,
  };
};
