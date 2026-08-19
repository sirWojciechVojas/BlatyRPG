export const createRuntimePart2Segment1 = () => {
  const wealthKeywordMap = {
    nedzny: {
      prefer: ["tani", "prosty", "używ", "napraw", "szary"],
      avoid: ["luks", "złoc", "srebr", "mistrz", "import", "szlache"],
    },
    biedny: {
      prefer: ["tani", "prosty", "napraw", "codzien", "podstaw"],
      avoid: ["luks", "złoc", "import", "szlache"],
    },
    standard: {
      prefer: ["solid", "warsztat", "cech", "codzien"],
      avoid: [],
    },
    bogaty: {
      prefer: ["mistrz", "precyz", "szlache", "import", "ochron"],
      avoid: ["używ", "szary"],
    },
    elitarny: {
      prefer: ["luks", "złoc", "srebr", "mistrz", "szlache", "egzot"],
      avoid: ["tani", "prosty", "używ"],
    },
    luksusowy: {
      prefer: ["luks", "złoc", "srebr", "mistrz", "szlache", "egzot", "salon"],
      avoid: ["tani", "prosty", "używ", "napraw"],
    },
  };
  const reputationKeywordMap = {
    fatalna: {
      prefer: ["szary", "używ", "podejrz", "dyskret", "spod lady"],
      avoid: ["mistrz", "cech", "licenc", "precyz"],
    },
    zla: {
      prefer: ["podejrz", "dyskret", "szary", "nocny"],
      avoid: ["mistrz", "cech", "licenc"],
    },
    podejrzana: {
      prefer: ["dyskret", "szary", "spod lady"],
      avoid: ["salon", "mistrz"],
    },
    neutralna: {
      prefer: ["solid", "warsztat", "codzien"],
      avoid: [],
    },
    dobra: {
      prefer: ["solid", "mistrz", "cech", "precyz", "ochron"],
      avoid: ["używ", "podejrz"],
    },
    znakomita: {
      prefer: ["mistrz", "cech", "licenc", "precyz", "szlache", "ochron"],
      avoid: ["używ", "podejrz", "szary"],
    },
  };
  const blockedSuggestionNameTokens = new Set([
    "cechowy",
    "cechowa",
    "cechowe",
    "seria",
    "partia",
    "pakiet",
    "dostawa",
    "edycja",
    "wariant",
    "zestaw",
  ]);
  const blockedSuggestionNamePhrases = [
    "dostawa targowa",
    "edycja cechowa",
    "pakiet handlowy",
  ];
  const bannedSuggestionSuffixPatterns = [
    /\s+z uchwytem\b/iu,
    /\s+do pasa\b/iu,
    /\s+z zamkiem\b/iu,
    /\s+do podrozy\b/iu,
    /\s+do podróży\b/iu,
    /\s+do warsztatu\b/iu,
    /\s+z pokrowcem\b/iu,
    /\s+na pasek\b/iu,
    /\s+z zaczepem\b/iu,
  ];
  const personalizedVariantQualifiers = [
    "podstawowy",
    "solidny",
    "wzmocniony",
    "precyzyjny",
    "lekki",
    "ciezki",
    "podrozny",
    "trwaly",
    "rzemieslniczy",
    "staranny",
  ];
  return {
    wealthKeywordMap,
    reputationKeywordMap,
    blockedSuggestionNameTokens,
    blockedSuggestionNamePhrases,
    bannedSuggestionSuffixPatterns,
    personalizedVariantQualifiers,
  };
};
