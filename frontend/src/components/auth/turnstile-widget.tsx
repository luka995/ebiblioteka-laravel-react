import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react'

export interface TurnstileWidgetHandle {
  reset: () => void
}

interface TurnstileWidgetProps {
  onVerify: (token: string) => void
  onExpire: () => void
  onError: () => void
  className?: string
}

let loaderPromise: Promise<TurnstileApi> | null = null

function loadTurnstile(): Promise<TurnstileApi> {
  if (typeof window === 'undefined') {
    return Promise.reject(new Error('Turnstile is not available on the server.'))
  }

  if (window.turnstile) {
    return Promise.resolve(window.turnstile)
  }

  if (loaderPromise) {
    return loaderPromise
  }

  const promise = new Promise<TurnstileApi>((resolve, reject) => {
    const resolveApi = () => {
      if (window.turnstile) {
        resolve(window.turnstile)
      } else {
        reject(new Error('Turnstile API did not load.'))
      }
    }

    const existing = document.querySelector<HTMLScriptElement>('script[data-turnstile-loader]')

    if (existing) {
      existing.addEventListener('load', resolveApi, { once: true })
      existing.addEventListener('error', () => reject(new Error('Turnstile script failed.')), { once: true })
      return
    }

    const script = document.createElement('script')
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
    script.async = true
    script.defer = true
    script.dataset.turnstileLoader = 'true'
    script.addEventListener('load', resolveApi, { once: true })
    script.addEventListener('error', () => reject(new Error('Turnstile script failed.')), { once: true })
    document.head.appendChild(script)
  })

  loaderPromise = promise.catch((error) => {
    loaderPromise = null
    throw error
  })

  return loaderPromise
}

export const TurnstileWidget = forwardRef<TurnstileWidgetHandle, TurnstileWidgetProps>(
  function TurnstileWidget({ onVerify, onExpire, onError, className }, ref) {
    const containerRef = useRef<HTMLDivElement>(null)
    const widgetId = useRef<string | null>(null)
    const callbacks = useRef({ onVerify, onExpire, onError })

    callbacks.current = { onVerify, onExpire, onError }

    useEffect(() => {
      const siteKey = import.meta.env.VITE_TURNSTILE_SITE_KEY as string | undefined

      if (!siteKey) {
        callbacks.current.onError()
        return
      }

      let cancelled = false

      loadTurnstile()
        .then((api) => {
          if (cancelled || !containerRef.current) {
            return
          }

          widgetId.current = api.render(containerRef.current, {
            sitekey: siteKey,
            action: 'login',
            theme: 'auto',
            callback: (token) => callbacks.current.onVerify(token),
            'expired-callback': () => callbacks.current.onExpire(),
            'error-callback': () => callbacks.current.onError(),
          })
        })
        .catch(() => {
          if (!cancelled) {
            callbacks.current.onError()
          }
        })

      return () => {
        cancelled = true

        if (widgetId.current && window.turnstile) {
          window.turnstile.remove(widgetId.current)
          widgetId.current = null
        }
      }
    }, [])

    useImperativeHandle(
      ref,
      () => ({
        reset: () => {
          if (widgetId.current && window.turnstile) {
            window.turnstile.reset(widgetId.current)
          }
        },
      }),
      [],
    )

    return <div ref={containerRef} className={className} />
  },
)
