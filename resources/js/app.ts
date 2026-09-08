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

const contactModal = document.querySelector<HTMLDialogElement>('[data-contact-modal]')

if (contactModal) {
  contactModal.showModal()

  contactModal.querySelector('[data-contact-modal-close]')?.addEventListener('click', () => {
    contactModal.close()
  })

  contactModal.addEventListener('click', (event) => {
    if (event.target === contactModal) {
      contactModal.close()
    }
  })
}
