# Changelog

## [10.09.2026] Auth: prikaz/skrivanje lozinke na prijavi

### Changed

- **Login stranica** (`frontend/src/pages/auth/login-page.tsx`) — dodato dugme sa `Eye`/`EyeOff` ikonom unutar password polja koje prebacuje `type` između `password` i `text` (`showPassword` stanje); input dobija `pr-10` da ne preklapa ikonu
- **i18n** (`frontend/src/i18n/locales/{en,sr-Cyrl,sr-Latn}.json`) — dodati ključevi `auth.showPassword` i `auth.hidePassword` sa prevodima na tri jezika (uključujući `aria-label`)

## [09.09.2026] News modul: vesti sa rich-text editorom i javnim stranicama

### Added

- **News model i migracija** (`app/Models/News.php`, `database/migrations/2026_09_09_000001_create_news_table.php`) — `title`, `slug`, `body`, `date`, `image`; `image_url` pristupnik vraća root-relative URL sličice iz storage diska
- **News CRUD API** (`app/Http/Controllers/NewsController.php`, `app/Http/Requests/StoreNewsRequest.php`, `app/Http/Requests/UpdateNewsRequest.php`, `app/Http/Resources/NewsResource.php`) — `index/show/store/update/destroy` plus `POST /news/upload-image` (upload inline slika iz rich-text editora u `news` direktorijum)
- **NewsPolicy** (`app/Policies/NewsPolicy.php`, `app/Services/AuthorizationService.php`, `routes/api.php`) — autorizacija po `viewAny/create/view/update/delete`; `news` dodato u globalne permisije
- **NewsFactory i NewsSeeder** (`database/factories/NewsFactory.php`, `database/seeders/NewsSeeder.php`, `database/seeders/DatabaseSeeder.php`) — tri uvodne vesti (idempotentno po `slug`-u)
- **Javne stranice vesti** (`app/Http/Controllers/PublicNewsController.php`, `resources/views/public/news/index.blade.php`, `resources/views/public/news/show.blade.php`, `routes/web.php`) — listing `/novosti` i detalj `/novosti/{news:slug}`; detalj ima naslovnu sliku sa lightbox dijalogom (`<dialog>`) i „Још новости" sekciju
- **Admin frontend za vesti** (`frontend/src/pages/news/news-page.tsx`, `frontend/src/components/news/news-form-modal.tsx`, `frontend/src/components/ui/rich-text-editor.tsx`, `frontend/src/lib/api.ts`, `frontend/src/types.ts`) — tabela vesti, forma za kreiranje/izmenu i rich-text editor (TipTap + `@tiptap/extension-image`) sa upload-om slika kroz toolbar, paste i drag&drop
- **i18n** (`frontend/src/i18n/locales/{en,sr-Cyrl,sr-Latn}.json`) — prevodi za news modul na tri jezika
- **Testovi** (`tests/Feature/Auth/NewsCrudApiTest.php`, `tests/Feature/PublicNewsTest.php`) — CRUD autorizacija i javni listing/detalj

### Changed

- **Navigacija** (`resources/views/public/partials/nav.blade.php`, `resources/views/public/home.blade.php`) — link „Новости" i „Све новости ↗" vode na `news.index`; nav izdvojen u partial (home koristi `@include`), anchor vs route po `request()->routeIs('home')`
- **Javni layout** (`resources/views/public/{contact,libraries,project}.blade.php`, `resources/views/public/partials/footer.blade.php`) — usaglašeno korišćenje nav partial-a i dodati linkovi ka novostima
- **Stilovi vesti** (`resources/css/app.css`, `resources/js/app.ts`) — `news-detail-hero`/`news-detail-cover`/`.news-lightbox` stilovi, lightbox JS logika (otvaranje/zatvaranje dijaloga)
- **PRD** (`PRD_laravel_react_migracija.md`) — dokumentacija news modula

## [08.09.2026] Security: Cloudflare Turnstile zaštita kontakt forme

### Added

