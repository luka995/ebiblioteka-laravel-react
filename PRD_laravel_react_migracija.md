# PRD: Migracija eBiblioteka sa Symfony 2 na Laravel API + React Web

> Dokument razlikuje trenutno produkciono stanje (Symfony 2 + Twig) od planiranog
> ciljnog stanja (Laravel API + React). React i Laravel delovi opisani u ovom PRD-u
> predstavljaju plan migracije, osim gde je izricito navedeno da se radi o legacy
> funkcionalnosti.

## Referenca na legacy projekat

Postojeca Symfony 2 aplikacija nalazi se na putanji:

`/var/www/ebiblioteka`

Novi projekat u `/var/www/ebiblioteka-new` koristi ovaj projekat kao referentni
izvor kada treba proveriti kako je neka funkcionalnost vec implementirana. Pre
implementacije migriranog modula potrebno je proveriti odgovarajuce legacy:

- kontrolere u `src/APPB/LibraryBundle/Controller` i `src/APPB/UserBundle/Controller`
- Doctrine entitete i repozitorijume u `src/APPB/*Bundle/Entity` i `src/APPB/*Bundle/Repository`
- Twig prikaze u `app/Resources/views` i bundle `Resources/views` direktorijumima
- rute i security pravila u `app/config`
- SQL/migracione skripte i postojece izvestaje kada se funkcionalnost oslanja na njih

Legacy projekat je referenca za postojecu funkcionalnost, poslovna pravila, dozvole,
redosled korisnickih koraka i format izvestaja. Ne treba direktno kopirati Symfony,
Doctrine ili Twig implementaciju u novi projekat. Implementaciju treba prilagoditi
Laravel API + React arhitekturi, uz ocuvanje funkcionalnog ponasanja osim kada je u
ovom PRD-u izricito definisana promena.

## 1. Uvod

### 1.1 Cilj
Cilj je rewrite postojece aplikacije (Symfony 2 + Twig + FOSUserBundle) u modernu,
odvojenu arhitekturu:
- Laravel API kao novi backend
- React kao novi web klijent

uz zadrzavanje postojece poslovne logike i uvodjenje novih funkcionalnosti:
- Razred
- Class (pripada razredu)
- Povezivanje ucenika (korisnika) sa class-om

### 1.2 Poslovni kontekst
Aplikacija je skolsko-bibliotecki sistem sa sledecim domenima:
- Biblioteke i njihove konfiguracije
- Katalog knjiga, autori, kategorije, fizicke jedinice
- Pozajmice, rezervacije, istorija i clanarine
- Korisnici i role
- Statistika, izvestaji, import podataka, notifikacije

### 1.3 Terminoloski standard za novu aplikaciju
- U Laravel API + React aplikaciji koristi se termin Fizicke jedinice.
- Izrazi kopija i kopije knjiga se ne koriste u novoj dokumentaciji i UI-u.
- Legacy nazivi (npr BookCopy, book_copies, copyInfo ruta) ostaju samo kao referenca na postojeci kod i migraciju.

### 1.4 Scope
U scope-u:
- Funkcionalna migracija svih postojecih modula
- Nova RBAC implementacija bez Spatie paketa
- Nova skolska hijerarhija razred -> class -> ucenik + skolska godina
- Migracija podataka i postepeni rollout
- Obavezan frontend standard: React UI mora koristiti shadcn/ui komponente, Tailwind CSS i Lucide React ikonice
- Migracija na novu (greenfield) PostgreSQL bazu za Laravel sistem

Van scope-a (za ovaj PRD):
- Potpuno odvojena native mobilna aplikacija sa zasebnim UI-jem i poslovnom logikom
- Potpuni redesign poslovnih pravila (osim gde je eksplicitno navedeno)

Napomena:
- Mobilno izdanje se razvija iz planiranog React web klijenta kroz Capacitor wrapper,
  a ne kao odvojena native aplikacija sa zasebnim UI-jem i poslovnom logikom.

### 1.5 Razdvajanje trenutnog i planiranog stanja

| Oblast | Trenutno stanje | Planirano stanje |
|---|---|---|
| Backend | Symfony 2 aplikacija | Laravel API |
| Prezentacija | Twig server-rendered UI | React + Vite web klijent |
| API komunikacija | Legacy Symfony tokovi i rute | Verziona JSON REST API komunikacija |
| ORM i pristup podacima | Doctrine, repozitorijumi i deo raw SQL-a | Eloquent, services i query objekti |
| Autentikacija i role | FOSUserBundle, Doctrine array roles | Laravel auth, Policy/Gate i enum rola |
| Baza | Legacy baza | Nova PostgreSQL baza |
| Status | Postojeći sistem i izvor za audit/migraciju | Ciljna implementacija |

---

## 2. Trenutno stanje: Symfony 2 aplikacija

Ovo poglavlje opisuje postojeci produkcioni softver i sluzi kao referentna osnova
za migraciju. Trenutna aplikacija koristi Symfony 2, Twig, Doctrine i FOSUserBundle.
U ovom stanju nema Laravel API-ja niti React klijenta kao ciljne arhitekture.

### 2.1 Kontroleri i funkcionalni moduli (kompletna lista)

### APPB\\LibraryBundle
- AuthorController: index, new, create, ajaxSearch, edit, update, delete
- BannerController: new, create
- BookController: index, new, ajaxSearch, edit, delete
- BookCopiesController: index, copyInfo, listaKopija, copies, dismiss, dismissCancel, rec_errorCopy, outofdate, unusable, deleteCopy, history, copyNew, copyCreate, copyEdit, copyUpdate, print
- BookCopiesConfirmedController: newAndInfo, delete
- CategoryController: index, new, create, edit, update, delete
- CityController: index, new, create, edit, update, delete
- DefaultController: index, loginblock, pdf, changeLocale, contact
- ExpenseController: index, new, create
- ImageController: resize
- ImportController: form
- InventarBookController: index, print, status
- LibraryController: index, print, new, create, edit, update, delete, activate
- LibrarySettingsController: index, update
- LogsController: index
- NewsController: index, publicView, view, new, create, edit, update, delete
- NotificationController: emailNotification
- PageController: index, new, create, edit, update, delete, view
- PublicLibraryController: index, view, category, book, search, reserve
- PublicUserController: index, profile, history, membership, reservations, reservationsDelete, changeData
- RecomendationController: index, publicView, view, new, create, edit, update, delete
- RegionController: index, new, create, edit, update, delete
- ReportTakenBooksController: index, history
- StatController: index, returns, revision, category, header, opterecenje, presek
- TagController: index, new, create, edit, update, delete
- UserController: addTags, removeTags, index, userprint, new, newType, create, edit, update, delete, activate, detail, logas, logback, print

