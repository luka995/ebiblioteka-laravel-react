# eBiblioteka — moderni školsko-bibliotečki sistem

> Novi projekat eBiblioteka — rewrite zastarele Symfony 2 aplikacije u modernu,
> odvojenu arhitekturu **Laravel API + React web**, uz zadržavanje postojeće
> poslovne logike i uvođenje novih funkcionalnosti (Razred / Class / Učenik,
> analitika za direktore, skeniranje ISBN/bar-koda, PWA i Capacitor mobilno izdanje).

Detaljna specifikacija projekta (domeni, modeli, relacije, API, migracija podataka,
prihvatni kriterijumi, faze) nalazi se u [PRD_laravel_react_migracija.md](./PRD_laravel_react_migracija.md).
Ovaj README daje sažet pregled projekta i trenutno stanje implementacije.

## Pozadina

- **Legacy projekat**: Symfony 2 + Twig + FOSUserBundle + Doctrine, na putanji
  `/var/www/ebiblioteka`. Koristi se isključivo kao referenca za poslovna pravila,
  audit, migraciju podataka i funkcionalnu verifikaciju — ne kopira se u novi kod.
- **Novi projekat**: `/var/www/ebiblioteka-new` (ovaj repozitorijum).
- Legacy termin `BookCopy`/kopija knjige u novoj aplikaciji i dokumentaciji zamenjen
  je terminom **Fizičke jedinice**.

## Ciljna arhitektura

```text
 React (dashboard + javni katalog)          Laravel API
 +----------------------------------+        +------------------------------------+
 | shadcn/ui + Tailwind CSS         |  JSON  | Controllers (tanki)                |
 | React Hook Form + Zod            | <----> | Services (domenska logika)         |
 | TanStack Query                   |  REST  | Policies/Gate (autorizacija)       |
 | Lucide React + i18n (sr/en)      |        | Form Requests + API Resources      |
 +----------------------------------+        | Eloquent + Query objekti          |
                                             +------------------------------------+
                                                      |
                                              PostgreSQL 16 (greenfield)
```

- Backend: **Laravel** (PHP 8.3) — verzionisani JSON REST API (`/api/v1`).
- Baza: **PostgreSQL 16**, greenfield sema. Legacy baza služi samo za ETL migraciju.
- Autentikacija: **Laravel Sanctum, session-based (cookie)**, HTTP-only kolačići;
  bez JWT. Detalji u §3.6 PRD-a.
- Frontend: **React + Vite + TypeScript** — potpuno odvojen SPA koji komunicira sa
  API-jem isključivo preko JSON REST-a.

## Tehnologije

### Backend
- Laravel + Sanctum, PHP 8.3
- PostgreSQL 16
- Policy autorizacija + Form Request validacija + API Resources
- Service sloj za domensku logiku, Query objekti za kompleksne upite
- Pest (feature/unit testovi)

### Frontend
- React + Vite + TypeScript
- React Router, TanStack Query, React Hook Form, Zod
- shadcn/ui (Radix UI + Tailwind CSS 4) kao jedini UI standard
- Lucide React ikonice, next-themes (light/dark), sonner (toast)
- i18n: sr-Cyrl (podrazumevano), sr-Latn, en

### Infrastruktura
- Docker Compose stack: PHP-FPM, Nginx, PostgreSQL, Vite dev server
- PWA izdanje + Capacitor wrapper (Android/iOS) iz istog React koda
- Cloudflare Tunnel za deljenje javnih adresa (vidi `TUNNEL.md`)

## Role model (bez Spatie)

Jedan korisnik ima tačno jednu rolu (Postgres `user_role` enum):

| Role | Nivo |
|---|---|
| `superadmin` | Globalni admin sistema |
| `library_admin` | Admin biblioteke |
| `librarian` | Bibliotekar |
| `user` | Član (učenik) |

Autorizacija u kodu ide kroz **Policy** klase + `AuthorizationService`, a frontend
dobija globalne/per-resource dozvole kroz `/api/v1/auth/me` (sidebar se gradi po
dozvolama, ne po hardkodovanoj ulozi).

## Domeni i moduli

- Auth & Users (role, članstva u bibliotekama, tagovi)
- Libraries & Settings (region → mesto → biblioteka)
- Catalog (knjige, autori, kategorije, tagovi) — u izradi
- Fizičke jedinice & Inventar — u izradi
- Pozajmice / rezervacije / članarine — u izradi
- Statistika, izveštaji i analitika za direktore — u izradi
- Razred / Class / Učenik + školske godine — u izradi
- CMS (vesti, stranice, baneri) — u izradi
- Import / Export (Excel, PDF 1:1 sa legacy-jem) — u izradi

## Autentikacija (session/cookie)

React i Laravel API su na različitim domenima (npr. `dashboard.ebiblioteka.rs` →
`demo.ebiblioteka.rs`). Session/XSRF kolačići dele se preko zajedničkog
`.ebiblioteka.rs` domena (middleware `SetSessionCookieDomain`, `SESSION_COOKIE_DOMAIN_PUBLIC`).
Na localhost-u ostaju host-only kolačići.

Auth endpointi:

- `GET  /api/v1/auth/me` — trenutni korisnik + dozvole + biblioteke + aktivna biblioteka
- `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`
- `POST /api/v1/auth/password/...` — zaboravljena lozinka / reset
- `PUT  /api/v1/auth/active-library` — kontekst aktivne biblioteke