- **TurnstileService** (`app/Services/Security/TurnstileService.php`) — verifikacija tokena prema Cloudflare Turnstile `siteverify` endpointu (Managed mode); proverava `success`, `action` i dozvoljene `hostname`-e; HTTP greške i izuzeci se loguju i vraćaju `false` (fail-closed)
- **Turnstile widget na kontakt formi** (`resources/views/public/contact.blade.php`, `resources/views/layouts/public.blade.php`) — eksplicitno renderovanje (`render=explicit`), token se upisuje u skriveno polje `turnstile_token`; dugme „Пошаљите поруку" onemogućeno dok widget ne vrati token; skripta se učitava samo ako postoji `TURNSTILE_SITE_KEY`
- **Rate limiting** (`app/Providers/AppServiceProvider.php`, `routes/web.php`) — `throttle:contact` (5/min po IP) na `POST /kontakt`
- **Konfiguracija** (`config/services.php`, `.env.example`) — `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET`, `TURNSTILE_HOSTNAMES`, `services.turnstile.action/hostnames`
- **Testovi** (`tests/Unit/TurnstileServiceTest.php`, `tests/Feature/PublicContactTest.php`) — uspešna/odbijena verifikacija, provera action/hostname, HTTP greške i izuzeci; feature testovi sa mockovanim servisom

### Changed

- **PublicContactController** (`app/Http/Controllers/PublicContactController.php`) — verifikuje `turnstile_token` pre slanja; slanje kroz `mail.transactional_mailer` sa lokalizacijom (`locale(app()->getLocale())`); greške slanja se loguju i vraćaju kroz `contact_error` flash umesto izuzetka
- **Kontakt forma** (`resources/views/public/contact.blade.php`, `resources/css/app.css`, `resources/js/app.ts`) — poruke uspeha/greške prikazane u `<dialog>` modalu (`contact_sent`/`contact_error`) umesto inline poruke; stilovi modalnog prozora i `contact-turnstile`
- **Konfiguracija kontakta** (`config/contact.php`) — `recipient` podržava više primalaca (zarezom razdvojena lista iz `CONTACT_EMAIL`)

## [08.09.2026] Mail: Transakcioni mejlovi (nalog kreiran, reset lozinke) i queue worker

### Added

- **TransactionalMailService** (`app/Services/Mail/TransactionalMailService.php`) — slanje kroz `mail.transactional_mailer` uz `locale(app()->getLocale())`
- **UserMailService** (`app/Services/Mail/UserMailService.php`) — `sendPasswordReset()` i `sendAccountCreated()`; gradi reset/login URL preko `FrontendUrl`
- **AccountCreated mail** (`app/Mail/AccountCreated.php`, `resources/views/emails/account-created.blade.php`, `account-created-text.blade.php`) — šalje kredencijale (email + plaintext lozinka) i link za prijavu nakon kreiranja naloga
- **ResetPassword mail** (`app/Mail/ResetPassword.php`, `resources/views/emails/reset-password.blade.php`, `reset-password-text.blade.php`) — prilagođen reset mejl (umesto default Laravel notifikacije) sa expiration informacijom
- **Email layout** (`resources/views/emails/layout.blade.php`) — zajednički HTML šablon sa brand zaglavljem (embeded logo `public/img/ebiblioteka-logo.png`) i footerom
- **Lokalizacija mejlova** (`lang/{en,sr-Cyrl,sr-Latn}/emails.php`) — subjecti, pozdravi, labele i CTA tekstovi na tri jezika
- **Queue worker servis** (`docker-compose.yml`) — `ebiblioteka-queue` kontejner (`queue:work database --queue=emails,default`) za asinhrono slanje
- **Testovi** (`tests/Feature/Auth/AccountCreatedMailTest.php`, `tests/Feature/Auth/PasswordResetTest.php`) — kreiranje naloga šalje mejl sa kredencijalima; reset lozinke koristi prilagođeni `ResetPassword` mail

### Changed

- **User model** (`app/Models/User.php`) — prepisana `sendPasswordResetNotification()` da koristi `UserMailService`
- **UsersController::store** (`app/Http/Controllers/UsersController.php`) — čuva plaintext lozinku pre heširanja i šalje `AccountCreated` mejl
- **AppServiceProvider** (`app/Providers/AppServiceProvider.php`) — uklonjen `ResetPassword::createUrlUsing` (premešten u `UserMailService`)
- **ContactMessage mail** (`app/Mail/ContactMessage.php`, `resources/views/emails/contact-message.blade.php`, `contact-message-text.blade.php`) — postaje `ShouldQueue`, lokalizovan subject/sadržaj, koristi zajednički layout i tekstualnu verziju
- **config/mail.php** — dodati `transactional_mailer` (Postmark) i `marketing_mailer` (budući Mailgun), čitljivi iz `MAIL_TRANSACTIONAL_MAILER`/`MAIL_MARKETING_MAILER`
- **composer.json** — dodati `symfony/http-client` i `symfony/postmark-mailer`; `.env.example` i `phpunit.xml` ažurirani za nove mailere

## [08.09.2026] UI: Uklanjanje Teme 2 (reference tema)

### Removed