### APPB\\UserBundle
- DefaultController: index

Napomena:
- Admin i public tokovi su mesani kroz iste bundle-ove i Twig prikaze.
- Dobar deo poslovne logike je u kontrolerima (debeli kontroleri), sto je bitna stavka za refaktor u Laravel service sloj.

### 2.2 Modeli i relacije (Doctrine)

### Tabele i entiteti
- fos_user -> User
- library -> Library
- library_settings -> LibrarySettings
- region -> Region
- city -> City
- category -> Category
- authors -> Author
- books -> Book
- book_copies -> BookCopy
- book_copies_confirmed -> BookCopiesConfirmed
- book_history -> BookHistory
- borrow -> Borrow
- reservation -> Reservation
- membership -> Membership
- transaction -> Transaction
- tags -> Tag
- access_logs -> Log
- inventar_books -> InventarBook
- news -> News
- recomendations -> Recomendation
- pages -> Page
- banners -> Banner
- books_central -> BookCentral
- authors_central -> AuthorCentral

### Relacije (sazetak)
- User M:1 Library
- User M:N Tag (pivot user_tag)
- Library M:1 Region
- Library M:1 City
- LibrarySettings 1:1 Library
- Category M:1 Library
- Category M:1 Category (parent self-reference)
- Author M:1 Library
- Book M:1 Library
- Book M:1 Category (primary)
- Book M:1 Category (secondary)
- Book M:N Author (pivot book_authors)
- BookCopy M:1 Library
- BookCopy M:1 Book
- BookCopy 1:N BookCopiesConfirmed
- BookCopiesConfirmed M:1 Library
- BookCopiesConfirmed M:1 BookCopy
- Borrow M:1 Library
- Borrow M:1 Book
- Borrow M:1 BookCopy
- Borrow M:1 User
- Reservation M:1 Library
- Reservation M:1 Book
- Reservation M:1 BookCopy
- Reservation M:1 User
- Membership M:1 Library
- Membership M:1 User
- Transaction M:1 Library
- Tag M:1 Library
- Tag M:N User (obrnuta strana)
- Log M:1 User
- Log M:1 Library
- InventarBook M:1 Library
- BookCentral M:N AuthorCentral (pivot book_central_authors)

### 2.3 Repozitorijumi (kompletna lista i namena)

- UserRepository
  - filterDQL(...): filtriranje korisnika po barcode, imenu, email, role, tag-u, biblioteci i lock statusu
  - koristi LIKE nad poljem roles (string reprezentacija role array-a)

- BookRepository
  - bookIds(...): lista ID knjiga biblioteke
  - filterDQL, filterDQLAddBook, filterDQLPublic: pretraga knjiga
  - filterBookSearch(...): kompleksna pretraga po nazivima, autorima, kategoriji, broju primeraka

- BookCopyRepository
  - filterCopiesSearch(...): pretraga fizickih jedinica po barkodu i recError
  - confirmedBookCopiesIds(...)
  - NumberCopies, NumberDeletedCopiesBeforeFrom, NumberNotReturnedCopies...
  - ConfirmedCopies, NotFoundCopies
  - sadrzi i DQL i native SQL

- LibraryRepository
  - filterLibrary(...): filtriranje biblioteka po nazivu i deleted statusu

- AuthorRepository
  - filterDQL(...), filterColDQL(...): pretraga autora

- TagRepository
  - filterDQL(...): lista/filter tagova sa join ka biblioteci

- BookCentralRepository
  - filterBookSearch(...): pretraga centralnog kataloga knjiga/autora

Zakljucak analize sloja pristupa podacima:
- Upiti su rasuti kroz kontrolere i repozitorijume
- Postoji mix ORM i raw SQL
- Potreban je jasan CQRS-lite pristup u Laravelu (Query service + Domain service)

### 2.4 Auth i role u postojecem resenju

- Koristi se FOSUserBundle
- User entitet je mapiran na fos_user
- FOS mapiranje definise roles kao type=array (Doctrine), prakticno tekstualna serializovana vrednost u koloni roles
- Role hijerarhija u security.yml:
  - ROLE_SUPER_ADMIN
  - ROLE_ADMIN
  - ROLE_LIBRARAIN (tipfeler u legacy kodu)
  - ROLE_ENDUSER
  - ROLE_USER (bazna)

Implikacije za migraciju:
- U bazi je dozvoljen multi-role model (array), ali poslovno aplikacija radi sa malim brojem nivoa
- Potrebna je normalizacija role imena (Librarian umesto LIBRARAIN)

### 2.5 View logika (Twig audit)

Analizirani su kljucni view-ovi kako bi React sloj zadrzao iste korisnicke tokove i ponasanje interfejsa.

### Navigacija i dostupnost po roli
- Glavni layout (`base.html.twig`) renderuje razlicite menije po roli: SUPER_ADMIN, ADMIN, LIBRARAIN, javni korisnik.
- Meniji mapiraju module 1:1 sa kontrolerima: Maticni podaci, Knjige, Korisnici, Finansije, Statistika, Administracija.
- React treba da ima role-based shell/layout i skrivanje stavki menija po istim pravilima.

### List view pattern
- Vecina admin ekrana koristi isti obrazac: filter forma (GET), tabela, pagination, akcije po redu.
- KNP paginator i sortable kolone su standard na listama (knjige, korisnici, autori, tagovi, gradovi, regioni, logovi).
- React ekvivalenti treba da imaju server-side pagination, sort i query param sync.

