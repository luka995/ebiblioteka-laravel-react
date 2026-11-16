/// <reference types="vite/client" />

interface TurnstileRenderOptions {
  sitekey: string
  action?: string
  theme?: 'auto' | 'light' | 'dark'
  language?: string
  size?: 'normal' | 'compact' | 'flexible'
  callback?: (token: string) => void
  'expired-callback'?: () => void
  'error-callback'?: () => void
}

interface TurnstileApi {
  render: (container: HTMLElement, options: TurnstileRenderOptions) => string
  reset: (widgetId?: string) => void
  remove: (widgetId?: string) => void
}

interface Window {
  turnstile?: TurnstileApi
}

interface ImportMetaEnv {
  readonly VITE_TURNSTILE_SITE_KEY?: string
}