- **Reference tema** (`resources/views/themes/ref/`, `resources/css/reference.css`) — kompletan odvojeni vizuelni sistem Teme 2 (layout, nav, footer, home, project, libraries, contact)
- **PublicTheme** (`app/Support/PublicTheme.php`) i **PublicThemeMiddleware** (`app/Http/Middleware/PublicThemeMiddleware.php`) — mehanizam razrešavanja teme iz query parametra/cookija
- **Theme switcher** (`resources/views/public/partials/theme-switch.blade.php`) i njegovi stilovi u `resources/css/app.css`
- **public_theme konfiguracija** (`config/app.php`) i `PublicThemeReferenceTest` (`tests/Feature/PublicThemeReferenceTest.php`)

### Changed

- **Javni kontroleri** (`PublicHomeController`, `PublicProjectController`, `PublicLibraryController`, `PublicContactController`) — `PublicTheme::view()` zamenjen direktnim `view()`
- **bootstrap/app.php** — `PublicThemeMiddleware` uklonjen iz web middleware grupe
- **vite.config.js** — uklonjen `reference.css` iz build ulaza

## [08.09.2026] UI: Mobilne liste i brand (logo, header library switcher)

### Added

- **List primitiv** (`frontend/src/components/ui/list.tsx`) — `List`, `ListItem`, `ListItemHeader`, `ListItemTitle`, `ListItemMeta`, `ListItemField` za mobilne kartice
- **Mobilne liste** (`frontend/src/components/users/users-mobile-list.tsx`, `libraries/libraries-mobile-list.tsx`, `tags/tags-mobile-list.tsx`) — responsive prikaz redova kao kartica na malim ekranima (checkbox selekcija za korisnike, akcije po redu)
- **Logo** (`frontend/src/assets/ebiblioteka-logo.svg`, `public/img/ebiblioteka-logo.png`) — SVG/PNG brand logo
- **HeaderLibrarySwitcher** (`frontend/src/components/layout/library-switcher.tsx`) — Ajax pretraga/izbor aktivne biblioteke u header-u (samo superadmin); prazan izbor vraća na „sve biblioteke"

### Changed

- **Brand** (`frontend/src/components/brand.tsx`) — `BrandMark` koristi logo umesto `BookOpen` ikonice
- **App shell** (`frontend/src/components/layout/app-shell.tsx`) — `LocaleSwitcher` u header-u zamenjen `HeaderLibrarySwitcher`-om
- **LibrarySwitcher** (`frontend/src/components/layout/library-switcher.tsx`) — dashboard kartica „aktivna biblioteka" samo za ne-superadmina (superadmin vodi iz header-a)
- **useAuth** (`frontend/src/hooks/useAuth.tsx`) — promena aktivne biblioteke invalidira `users`/`tags`/`libraries` query cache
- **Stranice** (`users-page.tsx`, `libraries-page.tsx`, `tags-page.tsx`) — tabele sakrivene na malim ekranima u korist mobilnih listi; `tags-page` filter biblioteke preko `LibrarySelect` (superadmin) / selektovanih biblioteka (ostali)
- **user-detail-page** (`frontend/src/pages/users/user-detail-page.tsx`) — delete flow prebačen iz AlertDialoga u `UserMembershipModal` (mode `delete`)
- **Frontend build** (`frontend/dist/`) — regenerisani asseti

## [08.09.2026] Admin: bulk akcije nad izabranim korisnicima (članstvo i nalog)

### Added

- **Bulk membership rute** (`routes/api.php`, `app/Http/Controllers/UserMembershipsController.php`) — `POST /users/memberships/deactivate`, `POST /users/memberships/activate`, `DELETE /users/memberships`; rade nad aktivnom bibliotekom aktera (`ActiveLibraryService::resolve`), bez aktivne → 422 `active_library_required`
- **Bulk nalog rute** (`routes/api.php`, `app/Http/Controllers/UsersController.php`) — `POST /users/bulk/deactivate` (soft brisanje naloga) i `DELETE /users/bulk/force` (trajno brisanje); zaštita sopstvenog naloga (422), FK `RESTRICT` → rollback cele operacije + 422 `cannot_force_delete` (sve-ili-ništa)
- **Policy bulk abilities** (`app/Policies/UserPolicy.php`) — `bulkManageMemberships` (superadmin), `bulkRemoveMemberships` (superadmin + library_admin), `bulkDelete`, `bulkForceDelete` (superadmin)
- **Idempotentni toggle** — bulk deactivate preskače već deaktivirane, bulk activate preskače već aktivne; kada niko od izabranih nema odgovarajuće članstvo → 422 `memberships_none_eligible` (nova poruka u `lang/sr-Latn|sr-Cyrl|en`)
- **Frontend bulk modal** (`frontend/.../user-memberships-bulk-modal.tsx`) — potvrdni dijalog za 5 akcija (deactivate/activate/remove članstvo + deaktivacija/brisanje naloga) sa nazivom izabrane akcije, brojem izabranih, aktivnom bibliotekom (read-only) i opisom akcije; role-gating dugmadi u toolbaru izbora (`users-page.tsx`)
- **Testovi** (`tests/Feature/Auth/LibraryMembershipTest.php`, `UserCrudApiTest.php`) — toggle skip ponašanje, 422 bez aktivne/bez kvalifikovanih, dozvole po ulogama, bulk remove/soft/force, sam-sele, FK rollback