### Fizicke jedinice (legacy BookCopy) - prikazna logika
- Ekran liste fizickih jedinica (`Book/index_copy.html.twig`) ima filtre barkod i recError i lazy info panel po redu preko AJAX poziva (`appb_admin_book_copy_info`).
- Ekran fizickih jedinica po knjizi (`Book/copies.html.twig`) kombinuje:
  - read-only prikaz metapodataka knjige
  - tabelu fizickih jedinica sa statusima
  - grupnu selekciju za stampu nalepnica
  - lokalnu JS listu selektovanih jedinica (localStorage helper)
- Detalj fizicke jedinice (`Book/copy_info.html.twig`) je akcioni centar:
  - edit
  - otpis/ponistavanje otpisa
  - oznaka pogresno zavedena + napomena
  - potvrda ispravnosti kroz modal i istoriju potvrda
  - istorija dogadjaja po jedinici

### Korisnicki detalj kao workflow ekran
- `User/detail.html.twig` je tab-based workflow: Podaci, Clanstvo, Rezervacije, Pozajmice.
- Jedan endpoint prima vise akcija kroz modal forme (membership, borrow, return, reservation_cancel).
- React treba da zadrzi isti UX tok, ali sa jasnim API komandama po akciji.

### Public katalog
- PublicLibrary view-ovi imaju breadcrumb navigaciju, pretragu po naslovu/autoru/kategoriji, karticni/tablarni prikaz knjiga i akciju rezervacije.
- Potrebno je zadrzati isti informativni nivo i jednostavnost toka pretrage i rezervacije.

### Statistika i revizija
- Revizijski izvestaj (`Stat/revision_report.html.twig`) je detaljan dokument sa sekcijama po statusu fizicke jedinice.
- Print/PDF tok je deo poslovnog procesa i mora ostati funkcionalno ekvivalentan u novoj aplikaciji.

### Zakljucak view analize
- Legacy UI koristi server-render + modal-first pattern.
- React treba da zadrzi:
  - iste entitete i akcije,
  - iste state tranzicije,
  - isti redosled korisnickih koraka,
  - termin Fizicke jedinice u svim ekranima i izvestajima.

### 2.6 Postojeci procesi revizije i opterecenja

- Revizija inventara vec postoji u Symfony aplikaciji kroz `InventarBookController` i
  pratece view-ove. Migracija treba da zadrzi postojeci proces, statuse, istoriju i
  izvestaje, a ne da uvodi poseban novi zapisnik o zatecenom stanju.
- Postojece statistike opterecenja citanosti realizovane su kroz `StatController`
  (`opterecenje`). U Laravel + React verziji treba ih funkcionalno migrirati i tokom
  implementacije razmotriti unapredjenje logike, agregacija i vizuelnog prikaza.
- Graficki prikaz citanosti i prikaz najcitanijih naslova predstavljaju unapredjenje
  postojeceg modula opterecenja, a ne odvojen proces ili duplu evidenciju.

---

## 3. Planirano stanje: Laravel API + React

Ovo je ciljna arhitektura nakon rewrite-a. Laravel zamenjuje Symfony backend, a
React zamenjuje Twig UI. Nova aplikacija komunicira preko verzionisanog JSON REST
API-ja i koristi novu PostgreSQL bazu. Symfony ostaje samo legacy izvor za audit,
poslovna pravila, migraciju podataka i funkcionalnu verifikaciju tokom tranzicije.

### 3.1 Backend (Laravel API)
Predlog:
- Laravel 12
- PostgreSQL kao ciljna baza podataka
- Sanctum za SPA autentikaciju (session/cookie) — usvojeni izbor, detalji u sekciji 3.6
- API Resources za response DTO sloj
- Form Request validacije
- Policy + Gate za autorizaciju
- Service sloj za domensku logiku
- Query objekti za kompleksne statistike

Slojevi:
- Controllers (tanki)
- Services (domenska pravila)
- Repositories/Query services (slozeni upiti)
- Eloquent modeli + relacije
- Jobs (importi, izvestaji, masovne operacije)

### 3.2 Frontend (React Web)
Predlog:
- React + Vite
- React Router
- TypeScript za tipski bezbedan frontend kod
- TanStack Query za asinhroni state, kesiranje, sinhronizaciju i mutacije prema API-ju
- React Hook Form za upravljanje formama
- Zod za deklarativnu validaciju schema i inferenciju TypeScript tipova
- shadcn/ui kao primarna UI komponentna biblioteka (komponente se kopiraju u projekat i ostaju pod punom kontrolom projekta)
- Radix UI primitive i Tailwind CSS kao osnova shadcn/ui komponenti
- Lucide React kao jedinstveni sistem ikonica
- PWA podrška za web izdanje aplikacije (manifest, service worker, instalacija na
  Android uredjaj i pokretanje u standalone modu)
- Capacitor kao wrapper za Android/iOS mobilno izdanje kada su potrebni pristup
  galeriji, kameri ili drugim native API-jima
- UI po modulima:
  - Admin panel
  - Public katalog
  - Korisnicki profil

Obavezni UI standardi:
- Zabranjeno je uvodjenje paralelnog UI framework-a za core ekrane (npr. MUI, Ant Design, Bootstrap component library i sl.)
- Svi osnovni elementi (forma, tabela, modal, tabs, drawer, snackbar, dialog, date picker, select i pagination) implementiraju se kroz shadcn/ui komponente
- Stilovi se definisu kroz Tailwind CSS tokene i projektne varijable, uz podrsku za light/dark temu gde je predvidjena
- Komponente moraju ostati pristupacne i koristiti Radix UI primitive gde ih shadcn/ui predvidja
- Lucide React ikonice koriste se za pretragu, izmenu, brisanje, dodavanje, navigaciju i ostale standardne akcije
- Responsivni layout mora podrzati desktop i mobilne prikaze, sa jasnim focus, hover, loading, error i disabled stanjima
- Web aplikacija i Capacitor mobilno izdanje koriste isti React UI i poslovnu logiku
- PWA i Capacitor izdanje moraju imati jasno definisan fallback za funkcije koje nisu
  dostupne u browseru

#### 3.2.1 Frontend slojevi i tok podataka

