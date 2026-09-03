import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import srCyrl from './locales/sr-Cyrl.json'
import srLatn from './locales/sr-Latn.json'
import en from './locales/en.json'

export const LOCALE_KEY = 'ebib.locale'
export const DEFAULT_LOCALE = 'sr-Cyrl'
export const SUPPORTED_LOCALES = ['sr-Cyrl', 'sr-Latn', 'en'] as const

export type AppLocale = (typeof SUPPORTED_LOCALES)[number]

function isSupported(value: string | null): value is AppLocale {
  return !!value && (SUPPORTED_LOCALES as readonly string[]).includes(value)
}

function initialLocale(): AppLocale {
  try {
    const stored = localStorage.getItem(LOCALE_KEY)
    if (isSupported(stored)) return stored
  } catch {
    // ignore
  }
  return DEFAULT_LOCALE
}

void i18n.use(initReactI18next).init({
  resources: {
    'sr-Cyrl': { translation: srCyrl },
    'sr-Latn': { translation: srLatn },
    en: { translation: en },
  },
  lng: initialLocale(),
  fallbackLng: DEFAULT_LOCALE,
  interpolation: { escapeValue: false },
  returnNull: false,
})

export function currentLocale(): string {
  return i18n.resolvedLanguage ?? DEFAULT_LOCALE
}

export function setLocale(locale: AppLocale): void {
  try {
    localStorage.setItem(LOCALE_KEY, locale)
  } catch {
    // ignore
  }
  document.documentElement.lang = locale
  void i18n.changeLanguage(locale)
}

export default i18n
