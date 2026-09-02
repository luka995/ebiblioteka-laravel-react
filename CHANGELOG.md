# Changelog

## [02.09.2026] Contact: Javna kontakt forma sa email notifikacijom

### Added

- **Kontakt strana** (`resources/views/public/contact.blade.php`) — javna kontakt forma sa hero sekcijom, info karticom Akademije Filipović i formom (ime, email, organizacija, poruka)
- **Email poruka** (`app/Mail/ContactMessage.php`, `resources/views/emails/contact-message.blade.php`) — mailable sa subject-om „Nova poruka sa eBiblioteka kontakt forme" i replyTo postavljenim na pošiljaoca
- **Kontakt konfiguracija** (`config/contact.php`) — primalac iz `CONTACT_EMAIL` env var
- **Testovi** (`tests/Feature/PublicContactTest.php`) — provera renderovanja stranice i slanja email poruke

### Changed

- **PublicContactController** (`app/Http/Controllers/PublicContactController.php`) — `create` prikazuje formu kroz `PublicTheme::view()`, `store` validira i šalje poruku na konfigurisanu adresu uz redirect sa `contact_sent` flash porukom

## [02.09.2026] UI: Tema 2 (reference) sa theme switcher-om i ćiriličnim sadržajem

### Added

- **Theme switcher** (`resources/views/public/partials/theme-switch.blade.php`) — lat/cyr toggle za prebacivanje između Teme 1 (classic) i Teme 2 (reference)
- **Reference tema** (`resources/css/reference.css`, `resources/views/themes/ref/`) — potpuno odvojen vizuelni sistem i Blade view-ovi Teme 2 (layout, nav, footer, home, project, libraries, contact) preuzeti sa prototipa
- **PublicTheme support** (`app/Support/PublicTheme.php`) — razrešavanje aktivne teme iz query parametra, cookija (`public_theme`) i konfiguracije
- **Cyrillic transliteracija** (`app/Support/Text.php`) — `Text::cyr()` za prevođenje latiničnih podataka u ćirilicu u referentnoj temi
- **LibraryCatalog support** (`app/Support/LibraryCatalog.php`) — izdvojen statički katalog biblioteka sa kategorijama i knjigama
- **Testovi Teme 2** (`tests/Feature/PublicThemeReferenceTest.php`) — provera ćiriličnog prikaza i linkova na home, catalog, library i book stranama sa `?theme=ref`

### Changed

- **PublicThemeMiddleware** (`app/Http/Middleware/PublicThemeMiddleware.php`) — persistor teme u cookie na osnovu `theme` query parametra; registrovan na web grupu u `bootstrap/app.php`
- **Javni kontroleri** (`PublicHomeController.php`, `PublicProjectController.php`, `PublicLibraryController.php`) — vraćaju view preko `PublicTheme::view()` i koriste `LibraryCatalog`
- **Nav/footer** (`resources/views/public/partials/nav.blade.php`, `footer.blade.php`, `home.blade.php`) — integrisan theme switcher, SVG logo, dinamički frontend linkovi i active stanje navigacije
- **Classic CSS** (`resources/css/app.css`) — stilovi theme switcher-a, vidljivost na mobilnom (≤620px), DM Sans umesto DM Mono
- **Vite ulaz** (`vite.config.js`) — dodat `resources/css/reference.css` u build ulaz

## [02.09.2026] Auth: Migracija auth ruta na /api/v1/auth sa Sanctum session autentikacijom

### Added

- **Frontend URL helper** (`app/Support/FrontendUrl.php`) — novi helper koji bira lokalni (`http://localhost:3001`) ili javni (`https://dashboard.ebiblioteka.rs`) frontend origin na osnovu request host-a
- **Current-user ruta** (`routes/auth.php`) — dodata `GET /api/v1/auth/me` koja vraća autentikovanog korisnika
- **Konfiguracija okruženja** (`.env.example`) — dodate `FRONTEND_URL`, `FRONTEND_URL_PUBLIC` i `SANCTUM_STATEFUL_DOMAINS` (localhost, 127.0.0.1, react-frontend)

### Changed

- **Auth API migracija** (`routes/auth.php`, `routes/api.php`, `routes/web.php`) — sve auth rute (register, login, logout, forgot-password, reset-password, email-verification, verify-email) premeštene iz web grupe u `routes/api.php` pod `v1/auth` prefiksom; `require routes/auth.php` uklonjen iz `routes/web.php`
- **Sanctum middleware** (`bootstrap/app.php`) — omogućen `EnsureFrontendRequestsAreStateful` na api grupi i `trustProxies` za proxy podešavanje
- **CORS** (`config/cors.php`) — dozvoljeni frontend origini iz `FRONTEND_URL` i `FRONTEND_URL_PUBLIC`
- **Email verifikacija** (`app/Http/Controllers/Auth/VerifyEmailController.php`) — redirect posle verifikacije koristi `FrontendUrl::url()` umesto fiksnog konfiga
- **Auth testovi** (`tests/Feature/Auth/`) — ažurirani na nove `/api/v1/auth` rute sa `Origin`/`Accept` headerima (Sanctum stateful zahtev)

### Added

- **PRD dokumentacija** (`PRD_laravel_react_migracija.md`) — dodata sekcija 3.6 „Autentikacija: Laravel Sanctum session-based (cookie auth)" sa flow-om, React i Laravel zahtevima i CORS smernicama

## [31.08.2026] Initial Laravel + React project setup with Docker

### Added

- **Project documentation** (`PRD_laravel_react_migracija.md`, `PRD_laravel_react_migracija.html`) — PRD for Laravel + React migration
- **Docker infrastructure** (`docker-compose.yml`, `php/Dockerfile`, `nginx/Dockerfile`, `nginx/default.conf`, `frontend/Dockerfile`, `frontend/.dockerignore`) — complete Docker Compose stack for PHP-FPM, Nginx, PostgreSQL, Redis
- **Laravel application skeleton** (`config/app.php`, `routes/web.php`, `app/Http/Controllers/`) — PublicHomeController, PublicLibraryController, PublicProjectController
- **Frontend React application** (`frontend/`, `vite.config.js`, `resources/js/app.ts`, `resources/js/bootstrap.ts`) — Vite + React + TypeScript setup with DM Sans font
- **Public views** (`resources/views/`) — home, libraries (index, show, book, category), project, layouts, partials (nav, footer)
- **Styling** (`resources/css/app.css`, `frontend/src/styles.css`) — global styles with DM Sans typography, removed DM Mono
- **Database structure** (`ebiblioteka_bib_structure_dump.sql`) — initial database schema dump
- **Testing** (`tests/Feature/`) — PublicHomeTest, PublicProjectTest
- **Postman collections** (`postman/`) — API environment and collection for testing
- **Frontend build assets** (`frontend/dist/`, `public/favicon.svg`) — compiled Vite assets
- **Dependencies** (`package.json`, `package-lock.json`, `frontend/package.json`, `frontend/package-lock.json`) — npm and Composer dependencies

### Changed

- **Config** (`config/app.php`) — updated application configuration
- **Routes** (`routes/web.php`) — added public routes for home, libraries, projects

### Removed

- **DM Mono font** — replaced with DM Sans across all stylesheets