Frontend je potpuno odvojen od Laravel backenda i komunicira sa njim iskljucivo
preko JSON REST API-ja. Symfony se ne koristi kao backend za planirani React klijent;
naveden je samo u audit delu i migracionim napomenama. Ciljna implementacija koristi
sledece slojeve:

```text
                    E-BIBLIOTEKA FRONTEND
                              |
          +-------------------+-------------------+
          |                                       |
       UI sloj                              Data sloj
          |                                       |
      shadcn/ui                           TanStack Query
          |                                       |
 React Hook Form + Zod                  Centralni API klijent
          |                                       |
    Lucide React                         Laravel REST API
```

Odgovornosti slojeva:
- React i React Router upravljaju komponentnim modelom, rutama i layout-ima po ulogama.
- shadcn/ui obezbedjuje standardizovane Table, Dialog, Button, Select, Input i Pagination komponente.
- TanStack Query upravlja ucitavanjem, kesiranjem, stale-while-revalidate osvezavanjem,
  paginacijom, greskama i mutacijama bez rucnog asinhronog state boilerplate-a.
- React Hook Form upravlja zivotnim ciklusom formi i koristi uncontrolled pristup gde je moguce.
- Zod schema validira forme i API podatke, a TypeScript tipovi se izvode iz schema.
- Centralni Axios ili Fetch klijent standardizuje base URL, JSON zaglavlja, autentikaciju,
  CSRF cookie tok i slanje kredencijala (`credentials: 'include'`) prema Sanctum
  session-based strategiji (sekcija 3.6), uz obradu gresaka.

Standardni query/mutation obrasci:
- `useQuery` se koristi za GET liste i detalje, na primer `GET /api/students`.
- `useMutation` se koristi za kreiranje, izmenu, brisanje i domenske akcije.
- Nakon uspesne mutacije invalidira se odgovarajuci query key, na primer
  `queryClient.invalidateQueries({ queryKey: ['students'] })`.
- Optimistic update se koristi samo za akcije kod kojih je moguce pouzdano vratiti
  prethodno stanje ako API operacija ne uspe.

#### 3.2.2 Standard implementacije modula

Svaki novi ili migrirani modul treba da prati isti tok:
1. TanStack Query ucitava podatke i izlozi loading/error/empty stanja.
2. shadcn/ui i Lucide React prikazuju tabelu, filtere, paginaciju i akcije.
3. React Hook Form otvara kreiranje ili izmenu u Dialog komponenti.
4. Zod validira unos pre slanja mutacije.
5. Uspeh ili greska mutacije prikazuju se kroz standardni feedback mehanizam.

Primer za modul Ucenici:
- Lista koristi `useQuery` za `GET /api/students`, server-side paginaciju, sortiranje,
  filtriranje i sinhronizaciju filtera sa URL query parametrima.
- Tabela koristi shadcn/ui Table, a akcije koriste Lucide ikonice kao sto su `Pencil`,
  `Trash2` i `Plus`.
- Forma za dodavanje i izmenu koristi shadcn/ui Dialog, React Hook Form i Zod schema.
- Nakon kreiranja, izmene ili brisanja lista ucenika se osvezava invalidacijom query kljuca.

## 3.3 Modulizacija po domenu
- Auth & Users
- Libraries & Settings
- Catalog (Books, Authors, Categories, Tags)
- Fizicke jedinice & Inventory
- Borrow/Return/Reservation
- Membership & Transactions
- Reports & Statistics
- CMS (News, Pages, Banner)
- Imports

## 3.4 Strategija baze podataka
- Strategija je greenfield: nova aplikacija koristi novu PostgreSQL semu.
- Legacy baza ostaje izvor podataka samo za migraciju i verifikaciju.
- Migracija se radi ETL procesom iz legacy baze u novu PostgreSQL bazu (bez inplace izmene legacy seme).
- Tokom tranzicije radi se paralelna validacija kljucnih agregata (broj korisnika, fizickih jedinica, aktivnih pozajmica, finansijskih zapisa).

## 3.5 Strategija mobilnog izdanja i skeniranja

### Osnovno resenje: PWA + Capacitor

- Web izdanje aplikacije dostupno je kao PWA i moze da se instalira na Android uredjaj
  iz browsera, uz ikonu na pocetnom ekranu i standalone prikaz koji korisniku daje
  utisak mobilne aplikacije.
- Za mobilno izdanje sa pristupom sistemskoj galeriji koristi se Capacitor wrapper
  nad istim React kodom.
- Capacitor je obavezan zbog zahteva da korisnik moze da sacuva skeniranu sliku u
  galeriju telefona.
- Capacitor izdanje moze kasnije da se objavi u Google Play prodavnici, uz pripremu
  Android projekta, signing kljuceva, privacy politike i jednokratnu Google Play
  registraciju.

### Skeniranje i cuvanje slika

- React sloj implementira skeniranje QR kodova, ISBN-a i barkodova kroz web-kompatibilan
  skener. Biblioteke za razmatranje su `zxing-wasm`, `@yudiel/react-qr-scanner` i
  `web-wasm-barcode-reader`, uz izbor koji se potvrdi testiranjem na podrzanim
  browserima i uredjajima.
- Na native Capacitor platformi koristi se `@capacitor/camera` za pristup kameri i
  cuvanje fotografije u galeriju kroz `saveToGallery: true`.
- Aplikacija proverava `Capacitor.isNativePlatform()` pre poziva native funkcionalnosti.
- U browser/PWA izdanju, gde cuvanje direktno u sistemsku galeriju nije garantovano,
  koristi se fallback za generisanje web preuzimanja slike.
- Za iOS se planiraju potrebne dozvole u `Info.plist`, najmanje za kameru i dodavanje
  fotografija u galeriju. Za Android se dozvole i konfiguracija definišu u
  `AndroidManifest.xml` prema ciljanoj verziji Androida i konkretnoj Capacitor verziji.
- Neophodno je obezbediti poruke za odbijenu dozvolu, nedostupnu kameru, neuspesno
  ocitavanje i neuspesno cuvanje slike.

### Alternativa: PWA + TWA

- Ako se zahtev za cuvanje slika u sistemskoj galeriji ukloni ili zameni obicnim web
  preuzimanjem, moze se razmotriti TWA izdanje kroz Bubblewrap.