### Changed

- **API klijent** (`frontend/src/lib/api.ts`) — dodati bulk endpointi (`usersMemberships*`, `usersBulkDeactivate`, `usersBulkForce`)
- **i18n** (`frontend/src/i18n/locales/*.json`) — ključevi za bulk nalog akcije, poruke brojanja i aktivne biblioteke
- **Frontend build** (`frontend/dist/`) — regenerisani asseti

## [08.09.2026] Admin: uklanjanje članstva za admina biblioteke i opisi akcija u modalu

### Added

- **UserPolicy::removeMemberships** (`app/Policies/UserPolicy.php`) — nova ability: trajno uklanjanje članstava dozvoljeno superadminu ili `library_admin`-u koji deli biblioteku sa ciljnim korisnikom; `delete`/`forceDelete`/`manageMemberships` ostaju superadmin-only
- **Ruta** (`routes/api.php`) — `DELETE /users/{user}/libraries` prebačena na `can:removeMemberships,user` (deactivate/activate ostaju na `manageMemberships`)
- **`can.removeMemberships` u API-ju** (`app/Http/Resources/UserResource.php`) — dodat u `can` mapu; vraćena polja `deactivated_libraries` i `tags` koja su u WIP izmeni bila uklonjena
- **DB konvencija za brisanje naloga** (`README.md`) — buduće poslovne/istorijske relacije ka `users` (pozajmice, rezervacije, članarine, izdavanja) koriste `ON DELETE RESTRICT`; pivot tabele stanja (`library_user`, `tag_user`) ostaju CASCADE; soft brisanje naloga nije blokirano
- **Prepoznavanje FK povrede** (`app/Http/Controllers/UsersController.php`) — `forceDestroy` razlikuje FK `RESTRICT`/constraint povredu (PostgreSQL `23503`, MySQL `23000`/`1451`, SQLite `19`/`787`) i vraća 422 `cannot_force_delete`
- **Opisi akcija u membership modalu** (`frontend/.../user-membership-modal.tsx`, i18n sr-Cyrl/Latn/en) — objašnjenja šta rade uklanjanje članstva, deaktivacija naloga i brisanje celog naloga; admin biblioteke u modalu vidi samo „Ukloni članstvo"
- **Bulk dodela/uklanjanje tagova po aktivnoj biblioteci** (`app/Http/Controllers/UserTagsController.php`) — bulk `assign`/`remove` više ne primaju `library_id`; biblioteka se rešava iz aktivne biblioteke aktera (`ActiveLibraryService::resolve`), bez aktivne → 422 `active_library_required`
- **„Biblioteka" filter u pretrazi korisnika samo za superadmina** (`app/Http/Controllers/UsersController.php`) — ne-superadminu se `library_id` ignoriše; superadmin sa `library_id` ne sužava dodatno na aktivnu biblioteku (filter ima prednost)
- **Vidljivost opisa akcija po roli** (`frontend/.../user-membership-modal.tsx`) — pun blok „Šta se dešava?" vidi samo superadmin; admin biblioteke vidi samo opis uklanjanja članstva
- **Testovi** (`tests/Feature/Auth/LibraryMembershipTest.php`, `UserCrudApiTest.php`, `UsersApiTest.php`, `UserTagAssignmentTest.php`) — admin može ukloniti članstvo iz svoje biblioteke (204), ne može iz tuđe (422), ne može deactivate/activate (403), ne može soft/force obrisati nalog (403); edit čuva članstva u bibliotekama kojima admin ne upravlja; DB-level FK blokada force delete-a (422); library_admin filter ignoriše `library_id`; superadmin filter ima prednost nad aktivnom bibliotekom; bulk assign/remove koristi aktivnu biblioteku i traži je (422)

### Changed

