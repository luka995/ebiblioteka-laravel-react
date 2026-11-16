import './bootstrap'

const menuButton = document.querySelector<HTMLButtonElement>('[data-menu-toggle]')
const mobileMenu = document.querySelector<HTMLElement>('[data-mobile-menu]')

menuButton?.addEventListener('click', () => {
  const isOpen = mobileMenu?.classList.toggle('is-open') ?? false
  menuButton.setAttribute('aria-expanded', String(isOpen))
})

document.querySelectorAll<HTMLAnchorElement>('[data-mobile-menu] a').forEach((link) => {
  link.addEventListener('click', () => {
    mobileMenu?.classList.remove('is-open')
    menuButton?.setAttribute('aria-expanded', 'false')
  })
})

document.querySelectorAll<HTMLDialogElement>('[data-flash-modal]').forEach((modal) => {
  modal.showModal()

  modal.querySelector('[data-flash-modal-close]')?.addEventListener('click', () => {
    modal.close()
  })

  modal.addEventListener('click', (event) => {
    if (event.target === modal) {
      modal.close()
    }
  })
})

document.querySelectorAll<HTMLElement>('[data-demo-widget]').forEach((widget) => {
  const tabs = Array.from(widget.querySelectorAll<HTMLButtonElement>('[data-demo-tab]'))
  const panels = Array.from(widget.querySelectorAll<HTMLElement>('[data-demo-panel]'))

  if (tabs.length === 0) {
    return
  }

  const activate = (key: string) => {
    tabs.forEach((tab) => {
      const selected = tab.dataset.demoTab === key
      tab.setAttribute('aria-selected', String(selected))
      tab.tabIndex = selected ? 0 : -1
    })

    panels.forEach((panel) => {
      panel.hidden = panel.dataset.demoPanel !== key
    })
  }

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => activate(tab.dataset.demoTab ?? ''))

    tab.addEventListener('keydown', (event) => {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
        return
      }

      event.preventDefault()

      let nextIndex = index

      if (event.key === 'ArrowRight') {
        nextIndex = (index + 1) % tabs.length
      } else if (event.key === 'ArrowLeft') {
        nextIndex = (index - 1 + tabs.length) % tabs.length
      } else if (event.key === 'Home') {
        nextIndex = 0
      } else if (event.key === 'End') {
        nextIndex = tabs.length - 1
      }

      const nextTab = tabs[nextIndex]
      activate(nextTab.dataset.demoTab ?? '')
      nextTab.focus()
    })
  })

  activate(tabs[0].dataset.demoTab ?? '')
})

const newsLightbox = document.querySelector<HTMLDialogElement>('[data-news-lightbox]')

if (newsLightbox) {
  document.querySelectorAll<HTMLButtonElement>('[data-news-lightbox-trigger]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
      newsLightbox.showModal()
    })
  })

  newsLightbox.querySelector('[data-news-lightbox-close]')?.addEventListener('click', () => {
    newsLightbox.close()
  })

  newsLightbox.addEventListener('click', (event) => {
    if (event.target === newsLightbox) {
      newsLightbox.close()
    }
  })
}