- TWA prikazuje postojece PWA izdanje kroz Chrome Trusted Web Activity i moze se
  distribuirati kroz Google Play bez zasebnog native UI-ja.
- TWA ne obezbedjuje isti pristup galeriji, fajl sistemu i drugim dubokim native API-jima
  kao Capacitor i zato nije trenutno usvojeno resenje.
- Ova alternativa zadrzava isti React kod i smanjuje kompleksnost odrzavanja, ali se
  uvodi tek nakon formalne promene zahteva za galeriju.

## 3.6 Autentikacija: Laravel Sanctum session-based (cookie auth)

### Odluka

Za aplikaciju eBiblioteka autentikacija koristi **Laravel Sanctum sa session-based
autentikacijom i HTTP-only cookies**.

Ne koristi se JWT ni custom access tokens.

Frontend je React SPA hostovan na drugom domenu u odnosu na Laravel API.

Primer:

- React: `https://admin.e-biblioteka.rs`
- Laravel API: `https://api.e-biblioteka.rs`

### Authentication flow

1. Korisnik salje email/username i lozinku iz React-a.
2. React salje login zahtev ka Laravel API-ju.
3. Laravel validira kredencijale i kreira autentikovanu sesiju.
4. Laravel vraca session cookie kroz `Set-Cookie`.
5. Browser cuva cookie.
6. React NE cita niti upravlja session cookie-jem.
7. Browser automatski salje cookie uz naredne API zahteve.
8. Laravel razresava autentikovanog korisnika iz sesije.
9. React odredjuje stanje autentikacije iz API odgovora kao sto je `/api/user` ili `/api/me`.

Session cookie mora biti konfigurisan kao:

- `HttpOnly`
- `Secure` u produkciji
- odgovarajuca `SameSite` konfiguracija
- ispravna `domain` konfiguracija za SPA/API setup

### React requirements

API zahtevi koji zahtevaju autentikaciju moraju slati credentials.

Za `fetch`:

```js
fetch(url, {
    credentials: 'include',
});
```

Za Axios:

```js
axios.defaults.withCredentials = true;
```

React nikada ne sme da cita, cuva ili rucno dodaje session cookie.

### Laravel requirements

Koristi se Laravel Sanctum SPA/session autentikacioni mehanizam.

Konfigurise se:

- Sanctum stateful domains
- session cookie domain
- CORS
- supports_credentials
- HTTPS u produkciji

Auth endpointi (uskladjeni sa verzionisanim namespace-om u sekciji 6.1) ukljucuju:

- GET  /sanctum/csrf-cookie
- POST /api/v1/auth/login
- POST /api/v1/auth/logout
- GET  /api/v1/auth/me

`/api/v1/auth/me` (ekvivalent `/api/user`) koristi React za utvrdjivanje trenutno
autentikovanog korisnika.

### CORS

Zato sto React i Laravel API koriste razlicite domene, Laravel mora eksplicitno
dozvoliti React origin.

Credentials moraju biti omoguceni:

```
Access-Control-Allow-Credentials: true
```

React origin mora biti eksplicitno dozvoljen; `*` se ne koristi kada su u pitanju
credentials/cookies.

### User implementation (inicijalni koraci)

- User model i database migration
- Login endpoint
- Logout endpoint
- Current authenticated user endpoint
- Sanctum/session konfiguracija
- CORS konfiguracija
- Authentication middleware
- React login flow
- React authentication state / protected routes

Autentikacija ostaje zasnovana na Laravel sesiji. JWT, refresh tokeni i
localStorage-based autentikacija se ne uvode osim ako buduci zahtev to izricito
opravda.

---

## 4. Novi zahtevi: Razred, Class, Ucenik

## 4.1 Poslovna pravila
- Svaki class ima razred kao enum vrednost 1-8 (bez posebne relacije na tabelu razreda)
- U prikazu razred mora biti mapiran i rimskim brojevima (I-VIII)
- Jedan class pripada tacno jednoj skolskoj godini
- Ucenik (korisnik sa rolom USER) pripada jednom class-u
- Ucenik moze promeniti class kroz vreme, uz cuvanje istorije rasporeda
- Class pripada biblioteci/skoli (preko library_id)
- Administratori biblioteke vide i menjaju samo svoje razrede/class-ove/ucenike
- Skolska godina se definise na nivou sistema i njom upravlja glavni admin (SUPER_ADMIN)

## 4.2 Predlog baze (Laravel)

### Nova tabela school_years
Kolone:
- id
- name (npr 2026/2027)
- starts_on (date)
- ends_on (date)
- is_active
- created_by (FK -> users.id, SUPER_ADMIN)
- timestamps

Unikat:
- unique(name)

### Nova tabela classes
Kolone:
- id
- library_id (FK -> libraries.id)
- school_year_id (FK -> school_years.id)
- grade (tinyint unsigned; dozvoljene vrednosti: 1-8)
- name (npr 1-1, I-2, 2b)
- teacher_name (opciono)
- is_active
- timestamps

Unikat:
- unique(library_id, school_year_id, grade, name)

Prikaz razreda:
- Backend cuva numeric vrednost 1-8
- Frontend prikazuje i rimski ekvivalent (1->I, 2->II, ..., 8->VIII)
- U formama za class razred je select sa vrednostima 1-8 i labelama tipa "1 (I)", "2 (II)" ...

### Izmene users tabele
Dodati:
- Nema direktne class kolone na users; veza ide preko student_class pivot tabele

Pravilo integriteta:
- Trenutni class korisnika dobija se iz aktivne (`active = true`) relacije u student_class tabeli
- Razred se ne cuva kao posebna relacija ni kolona na korisniku

### Nova tabela student_class (junction/pivot)
Kolone:
- id
- student_id (FK -> users.id)
- class_id (FK -> classes.id)
- school_year_id (FK -> school_years.id)
- active (boolean)
- timestamps

Unikat i integritet:
- unique(student_id, class_id, school_year_id)
- Postoji najvise jedna aktivna relacija po studentu (PostgreSQL partial unique index nad `student_id` gde je `active = true`)

