const messages = {
  pl: {
    title: "Czat kampanii",
    collapse: "Zwiń czat",
    expand: "Otwórz czat",
    live: "Połączono na żywo",
    syncing: "Synchronizacja…",
    reconnecting: "Ponowne łączenie…",
    offline: "Czat offline",
    refresh: "Odśwież",
    older: "Wczytaj starsze wiadomości",
    loading: "Wczytywanie wiadomości…",
    empty: "Nie ma jeszcze wiadomości. Rozpocznij rozmowę.",
    placeholder: "Napisz wiadomość…",
    send: "Wyślij",
    sending: "Wysyłanie…",
    readOnly: "Masz dostęp tylko do odczytu.",
    counter: "{count}/{max}",
    errors: {
      network: "Nie udało się połączyć z czatem.",
      offline:
        "Czat jest chwilowo offline. Wiadomość można ponowić po połączeniu.",
      forbidden: "Nie masz dostępu do czatu tej kampanii.",
      rate_limited: "Wysyłasz zbyt szybko. Poczekaj chwilę.",
      validation_failed: "Wiadomość jest pusta lub zbyt długa.",
      generic: "Nie udało się obsłużyć wiadomości.",
    },
  },
  en: {
    title: "Campaign chat",
    collapse: "Collapse chat",
    expand: "Open chat",
    live: "Live connection",
    syncing: "Synchronizing…",
    reconnecting: "Reconnecting…",
    offline: "Chat offline",
    refresh: "Refresh",
    older: "Load older messages",
    loading: "Loading messages…",
    empty: "No messages yet. Start the conversation.",
    placeholder: "Write a message…",
    send: "Send",
    sending: "Sending…",
    readOnly: "You have read-only access.",
    counter: "{count}/{max}",
    errors: {
      network: "Could not connect to campaign chat.",
      offline: "Chat is temporarily offline. Retry after reconnecting.",
      forbidden: "You do not have access to this campaign chat.",
      rate_limited: "You are sending too quickly. Wait a moment.",
      validation_failed: "The message is empty or too long.",
      generic: "The chat operation failed.",
    },
  },
};

const pathValue = (source, path) =>
  String(path)
    .split(".")
    .reduce((value, part) => value?.[part], source);

export const campaignChatText = (locale, key, variables = {}) => {
  const language = String(locale || "pl")
    .toLowerCase()
    .startsWith("en")
    ? "en"
    : "pl";
  const template = pathValue(messages[language], key) || key;
  return Object.entries(variables).reduce(
    (text, [name, value]) => text.replaceAll(`{${name}}`, String(value)),
    String(template),
  );
};
