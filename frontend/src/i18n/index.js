import { createI18n } from "vue-i18n";
import pl from "./locales/pl.json";

const DEFAULT_LOCALE = "pl";
const LOCALE_STORAGE_KEY = "blatyrpg-locale";
const SUPPORTED_LOCALES = ["pl", "en"];

const i18n = createI18n({
  legacy: true,
  locale: DEFAULT_LOCALE,
  fallbackLocale: DEFAULT_LOCALE,
  messages: { pl },
  globalInjection: true,
});

const loadedLocales = new Set(["pl"]);

function getStoredLocale() {
  if (typeof localStorage === "undefined") {
    return null;
  }
  const stored = localStorage.getItem(LOCALE_STORAGE_KEY);
  return SUPPORTED_LOCALES.includes(stored) ? stored : null;
}

function getBrowserLocale() {
  if (typeof navigator === "undefined" || !navigator.language) {
    return null;
  }
  const locale = navigator.language.split("-")[0];
  return SUPPORTED_LOCALES.includes(locale) ? locale : null;
}

export async function setLocale(locale) {
  const target = SUPPORTED_LOCALES.includes(locale) ? locale : DEFAULT_LOCALE;

  if (!loadedLocales.has(target)) {
    const messages = await import(
      /* webpackChunkName: "locale-[request]" */ `./locales/${target}.json`
    );
    i18n.global.setLocaleMessage(target, messages.default || messages);
    loadedLocales.add(target);
  }

  if (typeof i18n.global.locale === "string") {
    i18n.global.locale = target;
  } else {
    i18n.global.locale.value = target;
  }

  if (typeof document !== "undefined") {
    document.documentElement.setAttribute("lang", target);
  }

  if (typeof localStorage !== "undefined") {
    localStorage.setItem(LOCALE_STORAGE_KEY, target);
  }
}

export async function initI18n() {
  const locale = getStoredLocale() || getBrowserLocale() || DEFAULT_LOCALE;
  await setLocale(locale);
}

export const availableLocales = [
  { code: "pl", label: "PL" },
  { code: "en", label: "EN" },
];

export default i18n;