Pravila:
- Pri svakoj promeni class-a, prethodna aktivna relacija prelazi u `active = false`, a nova relacija postaje `active = true`.
- Istorija rasporeda se dobija kroz neaktivne relacije u student_class tabeli.

## 4.3 API endpointi za novi modul
- GET /api/v1/system/school-years
- POST /api/v1/system/school-years
- PUT /api/v1/system/school-years/{id}
- DELETE /api/v1/system/school-years/{id}
- GET /api/v1/classes
- POST /api/v1/classes
- PUT /api/v1/classes/{id}
- DELETE /api/v1/classes/{id}
- POST /api/v1/classes/{id}/students/assign
- POST /api/v1/classes/{id}/students/unassign
- GET /api/v1/classes/{id}/students

Autorizacija:
- school-years endpointi: samo SUPER_ADMIN
- classes endpointi: SUPER_ADMIN i LIBRARY_ADMIN (scope na biblioteku)

## 4.4 React ekranI
- Sistemski sifarnik skolskih godina (samo SUPER_ADMIN)
- Sifarnik class-ova
- U formi class-a obavezni select za skolsku godinu i select razreda (1-8 sa rimskim prikazom)
- Lista ucenika sa filterima: razred, class, status, tag
- Bulk dodela ucenika u class

## 4.5 Automatizacija katalogizacije i inventara

### Skeniranje ISBN/bar-koda

- Prilikom unosa nove knjige bibliotekar moze da skenira ISBN, QR kod ili bar-kod
  koriscenjem kamere preko web browsera ili povezanog bar-kod citaca.
- Skeniranje je deo web aplikacije. Native mobilna aplikacija nije deo trenutnog
  scope-a. Capacitor wrapper je planirani mobilni sloj istog React klijenta i ne
  predstavlja zasebnu native aplikaciju.
- Nakon uspesnog ocitavanja ISBN-a, sistem pokusava da preuzme bibliografske podatke
  iz COBISS-a ili druge konfigurisane bibliotecke baze:
  - naziv knjige,
  - autor ili autori,
  - izdavac,
  - godina izdanja,
  - UDK broj,
  - opis knjige.
- Uz COBISS, podrzani su i eksterni izvori kao dodatak/fallback: Google Books API i
  Open Library API. Detaljan redosled provere izvora definisan je u sekciji 4.5.1.
- Pre cuvanja zapisa bibliotekar mora da ima mogucnost pregleda i izmene preuzetih
  podataka.
- Ako kamera ili citac ne uspe da ocita oznaku, korisniku se prikazuje rucni unos
  ISBN-a. Korisnik moze rucno da unese i ostala bibliografska polja knjige.
- Rucni unos ima iste validacije i isti tok cuvanja kao podaci dobijeni skeniranjem.
- Ako spoljasnji izvor nije dostupan ili ne vrati podatke, unos knjige se nastavlja
  kroz rucni unos bez blokiranja procesa.

### Preporučeni workflow unosa knjige (ISBN)

1. **Unos:** Korisnik ukuca ili skenira bar-kod (ISBN) knjige.
2. **Fetch metadata:** Backend prvo proverava internu bazu (da ne ponavlja API
   poziv). Ako knjige nema, poziva Google Books API, a zatim Open Library API kao
   fallback. COBISS ili druga konfigurisana bibliotečka baza ostaje podržani izvor
   (sekcija 4.5) uz ove eksterne servise.
3. **Autofill forme:** Forma se popunjava dobijenim podacima (Autor, Naslov,
   Izdavač, Povez, Godina, Opis, Slika).
4. **Potvrda i korigovanje:** Korisnik proverava podatke i po potrebi izmeni/dopuni
   polja.
5. **Dodela signature/primerka:** Na sledećem koraku sistem automatski generiše
   ili korisnik ručno unosi Inventarni broj primerka i UDK signaturu za smeštaj na
   policu.

### Inventurna kontrola skeniranjem

- Bibliotekar moze da pokrene reviziju inventara iz web aplikacije i da kamerom ili
  bar-kod citacem skenira fizicke jedinice tokom obilaska polica.
- Sistem poredi skenirane bar-kodove sa fizickim jedinicama i knjigama koje pripadaju
  izabranoj biblioteci i reviziji.
- Rezultat se prikazuje u okviru postojeceg procesa revizije i obuhvata zateceno
  stanje, evidentirana ostecenja i nedostajuce fizicke jedinice/naslove prema
  postojecim poslovnim pravilima.
- Ne uvodi se novi, paralelni zapisnik ili proces. Postojeci revizioni tok,
  statusi, istorija i izvestaji iz Symfony aplikacije ostaju funkcionalni deo
  migracije.
- Tokom revizije mora biti moguc rucni unos bar-koda ili izbor fizicke jedinice kada
  kamera ne uspe da ocita oznaku.

## 4.6 Analitika za direktore

- Direktor biblioteke dobija izvestaj o opremljenosti obaveznom skolskom lektirom,
  sa procentom pokrivenosti fonda prema konfigurisanoj listi lektire.
- Izvestaj prikazuje koriscenje biblioteke po odeljenjima, ukljucujuci odeljenja sa
  najvecim i najmanjim brojem pozajmica u izabranom periodu.
- Graficki prikaz citanosti i lista najcitanijih naslova nadovezuju se na postojecu
  funkcionalnost `opterecenje` iz Symfony verzije. U migraciji se zadrzava postojece
  poslovno znacenje, uz mogucnost unapredjenja agregacija, filtera i vizuelizacije.
- Izvestaj treba da podrzi izbor perioda, biblioteke i skolske godine gde je to
  relevantno, kao i izvoz zvanicnog godisnjeg izvestaja za Skolski odbor i
  Ministarstvo prosvete.
- Pristup analitici i izvozima ogranicen je na direktore i druge role kojima je to
  eksplicitno dozvoljeno kroz policy pravila.

---

## 5. Role model u Laravelu (bez Spatie)

## 5.1 Preporuka
Za dati broj fiksnih rola usvaja se enum + striktno jedna rola po korisniku.

Role:
- SUPER_ADMIN
- LIBRARY_ADMIN
- LIBRARIAN
- USER