- **UserMembershipsController::destroy** — za ne-superadmina svaki `library_ids` mora biti u bibliotekama kojima upravlja; inače 422 `library_not_managed`
- **UsersController::update** — ne-superadmin pri izmeni korisnika spaja skup biblioteka sa postojećim članstvima u bibliotekama kojima ne upravlja (zatvoren „bypass" kojim se članstvo indirektno uklanjalo iz tuđe biblioteke)
- **Frontend liste korisnika** (`users-page.tsx`) — ikonica za brisanje/uklanjanje (UserX) vidljiva i kada postoji samo `removeMemberships` (library admin); `Power` toggle ostaje superadmin
- **Bulk tag modal** (`frontend/.../user-tags-bulk-modal.tsx`) — uklonjen Select biblioteke; koristi aktivnu biblioteku (read-only), bez aktivne → poruka i onemogućeno dugme; payload bez `library_id`
- **Filter panel korisnika** (`frontend/.../user-filters-panel.tsx`) — polje „Biblioteka" prikazano samo superadminu; za admina se `library_id` čisti pre primene filtera
- **Frontend build** (`frontend/dist/`) — regenerisani asseti

## [04.09.2026] README i .env.example: projektni pregled i uklanjanje konkretnih vrednosti iz šablona

### Changed

- **README.md** (`README.md`) — zamenjen default Laravel sadržaj projektnim pregledom po PRD-u: opis novog projekta (rewrite Symfony 2 → Laravel API + React), ciljna arhitektura, tehnologije, role model, domeni/moduli, autentikacija, status implementacije i roadmap; linkovi ka `PRD_laravel_react_migracija.md`, `TUNNEL.md` i `CHANGELOG.md`
- **.env.example** (`.env.example`) — produkcijske vrednosti (`FRONTEND_URL_PUBLIC`, `SESSION_COOKIE_DOMAIN_PUBLIC`, `CONTACT_EMAIL`) zamenjene generičkim placeholderima uz komentare; konkretni domeni i email prebačeni u lokalni `.env`
- **Config contact** (`config/contact.php`) — uklonjen hardkodovani fallback `akademijafilipovic@gmail.com`; `recipient` se čita isključivo iz `env('CONTACT_EMAIL')`


### Added

- **Policy autorizacija** (`app/Policies/UserPolicy.php`, `LibraryPolicy.php`, `RegionPolicy.php`, `PlacePolicy.php`, `TagPolicy.php`) — zamenjena ad-hoc `Gate` pravila (`manage-users`, `manage-libraries`) Laravel policy-ima; rute koriste `can:` abilities (`viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`, `manageMemberships`)
- **AuthorizationService** (`app/Services/AuthorizationService.php`) — jedinstveni sloj koji prevodi policy rezultate u API oblike: `resourceCan()` (per-resource `can`), `collectionPermissions()` i `globalPermissions()` (za `/me` i sidebar)
- **InteractsWithResourceAbilities** (`app/Http/Resources/Concerns/InteractsWithResourceAbilities.php`) — ugrađuje `can` mapu u resurse (User, Library, Region, Place, Tag)
- **Tag CRUD** (`app/Http/Controllers/TagsController.php`, `app/Models/Tag.php`, `database/migrations/*_create_tags_table.php`, `*_create_tag_user_table.php`, `database/factories/TagFactory.php`, `app/Http/Resources/TagResource.php`) — model `Tag` sa `library_id`, jedinstveno ime po biblioteci, CRUD kontroler, `StoreTagRequest`/`UpdateTagRequest`
- **Dodela tagova korisnicima** (`app/Http/Controllers/UserTagsController.php`) — `sync` (po biblioteci, čuva tagove drugih biblioteka) i bulk `assign`/`remove` za više korisnika, uz validaciju pripadnosti taga/biblioteke i članstva
- **Profile API** (`app/Http/Controllers/ProfileController.php`, `UpdateProfileRequest.php`, `UpdatePasswordRequest.php`) — prikaz, izmena profila (bez mogućnosti promene `role`/`libraries`/`bar_code`), promena lozinke uz `current_password`
- **Izmena lozinke korisnika od strane superadmina** (`UpdateUserPasswordRequest.php`, `UsersController::updatePassword`) — bez `current_password`
- **Životni ciklus članstva** (`app/Services/LibraryMembershipService.php`, `app/Http/Controllers/UserMembershipsController.php`, `app/Models/LibraryUserPivot.php`, `database/migrations/*_add_deleted_at_to_library_user_table.php`) — deaktivacija (soft delete pivota), aktivacija (restore) i trajno uklanjanje članstva; `LibraryUserPivot` pivot sa `SoftDeletes` i prepisanim `delete()`
- **ActiveLibraryService** (`app/Services/ActiveLibraryService.php`) — serverski kontekst aktivne biblioteke u sesiji: izbor, fallback na jedinu biblioteku, scope korisnika po biblioteci; endpoint `/api/v1/auth/active-library`
- **UserFilters** (`app/Queries/UserFilters.php`) — kolonska pretraga korisnika (`email`, `username`, `first_name`, `last_name`, `bar_code`, `jmbg`, `city`, `role`, `library_id`) umesto catch-all `search`
- **SetSessionCookieDomain** (`app/Http/Middleware/SetSessionCookieDomain.php`, `config/session.php`, `.env.example`) — dinamiko postavljanje `session.domain` na `SESSION_COOKIE_DOMAIN_PUBLIC` za javne domene (deljenje kolačića između poddomena), localhost ostaje host-only
- **Blameable** (`app/Models/Concerns/Blameable.php`) — `created_by`/`updated_by` trait (Laravel ekvivalent Yii2 BlameableBehavior)
- **AuthorizesByCreator** (`app/Policies/Concerns/AuthorizesByCreator.php`) — ownership pattern za buduće modele sa `created_by`
- **Frontend stranice** (`frontend/src/pages/regions/regions-page.tsx`, `places/places-page.tsx`, `tags/tags-page.tsx`, `profile/profile-page.tsx`, `settings/settings-page.tsx`) — CRUD tabele za regione/mesta/tagove, profil korisnika i podešavanja (dark mode)
- **Form modali** (`frontend/src/components/regions/region-form-modal.tsx`, `places/place-form-modal.tsx`, `tags/tag-form-modal.tsx`, `profile/change-password-dialog.tsx`, `users/user-membership-modal.tsx`, `users/user-tag-modal.tsx`, `users/user-tags-bulk-modal.tsx`) — kreiranje/izmena i bulk dodela tagova, upravljanje članstvima
- **LibrarySwitcher i select** (`frontend/src/components/layout/library-switcher.tsx`, `components/libraries/library-select.tsx`, `components/tags/tag-select.tsx`) — izbor aktivne biblioteke i selectovi za biblioteke/tagove
- **DropdownMenu** (`frontend/src/components/ui/dropdown-menu.tsx`) — korisnički meni (profil, podešavanja, promena lozinke, odjava)
- **Testovi** (`tests/Feature/Auth/*`) — ActiveLibrary, LibraryAdminTags, LibraryMembership, ProfileApi, RegionPlaceCrudApi, TagCrudApi, UserTagAssignment; prošireni UserCrudApi/UsersApi (permissions, filteri, promena lozinke)

### Changed

- **Rute** (`routes/api.php`, `routes/auth.php`) — `manage-users`/`manage-libraries` zamenjene policy abilities; dodati profile, tagovi, članstva, active-library i restore biblioteke endpointi
- **`/me` odgovor** (`routes/auth.php`) — dodat `permissions`, `selectable_libraries` i `active_library`; `AppServiceProvider` bez inline `Gate` definicija
- **Nav baziran na permisijama** (`frontend/src/components/layout/app-shell.tsx`) — sidebar filtriran po `can(permission)` umesto po ulozi; dodate stavke regioni/mesta/tagovi; korisnički meni prebačen u DropdownMenu
- **useAuth** (`frontend/src/hooks/useAuth.tsx`) — `permissions`, `libraries`, `activeLibrary`, `can()` i `setActiveLibrary()`; `setUnauthorizedHandler` za 401
- **Tema** (`frontend/src/main.tsx`, `frontend/src/index.css`) — `next-themes` ThemeProvider (system/light/dark) sa `storageKey="ebib.theme"`
- **Javni view-ovi prevedeni na ćirilicu** (`resources/views/public/*`, `resources/css/app.css`) — home, project, contact, libraries, footer, nav; testovi `PublicContactTest`/`PublicHomeTest`/`PublicProjectTest` ažurirani
- **i18n** (`frontend/src/i18n/locales/*.json`) — ključevi za nav, profil, tagove, regione, mesta, članstva, podešavanja
- **Frontend build** (`frontend/dist/`) — regenerisani asseti



### Added

- **Users/Libraries CRUD API** (`app/Http/Controllers/UsersController.php`, `app/Http/Controllers/LibrariesController.php`, `routes/api.php`) — `show`/`update`/`destroy` endpointi za korisnike i biblioteke; soft delete korisnika (migracija `deleted_at` + `SoftDeletes` na `User`), biblioteka se deaktivira kroz postojeći `deleted` flag; zaštita od brisanja sopstvenog naloga
- **Request klase** (`app/Http/Requests/UpdateUserRequest.php`, `app/Http/Requests/UpdateLibraryRequest.php`) — validacija izmene uz ignorisanje sopstvenog `email`/`username`/`bar_code` i opcionu lozinku
- **UserResource** (`app/Http/Resources/UserResource.php`) — izložena profilska polja (`jmbg`, `address`, `city`, `post_code`)
- **Testovi** (`tests/Feature/Auth/UserCrudApiTest.php`) — prikaz, izmena, soft delete korisnika/biblioteke, samodelecija i pagination meta
- **Server-side paginacija** (`frontend/src/pages/users/users-page.tsx`, `frontend/src/pages/libraries/libraries-page.tsx`) — lista 10 po strani, sinhronizacija `page`/`q` kroz URL query parametre i shadcn `Pagination`
- **CRUD akcije u tabelama** — ikonice pogled/izmena/obriši po redu i AlertDialog potvrda za brisanje
- **Prikaz korisnika** (`frontend/src/pages/users/user-detail-page.tsx`) — ruta `/users/:id` sa karticom (avatar/inicijali, ime, uloga, bar-kod, biblioteke) i tabovima Podaci / Rezervacije / Pozajmice (samo Podaci su funkcionalni)
- **Prikaz biblioteke** (`frontend/src/pages/libraries/library-detail-page.tsx`) — read-only ruta `/libraries/:id`
- **Deljeni modali** (`frontend/src/components/users/user-form-modal.tsx`, `frontend/src/components/libraries/library-form-modal.tsx`) — kreiranje i izmena u istom dialogu
- **shadcn/ui komponente** (`frontend/src/components/ui/`) — `tabs`, `pagination`, `alert-dialog`, `avatar`, `table`, `checkbox`, `dialog`, `sonner`; uklonjen custom `modal`
- **PaginationBar** (`frontend/src/components/pagination-bar.tsx`) — numerisana kontrola i prikaz „Приказ X–Y од Z"
- **API klijent** (`frontend/src/lib/api.ts`) — `put`/`delete` metode i helperi `user(id)`/`library(id)`

### Changed

- **Store validacije premeštene u FormRequest** (`StoreUserRequest`, `StoreLibraryRequest`, `StorePlaceRequest`, `StoreContactRequest`) umesto inline `validate()` u kontrolerima (`UsersController`, `LibrariesController`, `PlacesController`, `PublicContactController`)
- **Libraries lista** — `index` vraća samo neobrisane biblioteke (`where('deleted', false)`)
- **Toast stil** (`frontend/src/App.tsx`, `frontend/src/components/ui/sonner.tsx`) — Toaster montiran preko `sonner` komponente sa theme tokenima; pozivi toast-a struktuirani (title + description)
- **Auth forme** — refaktor na shadcn komponente i RHF `onTouched` validaciju
- **Validation poruke** (`lang/{sr-Cyrl,sr-Latn,en}/validation.php`) — dodato `cannot_delete_self`
- **i18n** (`frontend/src/i18n/locales/*.json`) — ključevi za CRUD akcije, paginaciju, detail stranice i tabove (sr-Cyrl, sr-Latn, en)
- **`frontend/src/index.css`** — `tw-animate-css` i `@custom-variant dark`
- **Frontend build** (`frontend/dist/`) — regenerisani asseti

### Removed

- **Custom modal** (`frontend/src/components/ui/modal.tsx`) — zamenjen shadcn `dialog` komponentom
- **Inline validacija** u kontrolerima — premeštena u FormRequest klase

## [03.09.2026] Frontend: Link na početnu sajta sa guest auth stranica

### Changed

- **Auth layout** (`frontend/src/pages/auth/auth-layout.tsx`) — na `/login`, `/forgot-password` i `/reset-password` dodat link „Nazad na sajt" koji vodi na javnu početnu stranicu (`laravelBaseUrl()`)
- **Auth forme** (login, forgot, reset i admin modali) — `react-hook-form` prebačen na `mode: 'onTouched'` da se validacione greške čiste dok korisnik kuca (izbegava se prikaz zastarelih „required" poruka)

## [03.09.2026] Frontend: React dashboard shell, auth ekrani i i18n (ćirilica default)

### Added

- **Frontend stack** (`frontend/`) — Tailwind CSS v4, shadcn/ui komponente (button, input, label, card, badge, select, modal), React Router, TanStack Query, React Hook Form + Zod
- **Dizajn tokeni** (`frontend/src/index.css`) — shadcn look zadržan; blagi žuti akcenat (`#ffd968`) na sidebar/nav aktivnim stavkama i brand elementima; fontovi ne menjani
- **Auth ekrani** (`frontend/src/pages/auth/`) — `/login`, `/forgot-password`, `/reset-password` sa split layout-om (brand panel u ćirilici/latinici/engleskom), jezičkim menjačem i shadcn formama
- **Dashboard shell** (`frontend/src/components/layout/app-shell.tsx`) — sidebar (Početna, Users, Libraries prema ulozi), header sa jezičkim menjačem i odjavom; `/` dashboard početna sa praznim widget prostorom
- **Users/Libraries stranice** (`frontend/src/pages/users/`, `frontend/src/pages/libraries/`) — liste sa pretragom i forme za kreiranje korisnika (sa rolom, bibliotekama m2m, auto bar-kod) i biblioteka (region → mesto kaskada)
- **API klijent** (`frontend/src/lib/api.ts`) — fetch sa `credentials: include`, Sanctum CSRF flow, `X-Locale` header, tipizirane rute
- **i18n** (`frontend/src/i18n/`) — i18next, default `sr-Cyrl`, opcije `sr-Latn` i `en` (JSON prevodi), pamćenje izbora u localStorage

### Changed

- **Auth context** (`frontend/src/hooks/useAuth.tsx`) — stanje autentikacije iz `/api/v1/auth/me`; login/logout kroz TanStack Query
- **Javni portal** — uklonjen CTA „Kreiraj nalog" (nema javne registracije); ostaje „Prijava"
- **Frontend build** (`frontend/dist/`) — regenerisani asseti

## [03.09.2026] Backend: Role model (Postgres enum), Users/Libraries API i server i18n

### Added

- **Role enum** (`app/Enums/UserRole.php`) — `superadmin`, `library_admin`, `librarian`, `user` sa `EnumToArray` trait-om (`toArray`, `toArrayWithValue`, lokalizovan `getLabel`) i mapiranjem iz Symfony/FOS legacy-ja
- **Migrations** — `user_role` native Postgres enum tip, profilska polja na `users` (username, first/last name, jmbg, address, city, post_code, bar_code), tabele `regions`, `places`, `libraries`, pivot `library_user` (m2m korisnik–biblioteka)
- **Modeli** — `Region`, `Place`, `Library` + relacije; `User` sa role cast-om, `libraries()`, `displayName()`
- **Users/Libraries API** (`app/Http/Controllers/`) — liste i kreiranje korisnika (bar-kod auto-generisan kroz API), biblioteka, regija i mesta; `/api/v1/roles` i `/roles/assignable`; Gate za superadmin upravljanje
- **UserResource** — `/api/v1/auth/me` vraća korisnika sa rolom
- **Superadmin seed** (`app/Console/Commands/EnsureSuperAdmin.php`) — `admin@ebiblioteka.rs`
- **Početne regije/mesta** (`database/seeders/LocationSeeder.php`)
- **Server i18n** — `SetLocale` middleware (`X-Locale`), `lang/{sr-Cyrl,sr-Latn,en}` fajlovi (auth, passwords, roles, validation); reset email link vodi na React `/reset-password`
- **Testovi** (`tests/Feature/Auth/UsersApiTest.php`) — role pristup, kreiranje korisnika/biblioteke, lokalizovane role labele, bar-kod

### Removed

- **Javna registracija** — `POST /api/v1/auth/register`, `RegisteredUserController`, `RegistrationTest`

## [02.09.2026] Frontend: React environment konfiguracija i Vite host podešavanja

### Added

- **Environment helper** (`frontend/src/lib/environment.ts`) — `laravelBaseUrl()` koji bira Laravel origin na osnovu hostname-a ili `VITE_LARAVEL_URL` override-a
- **Tunnel dokumentacija** (`TUNNEL.md`) — vodič za Cloudflare named tunnel deljenje lokalnog stack-a (Laravel na `demo.ebiblioteka.rs`, React na `dashboard.ebiblioteka.rs`)

### Changed

- **React aplikacija** (`frontend/src/App.tsx`) — „Nazad na početnu stranicu" link koristi `laravelBaseUrl()`
- **React stil** (`frontend/src/styles.css`) — DM Sans umesto monospace u kicker etiketi
- **Vite dev server** (`frontend/vite.config.ts`) — `host: 0.0.0.0` i dozvoljeni hostovi za tunnel (dashboard.ebiblioteka.rs, trycloudflare.com)
- **Frontend build** (`frontend/dist/`) — regenerisani JS/CSS asseti (`index-jrFFmw56.js`, `index-BqoD0GO7.css`) sa ažuriranim reference-ama u `index.html`

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