## Cloudflare Turnstile (kontakt forma)

Javna kontakt forma (`POST /kontakt`) je zaštićena **Cloudflare Turnstile** (Managed mode)
pored standardne Laravel validacije i rate limiting-a (`5/min` po IP adresi).

- `TURNSTILE_SITE_KEY` — koristi frontend (Blade kontakt forma); javna vrednost.
- `TURNSTILE_SECRET` — koristi **isključivo** Laravel backend (`TurnstileService`); nikada se ne izlaže frontend-u niti commit-uje.
- `TURNSTILE_HOSTNAMES` — allowlist frontend hostname-a koji siteverify prihvata (npr. `demo.ebiblioteka.rs`); u produkciji nikada ne navoditi `localhost`/`127.0.0.1`.

Vrednosti se podešavaju u Cloudflare dashboardu (Turnstile → Add widget), a zatim u `.env`:
`TURNSTILE_SITE_KEY` i `TURNSTILE_SECRET`. Backend uvek verifikuje token server-side
(fail-closed): bez uspešne verifikacije poruka se ne prihvata. Siteverify dodatno
proverava `action` (`contact`) i `hostname` prema `TURNSTILE_HOSTNAMES`.

## Referencijalni integritet (brisanje naloga)

Hard brisanje naloga (`users`) na nivou baze štite FK `ON DELETE RESTRICT` na
svim budućim poslovnim/istorijskim relacijama koje referenciraju nalog
(pozajmice, rezervacije, članarine, istorija izdavanja i sl.): nalog sa
zabeleženim zapisima ne može se obrisati — API vraća 422 `cannot_force_delete`.
Pivot tabele trenutnog stanja (`library_user`, `tag_user`) ostaju `ON DELETE
CASCADE`. Soft brisanje naloga (deaktivacija) relacijama nije blokirano.

Pravilo pri kreiranju migracija budućih modula:

```php
$table->foreignId('user_id')->constrained()->restrictOnDelete();
```

## Status implementacije

> Bez naznake "u izradi" stavke su implementirane i pokrivene testovima.

- [x] Auth: login, logout, /me, forgot/reset lozinke, verifikacija email-a
- [x] Javni portal (ćirilica): početna, o projektu, kontakt, pregled/katalog biblioteka
- [x] Dashboard shell: role i permisije, sidebar po dozvolama, izbor jezika, light/dark tema
- [x] Users: CRUD, soft delete / force delete, promena lozinke, kolonski filteri, kolonska pretraga
- [x] Biblioteke: CRUD, deaktivacija/restore, aktivna biblioteka i preklopnik
- [x] Članstva korisnik ↔ biblioteka (deaktivacija / aktivacija / uklanjanje, soft-delete pivot)
- [x] Regioni / Mesta: CRUD sa zaštitom brisanja
- [x] Tagovi: CRUD + dodela tagova korisnicima (po korisniku i bulk)
- [x] Profil: prikaz/izmena, promena lozinke; podešavanja naloga
- [x] Catalog (knjige, autori, kategorije) — naslovi sa ISBN unosom
- [x] Fizičke jedinice i inventar — inv. brojevi, barkodovi, otpis i arhiva
- [ ] Pozajmice / rezervacije / članarine
- [ ] Razred / Class / Učenik + školske godine
- [ ] Statistika, izveštaji, analitika za direktore
- [ ] Import / Export (Excel, PDF 1:1 sa legacy-jem) — u izradi
- [ ] Revizija inventara i skeniranje ISBN/bar-koda u PWA
- [ ] PWA + Capacitor mobilno izdanje
- [ ] Migracija podataka (ETL iz legacy baze)

## Pokretanje (Docker)

```bash
cp .env.example .env      # podesi DB_* i domene
docker compose up -d --build
```

| Servis | Adresa |
|---|---|
| Laravel sajt / API | http://localhost:81 |
| React (Vite) | http://localhost:3001 |
| Javne adrese | https://demo.ebiblioteka.rs / https://dashboard.ebiblioteka.rs (tunel) |

Rad sa artisanom/composerom/npm unutar kontejnera — vidi `docker-compose.yml`
(kontejneri `ebiblioteka-php`, `ebiblioteka-frontend`, `ebiblioteka-postgres`).

## Testovi i kod

```bash
composer test            # Pest feature/unit testovi (config:clear + test)
vendor/bin/pint          # PHP kodni stil (config: pint.json)
cd frontend && npm run build   # TypeScript check + Vite build
```

## Struktura repozitorijuma

```text
app/                  Laravel backend (Controllers, Services, Policies, Resources, Queries)
database/             migracije i factory
frontend/src/         React aplikacija (pages, components/ui, hooks, i18n, lib)
lang/                 server-side prevodi (sr-Cyrl, sr-Latn, en)
resources/views/      javni portal (Blade) — target: React javni katalog
tests/                Pest testovi
routes/               api.php, auth.php, web.php
PRD_laravel_react_migracija.md   kompletna specifikacija projekta
TUNNEL.md             Cloudflare Tunnel podešavanje
CHANGELOG.md          dnevnik promena
```

## Srodna dokumentacija

- `PRD_laravel_react_migracija.md` — detaljna specifikacija (domeni, baza, API, role, faze, kriterijumi)
- `TUNNEL.md` — Cloudflare Tunnel (javne adrese za kolege)
- `CHANGELOG.md` — istorija promena po danima