Implementacija:
- users.role tinyint unsigned
- PHP backed enum UserRole:int
- pomocne metode hasRole(), canManageLibrary(), itd.

Zasto enum (preporuka) umesto bitmap:
- Jednostavnije odrzavanje
- Jasniji SQL i indeksi
- Uskladjeno sa realnim poslovnim modelom gde korisnik uglavnom ima jedan nivo privilegije

## 5.2 Legacy mapiranje rola
Mapiranje iz Symfony/FOS:
- ROLE_SUPER_ADMIN -> SUPER_ADMIN
- ROLE_ADMIN -> LIBRARY_ADMIN
- ROLE_LIBRARAIN -> LIBRARIAN
- ROLE_ENDUSER/ROLE_USER -> USER

---

## 6. API specifikacija (high-level)

## 6.1 Auth
- POST /api/v1/auth/login
- POST /api/v1/auth/logout
- GET /api/v1/auth/me
- POST /api/v1/auth/password/reset-request
- POST /api/v1/auth/password/reset

## 6.2 Users
- GET /api/v1/users
- POST /api/v1/users
- GET /api/v1/users/{id}
- PUT /api/v1/users/{id}
- DELETE /api/v1/users/{id} (soft delete)
- POST /api/v1/users/{id}/activate
- POST /api/v1/users/tags/add
- POST /api/v1/users/tags/remove

## 6.3 Catalog i cirkulacija
- books, authors, categories, tags, physical-units (UI naziv: Fizicke jedinice)
- borrow, return, reservation
- user detail workflow (clanarina, zaduzenje, razduzenje, otkaz rezervacije)
- Web skeniranje ISBN/bar-koda sa kamerom ili bar-kod citacem i rucnim fallback unosom
- Preuzimanje i potvrda bibliografskih podataka iz COBISS-a ili konfigurisane baze
- Inventurna revizija sa skeniranjem fizickih jedinica kroz postojeci revizioni tok
- Skeniranje QR kodova, ISBN-a i barkodova u PWA i Capacitor izdanju
- Cuvanje skeniranih slika u galeriju kroz Capacitor, uz browser download fallback

## 6.4 Stats i report
- /stats/borrows
- /stats/returns
- /stats/category
- /stats/revision
- /stats/reading-load (migracija i moguce unapredjenje postojeceg `opterecenje` toka)
- /reports/reading-coverage
- /reports/annual-library
- /reports/taken-books

## 6.5 CMS
- news, recomendations, pages, banner

## 6.6 Import i export
- Import knjiga iz Excel formata
- Import korisnika iz Excel formata
- Import korisnika mora podrzati class podatke
- Ako class iz uvoza ne postoji, sistem treba da ga kreira u aktivnoj skolskoj godini (uz validaciju biblioteke i razreda 1-8)
- PDF eksporti moraju biti funkcionalno 1:1 ekvivalent legacy aplikaciji (raspored podataka, kljucni prikazi i izvestaji)

---

## 7. Plan migracije podataka (legacy baza -> PostgreSQL)

## 7.1 Koraci
1. Snapshot postojece baze
2. Kreiranje Laravel PostgreSQL schema + migracija
3. ETL skripte za master podatke (library, category, author, book)
4. ETL za korisnike i role mapiranje
5. ETL za transakcione podatke (book_copy, borrow, reservation, history, membership, transaction)
6. ETL za tag pivot i central katalog
7. Verifikacija integriteta i brojeva (legacy vs PostgreSQL)
8. Pilot rollout po jednoj biblioteci

## 7.2 Kriticne tacke
- Mapiranje razlika tipova i SQL semantike iz legacy baze ka PostgreSQL
- Roles konverzija iz array u enum
- Username prefiks obrisan... logika (legacy soft-delete)
- Tacnost available/total counters na knjigama
- Uskladjivanje deleted/locked semantike
- Ispravno inicijalno punjenje i dalja konzistentnost `student_class` pivot relacija i `active` statusa
- Pravila za automatsko kreiranje class-ova tokom importa korisnika u aktivnoj skolskoj godini

---

## 8. Nefunkcionalni zahtevi

- Bezbednost: CSRF, rate limit, audit log
- Performanse: paginacija na svim listama, indeksiranje FK i search kolona
- Kamera se koristi iz browsera uz eksplicitnu dozvolu korisnika; aplikacija mora
  obezbediti rucni unos kada kamera ili citac nisu dostupni ili je ocitavanje neuspesno.
- Integracija sa spoljasnjim biblioteckim izvorom ne sme blokirati rucni unos ako je
  izvor nedostupan ili ne vrati rezultat.
- PWA izdanje mora imati validan web manifest, service worker, responsive prikaz i
  standalone instalacioni tok na podrzanim Android browserima.
- Capacitor native izdanje mora obraditi dozvole za kameru i galeriju, kao i greske
  native API-ja bez prekida osnovnog web toka.
- Observability: structured log, exception tracking
- Testovi:
  - Feature testovi za API
  - Unit testovi za Service sloj
  - Integracioni testovi za migraciju podataka

---

## 9. Prihvatni kriterijumi

- Svi postojeci moduli imaju API ekvivalente
- React pokriva admin i public tokove
- React frontend koristi shadcn/ui, Tailwind CSS i Lucide React kao jedinstveni UI standard
- Core frontend slojevi koriste TanStack Query, React Hook Form i Zod prema definisanim
  pravilima za API state, forme i validaciju
- Frontend koristi centralni Axios/Fetch API klijent sa standardizovanom autentikacijom,
  zaglavljima i obradom gresaka
- Korisnicke role rade po novom modelu bez Spatie
- Korisnik ima striktno jednu rolu (enum), bez multi-role kombinacija
- Postoji CRUD za class-ove i dodela razreda kroz enum polje u class-u
- Razred je implementiran kao enum 1-8 u tabeli class-ova, uz rimski prikaz u UI-u
- Skolska godina je sistemski entitet i vezana je za class kroz FK (`school_year_id`)
- Ucenici su povezani sa class-ovima
- Istorija promene class-a korisnika se cuva kroz student_class pivot tabelu i dostupna je za pregled
- U svakom trenutku postoji tacno jedna aktivna class relacija po korisniku
- Import modul podrzava knjige i korisnike (ukljucujuci class podatke)
- Import korisnika moze kreirati nedostajuci class u aktivnoj skolskoj godini
- PDF eksporti su 1:1 funkcionalno uskladjeni sa legacy sistemom
- ISBN/bar-kod se moze ocitati kamerom ili bar-kod citacem iz web aplikacije
- QR kodovi, ISBN-i i barkodovi mogu se skenirati u PWA izdanju, a isti React tok radi
  i u Capacitor izdanju
- Capacitor izdanje moze da sacuva skeniranu sliku u sistemsku galeriju telefona
- Browser/PWA izdanje nudi preuzimanje slike kada direktno cuvanje u galeriju nije dostupno
- PWA moze da se instalira na Android kao standalone web aplikacija
- Nakon ocitavanja korisnik moze da pregleda i izmeni preuzete bibliografske podatke
- Kod neuspesnog ocitavanja postoji rucni unos ISBN-a i ostalih polja knjige
- Inventurna revizija koristi postojeci revizioni proces, bez uvodjenja paralelnog zapisnika
- Postojece `opterecenje` statistike su migrirane, a graficki prikaz citanosti,
  najcitaniji naslovi i izvestaj o opremljenosti lektirom dostupni su prema ovlascenju
- Godisnji izvestaji za Skolski odbor i Ministarstvo prosvete mogu se generisati iz sistema
- Migracija podataka prolazi bez gubitka kljucnih relacija
- Statistike i izvestaji vracaju konzistentne rezultate

---

## 10. Predlog implementacionih faza

- Faza 1: Frontend osnova (React + Vite + TypeScript, Router, Tailwind CSS, shadcn/ui,
  Lucide React, centralni API klijent), zatim Auth, Users, Libraries, Roles
- Faza 2: Catalog (books/authors/categories/tags)
- Faza 3: Fizicke jedinice + Borrow/Return/Reservation + User detail workflow
- Faza 4: Stats/Reports + CMS + Import, ukljucujuci reviziju inventara, web skeniranje,
  PWA podrsku, izvestaj o opremljenosti lektirom i unapredjenje `opterecenje` analitike
- Faza 5: Razred/Class/Ucenik + finalna migracija
- Faza 6: Capacitor Android/iOS wrapper, testiranje galerije i dozvola, UAT, cutover,
  hypercare i priprema Google Play izdanja

---

## 11. Tehnicke napomene iz analize

- Legacy koristi FOSUserBundle i Doctrine array roles
- Legacy role naziv sadrzi typo ROLE_LIBRARAIN
- Legacy kod ima mix ORM i native SQL; kod migracije to razdvojiti na Query services
- Legacy kontroleri su debeli; u Laravelu logiku prebaciti u servise

---

## 12. Otvorena pitanja (za finalni backlog)

Zakljucene odluke:
- Korisnik ima striktno jednu rolu (enum model).
- Korisnik moze menjati class kroz vreme, a istorija se cuva kroz student_class pivot relacije (active true/false).
- Import modul obavezno ostaje i pokriva uvoz knjiga i korisnika.
- Import korisnika podrzava class i automatsko kreiranje class-ova u aktivnoj skolskoj godini.
- Mobilno izdanje koristi isti React kod kroz Capacitor kada je potreban pristup
  galeriji ili drugim native API-jima.
- TWA/Bubblewrap ostaje moguca alternativa samo ako se ukloni zahtev za cuvanje slika
  u sistemskoj galeriji.
- PDF eksporti su obavezni i moraju biti 1:1 funkcionalno uskladjeni sa legacy aplikacijom.

---

## 13. Implementacioni dodaci — auth, role i dashboard (usvojeno 03.09.2026.)

Odluke primenjene u prvoj fazi (login/forgot/reset, role model, dashboard shell):

- **Javna registracija se ne implementira.** Nalog korisniku otvara superadmin ili
  admin biblioteke iz dashboard forme (Users), u okviru biblioteke. `POST /register`
  je uklonjen iz API-ja (`routes/auth.php`). Na javnom portalu uklonjen je CTA
  "Kreiraj nalog"; ostaje "Prijava".
- **Primaran jezik UI-ja je srpski na ćirilici** (`sr-Cyrl` default), sa opcijama
  latinica (`sr-Latn`) i engleski (`en`). React koristi i18next; jezik se pamti u
  localStorage i šalje kroz `X-Locale` header ka API-ju (server: `SetLocale`
  middleware + `lang/{sr-Cyrl,sr-Latn,en}`).
- **Role = Postgres native enum tip** `user_role` (`CREATE TYPE`), vrednosti:
  `superadmin`, `library_admin`, `librarian`, `user` (mapiranje iz §5.2).
  PHP `enum UserRole: string` koristi `EnumToArray` trait (obrazac iz projekta
  "portfolio") sa `toArray()`, `toArrayWithValue()` i lokalizovanim `getLabel()`.
- **Biblioteka-korisnik je many-to-many** preko pivot tabele `library_user`
  (jedan nalog može koristiti više biblioteka). Nasleđeni `library_id` na
  korisniku se ne uvodi. Model "mesto" (place) pripada regiji; biblioteka pripada
  mestu (bez dupliranja region/city na biblioteci kao u legacy-ju).
- **`users` tabela** je proširena: role (enum), `username`, `first_name`,
  `last_name`, `jmbg`, `address`, `city`, `post_code`, `bar_code` (auto-generisano
  kroz API, 13 cifara).
- **Dashboard (React)**: `/login`, `/forgot-password`, `/reset-password`
  (query: email+token), a iza autentikacije `/` (dashboard početna, prazan widget
  prostor — widgeti po tipu naloga dolaze kasnije), `/users`, `/libraries`.
  Sidebar za početak: Users i Libraries (vidljive superadminu); role-based meni
  dolazi sa module-ima. Password reset link iz email-a vodi na React rutu.
- **Login API ostaje session-based (Sanctum)**; `/me` vraća korisnika kroz
  `UserResource` (sa `role`). Auth ekrani su rađeni u shadcn/ui stilu sa blagim
  žutim akcentom (sidebar/nav), bez menjanja fontova.
