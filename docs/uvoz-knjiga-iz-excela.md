# Asinhroni uvoz knjižnog fonda iz Excel-a (`.xlsx`)

## Koncept, zahtevi, tehnički plan i faze implementacije

---

## 1. Svrha dokumenta

Ovaj dokument objedinjuje:

- početne zahteve za asinhroni uvoz knjižnog fonda iz Excel-a,
- dopunjene odluke donete nakon analize,
- usklađivanje sa postojećim Laravel/React projektom,
- predloženu strukturu baze, servisa, job-ova, API-ja i UI-ja,
- faze implementacije i kriterijume prihvatanja.

Cilj je da se jedan dokument može direktno koristiti kao ulaz za implementaciju.

**Status:** implementirane su faze 1–5: backend infrastruktura i obrada, HTTP API
za pregled/pokretanje/ispravke/retry i React UI za upload, preview, praćenje i
ponovni pokušaj. Preostaje faza 6 — završni testovi i provera produkcionog queue
okruženja. Ulazni sloj je izdvojen u generički `ExcelImportReader` i ugovor
`ExcelImportMapper`; knjige koriste `BookImportMapper`.

---

## 2. Cilj i osnovni principi

Potrebno je omogućiti uvoz knjižnog fonda iz `.xlsx` fajla sa oko 10.000 redova.

Osnovni principi:

- Uvoz se izvršava **asinhrono preko queue mehanizma**.
- Fajl se čita **streaming** pristupom, red po red, bez učitavanja celog workbook-a u memoriju.
- Ne koristi se jedna globalna transakcija za ceo uvoz.
- Svaki red iz Excel-a se prati pojedinačno:
  - broj reda u fajlu,
  - originalni podaci iz reda,
  - status,
  - razlog greške,
  - broj pokušaja,
  - povezani `book_id` i `book_copy_id`.
- Standardni režim rešavanja knjige koristi ISBN:
  1. provera lokalne baze,
  2. eksterni ISBN/COBISS lookup,
  3. ako ne uspe — red ostaje `failed`.
- Direktni Excel režim dostupan je odmah u učitanom previewu, ne samo kao fallback neuspešnih redova. Korisnik bira redove i pokreće asinhroni uvoz bez eksternih ISBN provera.
- Jedini dozvoljen eksterni izvor za ovaj import je NBS/COBISS putem postojećeg scraping-a. OpenLibrary i Google Books se ne pozivaju niti se njihovi ranije keširani podaci koriste.
- Za bibliografska polja važi COBISS → Excel: validna neprazna COBISS vrednost ima prednost, a ako je nema koristi se vrednost iz reda. Lokalni podaci primerka imaju poseban Excel prioritet prema odeljku 11.2.
- Tok je upload → asinhrona priprema → preview → izbor redova i režima → pokretanje asinhronog uvoza. Pre korisničkog pokretanja nema COBISS poziva ni kreiranja naslova/kopija, rezervacije brojeva ili pomeranja inventarne sekvence.
- Automatski inventarni broj dodeljuje se tek u transakciji kreiranja kopije. Ne garantuje se numeracija po Excel redosledu, naslovu ili prethodnom neuspešnom pokušaju. Ako kolona postoji, koristi se njen validan broj.
- Za pojedinačne izmene koriste se kratke transakcije, nikada jedna transakcija za ceo import.
- Jedan upload = jedan zapis o uvozu, sa detaljima po redovima.

---

## 3. Zahtevi

### 3.1 Funkcionalni zahtevi

#### Upload i pokretanje

- Korisnik bira `.xlsx` fajl i pokreće pripremu previewa.
- Uvoz je vezan za aktivnu biblioteku.
- Fajl se validira i čuva privatno.
- Kreira se zapis `book_imports`.
- Job učitava redove bez dodele brojeva. U pripremljenom previewu korisnik bira redove, režim i „Pokreni uvoz“; obrada oba režima ide kroz job-ove.
- Korisnik može da zatvori stranicu; obrada se nastavlja.

#### Praćenje redova

Za svaki red se čuva:

- broj reda u Excel-u,
- `raw_payload` sa podacima iz reda,
- odvojeni konačno korišćeni podaci i izvor svake vrednosti, bez prepisivanja Excel snimka,
- status,
- razlog neuspeha,
- broj pokušaja,
- `book_id` kada se naslov reši,
- `book_copy_id` kada se kopija kreira,
- vreme obrade.

#### ISBN i COBISS

- Prvo se proverava lokalna baza.
- ISBN se normalizuje i proverava u oba oblika, ISBN-10 i ISBN-13.
- Ako nema lokalno, koristi se isključivo NBS/COBISS scraping.
- OpenLibrary i Google Books nisu merodavni za ovaj import zbog mogućih razlika u jeziku, pismu i katalogizaciji. Ne koriste se ni kao dopuna za pojedinačno prazno COBISS polje: dopuna dolazi iz Excel-a.
- Eksterni pozivi se izvršavaju sa kontrolisanim brojem pokušaja i pauzom.
- Ako lookup ne uspe, red ostaje `failed` sa jasnim razlogom.
- Jedan neuspešan red ne prekida ceo uvoz.

#### Inventarni broj

- Pravilo ručnog/automatskog inventarnog broja vezano je za biblioteku.
- Parser detektuje kolonu inventarnog broja po nazivu zaglavlja.
- Ako kolona postoji, koristi se vrednost iz Excel-a.
- Ako kolona ne postoji, koristi se postojeći generator inventarnog broja.
- Automatski broj određuje se tek pri kreiranju kopije, kroz postojeći generator i zaključavanje sekvence. Preview prikazuje „Dodeljuje se pri uvozu“, ne unapred izračunat broj.
- Odsustvo kolone nije fatalna greška, ali UI mora da prikaže upozorenje.
- Proverava se format i jedinstvenost u bazi i u okviru istog uvoza.
- Konačna provera jedinstvenosti radi se u transakciji upisa, uključujući arhivirane kopije. Sačuvan broj u Excel redu nije rezervacija niti garancija da je i dalje slobodan.

#### Direktni uvoz bez ISBN provere

- Opcija u učitanom previewu: **„Import bez provere ISBN broja na eksternim servisima (COBISS)“**.
- Dostupna je za izabrane još neobrađene redove, bez prethodnog pokušaja COBISS obrade.
- Uzimaju se efektivni Excel podaci (original sa eventualnim sačuvanim ispravkama), bez COBISS HTTP zahteva, metapodataka i keša; Google Books/OpenLibrary se takođe ne koriste.
- Preskače se bibliografska provera preko ISBN-a, ne validacija obaveznih podataka, tipova, jedinstvenosti, dodele broja i autorizacije.
- Naslov je obavezan. Povezivanje sa postojećim naslovom po naslovu/autorima ostaje lokalna deduplikacija; ne obogaćuje red podacima drugih kopija.
- Koristi se isti mehanizam job-ova i kratka transakcija po redu kao u fallback-u. Nema sinhronog masovnog upisa ni globalne transakcije.

#### Fallback uvoz neuspešnih redova

- Korisnik vidi listu neuspešnih redova.
- Može da filtrira po statusu.
- Može da označi pojedinačne redove ili sve neuspešne redove.
- Pokreće ponovni uvoz samo za izabrane redove.
- Pre ponovnog pokušaja može da ispravi vrednost koja je izazvala grešku u samom previewu, prema odeljku 9.6.
- Fallback:
  - preskače COBISS,
  - koristi naslov, autore, kategoriju i ostale podatke iz Excel-a,
  - pronalazi ili kreira kategoriju,
  - pronalazi ili kreira naslov,
  - kreira kopiju,
  - koristi validan broj iz Excel-a ili generiše broj pri kreiranju kopije ako kolona ne postoji.
- Svaki red se obrađuje u sopstvenoj kratkoj transakciji.
- Ako fallback ne uspe, red ponovo dobija `failed` i novi razlog.

#### Praćenje uvoza u UI-ju

- Lista svih uvoza.
- Detalj pojedinačnog uvoza.
- Filter redova po statusu.
- Pretraga po broju reda, ISBN-u, naslovu i inventarnom broju.
- Prikaz razloga greške.
- Selekcija redova i „izaberi sve“.
- Pokretanje fallback uvoza sa jasnim upozorenjem da se COBISS ne koristi.
- Preview prikazuje sve 23 kolone ulaznog reda, ispravke i konačne podatke/poreklo. Broj iz Excel-a prikazuje se kao ulaz, a automatski dodeljeni broj tek posle uspešnog upisa kopije. Izbor režima i pokretanje dešavaju se u previewu.

### 3.2 Nefunkcionalni zahtevi

- Podrška za oko 10.000 redova bez prekida i bez prekomernog trošenja memorije.
- Streaming čitanje fajla.
- Batch obrada u queue-ju.
- Mala potrošnja memorije po job-u.
- Kratke DB transakcije.
- Idempotentnost i mogućnost nastavka nakon prekida.
- Zaštita od duplih uvoza i duplih inventarnih brojeva.
- Privatno čuvanje fajla.
- Autorizacija po biblioteci.
- Lokalizacija poruka i statusa.
- Logovanje toka obrade.
- Mogućnost recovery-ja zaglavljenih uvoza.
- Poslovni režimi redovnog ručnog/automatskog unosa ostaju. Import koristi postojeću per-library sekvencu, provere kolizija i DB unique ograničenja, bez posebnog sistema rezervacija.

### 3.3 Zaključene odluke

Nakon analize i dopune, zaključene su sledeće odluke:

1. **Ako Excel sadrži kolonu inventarnog broja, brojevi iz fajla se koriste uvek**, čak i kada biblioteka ima automatski režim. Sekvenca se nikada ne smanjuje: pri uspešnom upisu uzima se maksimum trenutne sekvence, svih već korišćenih brojeva (uključujući arhivu) i broja upravo kreirane kopije. Neuspešni i neizabrani redovi ne pomeraju sekvencu.
2. **Prazna ćelija inventarnog broja kada kolona postoji znači grešku reda** (`missing_inventory_number`). Ne generiše se automatski broj.
3. **Jedini eksterni izvor standardnog uvoza je NBS/COBISS scraping**. OpenLibrary i Google Books nisu dozvoljeni ni kroz zajednički keš. Postojeći lookup van ovog importa ne menja izbor provajdera.
4. **Dozvoljen je samo jedan aktivan uvoz po biblioteci, uz DB garanciju.** PostgreSQL parcijalni unique indeks obuhvata `pending`, `ready` i `processing`, uključujući preview koji čeka korisnika i aktiviranje fallback-a. Konflikt vraća `409 Conflict`; obrada koristi DB lease i proveru vlasništva.
5. **ISBN rezultat razlikuje neuspešnu pretragu od odsustva rezultata.** Čuvaju se strukturisani ishodi provajdera; greška servisa ne sme biti predstavljena kao „ISBN nije pronađen“.
6. **Trenutni napredak i kumulativni pokušaji imaju odvojene kolone.** `processed_rows` broji trenutno završene redove, a `completed_attempts` sve završene pokušaje, uključujući prethodne standardne i fallback obrade.
7. **„Izaberi sve“ obuhvata ceo trenutni filtrirani rezultat, preko svih stranica.** Ručni izbor šalje ID-jeve, a izbor svih šalje filtere. Podrazumevana paginacija redova je 200.
8. **COBISS → Excel važi po bibliografskom polju**, ne po celom redu; svi izuzeci za lokalne podatke primerka navedeni su u matrici 11.2.
9. **Originalno Excel stanje ostaje sačuvano u `book_import_rows`**, odvojeno od korisničkih ispravki i konačnih podataka. Priprema završava statusom `ready`; korisnik bira redove i režim, pa pokreće uvoz iz previewa.
10. **Bez odgovarajućeg COBISS rezultata nema automatskog Excel fallback-a**. `missing_isbn`, `invalid_isbn`, `isbn_not_found` i `lookup_failed` ostaju jasno označeni, a korisnik izričito bira „Uvezi iz Excel podataka“.
11. **Datum dodavanja ne unosi se iz Excel-a niti COBISS-a.** `book_copies.date_add` postavlja se na datum uspešnog kreiranja konkretne kopije u aplikacionoj vremenskoj zoni, uključujući naknadni Excel fallback; nije datum upload-a.
12. **Postoje samo dva režima: `isbn_check` i `excel_direct`.** Direktni Excel import dostupan je pre prvog COBISS pokušaja i pri retry-ju. „Excel fallback“ znači ponovni pokušaj neuspešnih redova kroz `excel_direct`, ne treći režim.
13. **Nema rezervacija inventarnih brojeva ni barkodova u previewu.** Dodela i upis nastaju sa kopijom; neuspešan automatski pokušaj ne zadržava broj. Retry dobija tada sledeći broj, dok se već uspešno uvezen red ne obrađuje ponovo.
14. **Ispravka neuspešnog reda radi se u previewu**, bez menjanja originalnog Excel snimka. Broj iz Excel-a može se ispraviti pre upisa; konačna provera slobodnog broja ponavlja se pri kreiranju kopije.

---

## 4. Postojeće stanje projekta

### 4.1 Tehnološki okvir

- Laravel 13, PHP 8.3+ (Docker koristi PHP 8.4).
- PostgreSQL.
- React + Vite + TypeScript.
- React Query za server state.
- Session/cookie autentifikacija.
- Queue konekcija: `database`.
- Postojeći async obrasci: generisanje PDF inventarne knjige i PDF bar-kodova.

### 4.2 Postojeći modeli i servisi koje treba iskoristiti

| Postojeće | Namena u uvozu |
|---|---|
| `Book` | Naslovi. Nema ISBN kolonu. |
| `BookCopy` | Fizičke jedinice. ISBN, inventarni broj, barkod i bibliografski podaci su ovde. |
| `Library` | `inv_number_auto` režim. |
| `BookInventorySequence` | Per-library sekvenca inventarnih brojeva. |
| `InventoryNumberService` | Normalizacija, generator, provera jedinstvenosti, barkod. |
| `InventoryReconciliationService` | Postojeća pravila za ručni unos; uvoz ih namerno zaobilazi. |
| `BookCopyService` | Ručni/auto unos kopija; ne koristi se direktno za uvoz. |
| `BookService` | Kreiranje naslova i povezivanje autora. |
| `AuthorResolverService` | Pronalazak/kreiranje autora uz transliteraciju. |
| `CategoryResolverService` | Pronalazak/kreiranje kategorije po nazivu. |
| `CobissCategoryMapper` | Mapiranje COBISS kategorije/UDK na drvo kategorija. |
| `BookMatcher` | Detekcija duplikata naslova i pronalazak postojećeg naslova. |
| `IsbnNormalizer` | Normalizacija i validacija ISBN-10/13. |
| `IsbnMetadataService` | Postojeći zajednički lanac; import mora koristiti eksplicitno COBISS-only ulaz i odvojen keš, bez menjanja ostalih tokova. |
| `NbsCatalogProvider` | NBS/COBISS+ scraping. |
| `IsbnLookupService` | Postojeći obrazac lokalne provere; njegov neograničeni eksterni lanac ne sme se nekritički pozvati iz importa. |
| `ActiveLibraryService` | Aktivna biblioteka i scope. |
| `AuthorizationService` | Policy permisije za frontend i resurse. |
| `GenerateInventoryBookPdf` | Primer async job-a, statusa i privatnog fajla. |
| `GenerateBarcodePrintPdf` | Primer async job-a i polling UI-ja. |

### 4.3 Važni nalazi

- `books` tabela nema ISBN. Lokalna provera mora prvo preko `book_copies.isbn`, pa preko `book_id` do naslova.
- U inventarnoj knjizi se `book_copies.udk` prikazuje kao „Signatura“. Zato se Excel kolona `signatura` mapira na `udk`.
- Postojeći `BookCopyService` u auto režimu ne dozvoljava ručni inventarni broj i primenjuje pravilo „bez preskakanja“. Bulk uvoz mora da ima odvojen servis.
- Unique ograničenja `book_copies` su:
  - `(library_id, order_number)`,
  - `(library_id, barcode)`,
  - `(library_id, seq_number)`.
- Unique važi i za soft-deleted kopije, pa provera duplikata mora uključiti arhivirane kopije.
- Projekat trenutno nema Excel paket.
- Postojeći `api.upload()` u frontend-u hardkoduje polje `image`, pa je potreban `api.uploadFile()`.
- Postojeći `BookMetadata` nema odvojena polja za sekundarnu kategoriju, broj izdanja, tom ni povez. Dok COBISS parser ne izloži pouzdano mapiranu vrednost, ova polja se smatraju nedostajućim i uzimaju iz Excel-a; ne izvode se nagađanjem iz drugih polja.
- `InventoryNumberService::next()` pod DB zaključavanjem uzima `last_number + 1`, proverava koliziju i pomera sekvencu; sam ne kreira kopiju. `nextValue()` samo čita sledeću vrednost, ali može inicijalizovati zapis sekvence, pa se ne poziva radi dodele u previewu.
- `InventoryNumberService::barcodeFor()` normalizuje inventarni broj i poziva `BarCode::generateFromBaseNumber()`: dopuna vodećim nulama do 12 cifara + EAN-13 kontrolna cifra. To je računanje stringa, bez DB upisa. Postojeći `BookCopyService::persist()` upisuje broj i barkod tek kroz `BookCopy::create()`.
- Barkod kopije ne izvodi se iz opcionog `seq_number`. Posebna `bar_code_seq` sekvenca u `BarCode::generate()` koristi se za korisničke kartice, ne za kopije knjiga.

---

## 5. Izbor Excel paketa

Dodati:

```bash
composer require openspout/openspout:^4.33
```

Zašto OpenSpout:

- stvarno streaming čitanje redova,
- mala memorija,
- podrška za XLSX,
- v4.33 podržava PHP 8.3–8.6.

Ne koristiti OpenSpout v5 za sada, jer zahteva PHP 8.4+ i podigao bi minimalnu verziju projekta.

Ne koristiti `spatie/simple-excel`, jer se oslanja na PhpSpreadsheet i učitava ceo workbook.

---

## 6. Arhitektura rešenja

### 6.1 Visok nivo toka

```text
Upload .xlsx
   ↓
Sinhrona provera zaglavlja
   ↓
book_imports (pending)
   ↓
PrepareBookImportJob
   ├─ streaming čitanje redova
   ├─ upis book_import_rows u chunk-ovima
   ├─ detekcija inventarne kolone
   ├─ provera ulaznih podataka, bez dodele brojeva
   └─ status = ready
   ↓
Preview: izbor redova i režima → Pokreni uvoz
   ↓
ProcessBookImportJob (isbn_check ili excel_direct)
   ├─ batch ~20 redova
   ├─ po jedan red = jedna kratka transakcija
   ├─ isbn_check: ISBN → lokalna baza → COBISS
   ├─ excel_direct: samo Excel, bez eksternih poziva
   ├─ kreiranje/reuse naslova
   ├─ dodela/provera inventarnog broja i kreiranje kopije sa barkodom
   ├─ osvežavanje brojača
   └─ self-dispatch dok ima redova
   ↓
ready (ostali neizabrani redovi) / completed / completed_with_errors
   ↓
Korisnik filtrira failed redove i ispravlja podatke
   ↓
Retry izabranih redova
   ↓
ProcessBookImportJob (isbn_check ili excel_direct)
   ├─ isbn_check ponavlja proveru; excel_direct preskače COBISS
   ├─ efektivni podaci iz previewa
   ├─ kreiranje/reuse naslova
   ├─ broj iz Excel-a ili novi automatski broj pri kreiranju kopije
   └─ novi status reda
```

### 6.2 Zašto chunked self-chaining job

Ne koristiti:

- jedan job po redu — 10.000 job-ova je previše,
- jedan dug job za ceo uvoz — veliki timeout i težak oporavak.

Koristiti:

- `PrepareBookImportJob` za pripremu,
- `ProcessBookImportJob` koji obrađuje batch i ponovo sebe dispečuje dok ima posla,
- DB lease za vlasništvo nad obradom aktivnog importa biblioteke,
- kratke transakcije po redu,
- čuvanje napretka u bazi.

Time se dobija:

- nastavak nakon prekida,
- kontrolisan rate-limit,
- mali timeout po job-u,
- stabilan rad sa 10.000 redova.

### 6.3 Queue

- Poseban queue: `imports`.
- Jedan worker za `imports`, da bi se poštovao COBISS crawl delay.
- Job timeout: ~300 sekundi.
- `tries=3`.
- DB lease zajednički za pripremu i sve režime obrade, prema odeljku 15.3; cache lock nije osnova ispravnosti.
- Pauza ~1 sekunda između eksternih ISBN lookupa.
- `DB_QUEUE_RETRY_AFTER` mora biti veći od timeout-a, npr. 900 sekundi.
- Job se dispečuje posle commit-a transakcije.

### 6.4 Zajednički Excel ulaz i domenski mapperi

- `ExcelImportReader` u `app/Services/ExcelImports/` otvara i zatvara XLSX,
  streamuje prvi list i čuva fizičke brojeve redova. Ne poznaje modele, tabelu
  importa, inventarne brojeve, ISBN, queue ili `book_imports` konfiguraciju.
- `ExcelImportMapper<TRow>` ima dve metode: `mapHeader()` prepoznaje zaglavlje,
  a `mapRow()` vraća konkretan tip reda ili `null` za red koji se preskače.
  Reader ne određuje obavezna polja ni pravila validacije pojedinačnog domena.
- `ExcelImportHeaderMapDTO` sadrži naziv lista, broj header reda, originalna
  zaglavlja i mapu kolona. `hasColumn()` nije vezan za inventarnu kolonu.
- `ExcelCellNormalizer` zajednički normalizuje tekst, razmake i numeričke
  vrednosti bez naučne notacije; koriste ga reader i mapperi.
- `BookImportMapper` implementira ugovor za `BookImportRowDataDTO` i koristi
  postojeće knjiške header/payload mappere. Aliasi, ISBN/naslov uslov, validacija
  kolona i knjiški kodovi grešaka ostaju izvan generičkog reader-a.
- Pipeline prosleđuje limit pretrage zaglavlja. `header()` vraća `null` kada
  zaglavlje nije pronađeno u tom limitu; book pipeline to pretvara u postojeći
  `missing_columns`. OpenSpout greške čitanja pretvara u `file_unreadable`,
  dok se neočekivane greške mappera ne prikrivaju. API statusi i poruke ostaju isti.
- Priprema i upload sada koriste `ExcelImportReader` sa `BookImportMapper`.
  DB lease, chunk upis, limiti redova i brojači ostaju odgovornost knjiškog pipeline-a.

Naredni korak je faza 3 na ovoj osnovi. Obrada koristi već pripremljene preview
redove iz baze, bez ponovnog parsiranja originalnog XLSX fajla.
Kada dođe uvoz učenika, dodaju se `StudentImportMapper` i studentski pipeline
(njegovi DTO-i, validacija i upis), bez novog XLSX reader-a. Studentski import
i faza 3 nisu implementirani u ovom refaktoru.

---

## 7. Model podataka

### 7.1 Tabela `book_imports`

| Kolona | Tip | Napomena |
|---|---|---|
| `id` | bigint | PK |
| `library_id` | FK | `libraries`, cascade |
| `user_id` | FK nullable | ko je pokrenuo, nullOnDelete |
| `original_filename` | string | originalni naziv fajla |
| `file_path` | string | putanja na privatnom `imports` disku |
| `file_size` | unsigned bigint | veličina fajla |
| `sheet_name` | string nullable | prvi/obrađeni list |
| `status` | string(30) | enum |
| `has_inventory_column` | boolean | detektovana kolona |
| `headers` | jsonb nullable | originalna zaglavlja |
| `column_map` | jsonb nullable | mapiranje kolona |
| `total_rows` | unsigned int | ukupno redova |
| `processed_rows` | unsigned int | trenutno završeni redovi: `imported` + `failed` |
| `imported_rows` | unsigned int | uspešno |
| `failed_rows` | unsigned int | neuspešno |
| `completed_attempts` | unsigned bigint | kumulativni broj završenih pokušaja obrade svih redova |
| `processing_token` | uuid nullable | vlasnik trenutnog izvršavanja job-a |
| `lease_expires_at` | timestamp nullable | istek DB lease-a; osnova za recovery |
| `failure_code` | string(40) nullable | fatalna greška importa |
| `failure_message` | text nullable | detalj fatalne greške |
| `started_at` | timestamp nullable | početak pripreme |
| `finished_at` | timestamp nullable | kraj |
| `created_at`, `updated_at` | timestamps | |

Indeksi:

- `(library_id, status)`,
- `(library_id, created_at)`,
- unique `(library_id) WHERE status IN ('pending', 'ready', 'processing')` — PostgreSQL parcijalni indeks,
- `lease_expires_at` za recovery aktivnih uvoza.

Parcijalni unique indeks je konačna zaštita i za upload i za ponovno aktiviranje završenog importa. Prethodna aplikativna provera služi samo za poruku korisniku.

### 7.2 Tabela `book_import_rows`

| Kolona | Tip | Napomena |
|---|---|---|
| `id` | bigint | PK |
| `book_import_id` | FK | cascade |
| `row_number` | unsigned int | stvarni red u Excel-u |
| `raw_payload` | jsonb | sačuvani Excel podaci pre spajanja sa COBISS-om; ne prepisuju se lookup-om ili retry-jem |
| `input_overrides` | jsonb nullable | samo korisničke ispravke previewa, odvojene od originalnog reda |
| `input_version` | unsigned int | početno 1; optimističko zaključavanje ispravki, raste pri uspešnoj izmeni |
| `resolved_payload` | jsonb nullable | konačno korišćene vrednosti poslednje obrade; `null` pre razrešavanja |
| `field_sources` | jsonb nullable | poreklo: `excel`, `preview_edit`, `cobiss`, `existing_book`, `generated`, `default` |
| `isbn` | string(32) nullable | denormalizovano za pretragu |
| `title` | string(255) nullable | denormalizovano za pretragu |
| `inventory_number` | string(32) nullable | denormalizovano za prikaz/proveru |
| `status` | string(20) | enum |
| `failure_code` | string(40) nullable | razlog |
| `failure_message` | text nullable | detalj |
| `lookup_diagnostics` | jsonb nullable | strukturisani ishod COBISS lookup-a; bez dijagnostike nedozvoljenih provajdera |
| `attempts` | unsigned int | kumulativni broj započetih pokušaja, uključujući prekinute |
| `completed_attempts` | unsigned int | kumulativni broj pokušaja završenih kao `imported` ili `failed` |
| `attempt_mode` | string(20) nullable | `isbn_check` ili `excel_direct`, i za prvi uvoz i za retry |
| `processing_requested_at` | timestamp nullable | trajno evidentiran izbor reda za konkretno pokretanje; nije svaki `pending` red izabran |
| `book_id` | FK nullable | nullOnDelete |
| `book_copy_id` | FK nullable | nullOnDelete |
| `processed_at` | timestamp nullable | |
| `created_at`, `updated_at` | timestamps | |

Ograničenja:

- unique `(book_import_id, row_number)`,
- indeks `(book_import_id, status, row_number)`,
- indeks `(book_import_id, isbn)`.

### 7.3 Enumeracije

Po konvenciji iz `AGENTS.md`, sve nove enumeracije imaju sufiks `Enum`.

Sve četiri enumeracije importa koriste postojeći trait `EnumToArray` i `getLabel()`
sa prevodima iz `book_imports.php` za `en`, `sr-Latn` i `sr-Cyrl`.

#### `BookImportStatusEnum`

- `Pending = 'pending'`
- `Ready = 'ready'` — preview spreman, čeka izbor ili pokretanje preostalih redova
- `Processing = 'processing'`
- `Completed = 'completed'`
- `CompletedWithErrors = 'completed_with_errors'`
- `Failed = 'failed'`

#### `BookImportRowStatusEnum`

- `Pending = 'pending'`
- `Processing = 'processing'`
- `Imported = 'imported'`
- `Failed = 'failed'`

#### `BookImportModeEnum`

- `IsbnCheck = 'isbn_check'` — ISBN provera u lokalnoj bazi, zatim COBISS ako nema lokalnog rezultata
- `ExcelDirect = 'excel_direct'` — Excel-only obrada, i za prvi uvoz i za ponovni pokušaj bez ISBN provere

#### `BookImportFailureEnum`

Predloženi kodovi:

- `missing_isbn`
- `invalid_isbn`
- `invalid_field_value`
- `isbn_not_found`
- `lookup_failed`
- `missing_title`
- `missing_inventory_number`
- `invalid_inventory_number`
- `duplicate_inventory_number`
- `inventory_collision`
- `duplicate_seq_number`
- `file_unreadable`
- `missing_columns`
- `duplicate_headers`
- `no_rows`
- `too_many_rows`
- `job_failed`
- `unexpected_error`

### 7.4 Modeli

- `App\Models\BookImport`
  - `library()`
  - `user()`
  - `rows()`
  - castovi za status, failure code, `headers`, `column_map`, brojače i datume, uključujući `lease_expires_at`.

- `App\Models\BookImportRow`
  - `bookImport()`
  - `book()`
  - `bookCopy()`
  - castovi za status, failure code, `attempt_mode`, `raw_payload`, `input_overrides`, `resolved_payload`, `field_sources`, `lookup_diagnostics`, brojače i datume.

### 7.5 DTO klase

Po konvenciji, DTO klase imaju sufiks `DTO`.

- `BookImportListQueryDTO`
- `BookImportRowListQueryDTO`
- `ExcelImportHeaderMapDTO` — zajedničko zaglavlje za različite tipove importa
- `BookImportRowDataDTO`

Efektivni ulaz je originalni `raw_payload` sa primenjenim dozvoljenim `input_overrides`, uključujući eksplicitno brisanje vrednosti. Korisnički `null` ne znači „vrati original“. Ako inventarna kolona ne postoji, DTO ne prihvata naknadno ručno zadavanje broja: generator se koristi pri kreiranju kopije.

### 7.6 Značenje brojača

- `imported_rows` i `failed_rows` broje redove prema trenutnom statusu.
- `processed_rows = imported_rows + failed_rows`; ovaj broj nije istorijski zbir i smanjuje se kada izabrani failed redovi ponovo pređu u `pending`.
- `attempts` na redu raste atomski pri stvarnom preuzimanju reda u obradu, ne pri slanju retry zahteva niti svakim HTTP pozivom provajderu.
- `completed_attempts` na redu raste u istoj transakciji u kojoj započeti pokušaj završi kao `imported` ili `failed`. Vraćanje reda na `pending` ne briše ovaj broj.
- `completed_attempts` na importu je zbir istoimene kolone svih njegovih redova. Može biti veći od `total_rows` i nikada se ne smanjuje tokom ponovnih obrada.
- Pokušaj prekinut padom procesa ostaje uračunat u `attempts`, ali ne i u `completed_attempts`; recovery ga vraća na čekanje, a novo preuzimanje otvara novi pokušaj.
- Ako konačni pad job-a označi neobrađen `pending` red kao `failed`, to menja trenutni napredak, ali ne izmišlja započet ili završen pokušaj tog reda.
- Brojači importa se preračunavaju po batch-u i pri završetku/recovery-ju; pri prihvatanju fallback zahteva ažuriraju se u istoj transakciji sa statusima redova.

---

## 8. Excel format i mapiranje

### 8.1 Ulazni format

- Podržan je samo `.xlsx`.
- Obrađuje se prvi list u fajlu.
- Maksimalna veličina fajla: ~20 MB.
- Maksimalan broj redova: ~20.000.
- Zaglavlje se traži u prvih 20 redova.
- Prazni redovi se preskaču.
- Jedan red = jedna kopija.

### 8.2 Obavezne kolone

Fajl mora imati najmanje jednu od:

- ISBN,
- naslov.

Ako nema ni jedno, upload se odbija.

Ako ima naslov ali nema ISBN:

- standardni uvoz će redove ostaviti `failed`,
- direktni Excel režim može odmah da ih uveze, bez prethodne standardne greške; dostupan je i naknadni fallback.

Ako ima ISBN ali nema naslov:

- standardni uvoz može da uspe preko COBISS-a,
- Excel fallback ne može bez naslova u samom Excel redu; ne preuzima raniji COBISS naslov.

### 8.3 Aliasi kolona

Normalizacija se radi kroz `Text::labelKey`, pa su ćirilica i latinica ravnopravne.

| Kanonsko polje | Aliasi |
|---|---|
| `isbn` | `isbn`, `isbn broj`, `isbn number`, `međunarodni broj` |
| `title` | `naslov`, `naziv`, `naziv knjige`, `title`, `book title`, `наслов`, `назив` |
| `authors` | `autor`, `autori`, `author`, `authors`, `аутор`, `аутори` |
| `category` | `primarna kategorija`, `kategorija`, `kategorije`, `oblast`, `примарна категорија`, `категорија`, `област` |
| `category_secondary` | `sekundarna kategorija`, `druga kategorija`, `secondary category`, `секундарна категорија` |
| `description` | `opis`, `opis knjige`, `description`, `опис`, `опис књиге` |
| `cover_url` | `url korice`, `url korica`, `cover url`, `url корице` |
| `inventory_number` | `inventarni broj`, `inv broj`, `inv br`, `inventory number`, `order number`, `инвентарни број`, `инв број`, `број инвентара` |
| `seq_number` | `redni broj`, `seq number`, `редни број` |
| `publisher` | `izdavac`, `izdavač`, `publisher`, `издавач` |
| `publish_place` | `mesto izdanja`, `mesto`, `publish place`, `место издања` |
| `publish_year` | `godina`, `godina izdanja`, `publish year`, `година` |
| `issue_number` | `broj izdanja`, `issue number`, `број издања` |
| `num_of_pages` | `broj strana`, `strana`, `pages`, `број страна` |
| `dimension` | `dimenzije`, `format`, `dimensions`, `димензије` |
| `part` | `tom`, `part`, `том` |
| `udk` | `udk`, `signatura`, `signature`, `сигнатура`, `удк` |
| `binding` | `povez`, `povez knjige`, `binding`, `повез` |
| `origin` | `tip nabavke`, `način nabavke`, `origin`, `начин набавке` |
| `book_number` | `broj knjige`, `book number`, `број књиге` |
| `place_on_shelf` | `mesto na polici`, `polica`, `place on shelf`, `место на полици` |
| `price` | `cena`, `price`, `цена` |
| `notice` | `napomena`, `notice`, `напомена` |

Napomena:

- `signatura` se mapira na `udk`, jer postojeća inventarna knjiga prikazuje UDK kao signaturu.
- Autori se razdvajaju na `;` i `|`, ne na zapetu, zbog formata „Prezime, Ime“.
- Primarna i sekundarna kategorija prihvataju naziv ili punu putanju, npr. `Književnost / Domaća književnost`. Putanja se razdvaja na `/`, a svaki nivo se rešava kroz `CategoryResolverService` unutar odgovarajućeg roditelja i aktivne biblioteke, bez slanja ID-jeva iz Excel-a.
- `category` ostaje kanonsko polje primarne kategorije radi kompatibilnosti sa prethodno definisanim formatom.
- Inventarni broj, redni broj i broj knjige su različita polja; ne smeju se međusobno zamenjivati.
- Nepoznate kolone se ignorišu, ali se originalna zaglavlja čuvaju u `headers`.
- Dupli kanonski naziv kolone je fatalna greška (`duplicate_headers`).

### 8.4 Normalizacija ćelija

- Trimovanje i normalizacija razmaka.
- Numeričke vrednosti se pretvaraju u string bez naučne notacije.
- Šablon nema datumskih ulaznih kolona. `date_add` i sistemski timestamp-ovi nisu podržan korisnički ulaz; eventualna takva kolona u starijem fajlu ignoriše se kao nepoznata i ne utiče na datum nove kopije.
- Datum dodavanja određuje se tek u transakciji uspešnog kreiranja kopije, kao tadašnji datum u aplikacionoj vremenskoj zoni. Pri idempotentnom ponavljanju već uspešno obrađenog reda ne menja se.
- Korisnički prikaz generisanog datuma koristi `DD-MM-YYYY`, npr. `01-09-2024`, a interno se čuva `Y-m-d`. UTC je vremenska zona, ne format datuma.
- Cena se parsira tolerantno; neispravna vrednost ne obara red.
- Broj strana se parsira u integer.
- U sačuvanom Excel ulazu ISBN ostaje tekstualna vrednost (uz trimovanje); standardni režim koristi `IsbnNormalizer` za pretragu i kontrolnu cifru. Excel-only režim ne briše niti zamenjuje korisnički ISBN radi provere kontrolne cifre.
- Inventarni broj se čuva kao trimovan string, a validira se kroz `InventoryNumberService`.
- `seq_number` je opcion ceo broj sa predznakom (32 bita) i mora biti jedinstven u biblioteci, uključujući arhivirane kopije. Ne generiše se iz inventarnog broja; duplikat daje `duplicate_seq_number`.
- `cover_url` je opcion HTTP/HTTPS URL do 255 znakova, prema trenutnoj dužini kolone `books.cover_url`. Čuva se adresa bez preuzimanja slike; lokalne putanje i sadržaj slike se ne uvoze.
- Nova polja poštuju tipove i dužine postojećih kolona; neispravne neprazne vrednosti daju `invalid_field_value` uz naziv polja u detalju greške.
- Excel formule se ne izvršavaju niti se koristi njihov izračunati rezultat.
  Formula u podržanoj koloni čuva se kao tekst uz `invalid_field_value`.
- Provera pripreme ne zahteva validan ISBN ili naslov u svakom redu: konkretni
  obavezni podaci proveravaju se tek prema korisnički izabranom režimu.

### 8.5 `raw_payload`

`raw_payload` je sačuvani sadržaj Excel reda posle ulaznog mapiranja/normalizacije, pre bibliografskog spajanja. Nije kontejner za COBISS odgovor i ne menja se tokom obrade ili fallback-a. Originalni fajl ostaje sačuvan za izvorni Excel zapis i formatiranje.

Primer:

```json
{
  "isbn": "9788617123456",
  "title": "Na Drini ćuprija",
  "authors": ["Andrić, Ivo"],
  "category": "Književnost",
  "category_secondary": "Školska lektira",
  "description": "Kratak opis naslova.",
  "cover_url": null,
  "inventory_number": "12345",
  "seq_number": 1,
  "publisher": "Prosveta",
  "publish_place": "Beograd",
  "publish_year": "2020",
  "num_of_pages": 320,
  "udk": "821.163.41",
  "binding": "tvrdi",
  "price": "1200.00",
  "notice": null
}
```

`isbn`, `title` i `inventory_number` se dodatno upisuju u kolone tabele zbog pretrage.

- Denormalizovane ulazne kolone ne menjaju se COBISS naslovom ili automatski dodeljenim inventarnim brojem; predstavljaju Excel ulaz sa eventualnim korisničkim ispravkama.
- Posle ispravke previewa denormalizovana polja prate efektivni ulaz radi pretrage, dok se original i dalje čita iz neizmenjenog `raw_payload`.
- `resolved_payload` sadrži konačne vrednosti koje su zaista korišćene, uključujući inventarni broj i datum dodavanja uspešno kreirane kopije. Pre uspešnog upisa nema dodeljenog automatskog broja ni barkoda.
- `field_sources` je mapa po poljima konačnog rezultata; npr. `title: cobiss`, `publisher: excel`, `inventory_number: generated`. Ako je naslov već postojao, njegova zadržana polja imaju poreklo `existing_book`.
- Uspešan snapshot čuva se atomski sa kopijom i statusom reda. Kod neuspeha može postojati razrešeni deo za pregled, ali UI ga mora označiti kao nepotpun/neuvezen; generisane vrednosti iz rollback-a ne predstavljaju se kao dodeljene.

### 8.6 XLSX šablon za popunjavanje

[Preuzmi šablon za uvoz knjiga](./sablon-uvoza-knjiga.xlsx).

- Šablon opisuje planirani import; samo postojanje fajla ne znači da je funkcionalnost implementirana.
- Prvi list `Uvoz` sadrži uputstvo u redovima 1–12, zaglavlje u redu 14 i prazan prostor za podatke od reda 15. Uputstvo je smešteno od kolone B da ostane sačuvano kada korisnik obriše kolonu A.
- Parser traži ceo prepoznatljiv naziv ćelije zaglavlja, ne pojavljivanje reči „ISBN“ ili „Naslov“ unutar rečenice uputstva. Redovi pre zaglavlja se ne tretiraju kao podaci.
- Za automatske inventarne brojeve briše se **cela prva kolona**, ne samo sadržaj. Skrivanje kolone ili ostavljanje praznih ćelija ne aktivira generator.
- Nema kolone `Datum dodavanja`; datum nove kopije dodeljuje aplikacija pri uspešnom upisu, a korisniku se prikazuje kao `DD-MM-YYYY`.
- Korisnička objašnjenja, formati i obaveznost nalaze se u komentarima zaglavlja. Šablon ne sadrži tehnički list sa mapiranjem baze niti nazive kolona baze u komentarima.
- List `Primeri` sadrži izmišljene podatke bez ISBN-a radi prikaza Excel fallback-a; nikada se ne čita pri uvozu prvog lista.
- Šablon ima 23 kolone: unosiva bibliografska polja `books` i `book_copies`, autore i obe kategorije. Nema potrebe popunjavati ili zadržati neobavezne kolone.
- Oznaka pogrešnog zavođenja i pripadajuća napomena nisu deo importa; nove kopije zadržavaju podrazumevane vrednosti tih polja i ne nasleđuju ih iz postojeće kopije.
- Ne uvoze se primarni/strani ID-jevi, `library_id`, slug, sistemski datumi, interna putanja `image`, `borrowed`, `reserved`, arhiva ni istorija pozajmica, rezervacija i otpisa. Biblioteka dolazi iz autorizovanog konteksta; barkod se izvodi iz inventarnog broja.
- `Tag` je povezan sa korisnicima, ne sa knjigama, pa nije deo ovog šablona.
- Test ulaza treba da proveri i originalni šablon i varijantu sa obrisanom prvom kolonom, kao i preskakanje uputstva, praznih redova i pomoćnih listova.

---

## 9. Pipeline — detaljno

### 9.1 Faza A — upload i inicijalizacija

1. `POST /api/v1/book-imports`
2. Validacija:
   - fajl je obavezan,
   - `.xlsx`,
   - max ~20 MB,
   - aktivna biblioteka postoji.
3. Sinhrono se otvara samo zaglavlje:
   - traži se header red u prvih 20 redova,
   - mapiraju se kolone,
   - proverava se da postoji ISBN ili naslov.
4. Ako za biblioteku postoji `pending`/`ready`/`processing` import:
   - vraća se `409`.
5. Fajl se čuva na privatni `imports` disk.
6. U kratkoj DB transakciji kreira se `book_imports` zapis:
   - `status = pending`,
   - `headers`, `column_map`, `has_inventory_column`.
   - Parcijalni unique indeks sprečava istovremene upload-e za istu biblioteku; povreda baš tog ograničenja mapira se u `409`, a sačuvani fajl neuspelog zahteva se uklanja.
7. Posle commit-a dispečuje se `PrepareBookImportJob`.
8. API vraća `201` sa import resursom.

Ako je zaglavlje neispravno, vraća se `422` i fajl se ne zadržava.

### 9.2 Faza B — priprema

`PrepareBookImportJob`:

1. Uzima svež import i atomski preuzima DB lease prema odeljku 15.3.
2. Proverava da je status `pending`; postavlja `started_at` samo pri prvom početku pripreme.
3. Otvara fajl i streamuje redove.
4. Preskače prazne redove.
5. Mapira svaki red u `raw_payload`.
6. Upisuje `book_import_rows` u chunk-ovima, npr. po 500, uz proveru vlasništva lease-a u svakoj chunk transakciji.
   - Prekinuto čitanje može da se ponovi od početka uz uklanjanje delimičnih redova pod proverom lease-a; `pending` import još nije dostupan za izbor ili ispravke.
7. Broji `total_rows`.
8. Ako je prekoračen maksimalan broj redova:
   - briše delimično upisane redove,
   - postavlja import na `failed`,
   - `failure_code = too_many_rows`.
9. Ako nema nijednog reda:
   - `failed`,
   - `failure_code = no_rows`.
10. Tokom mapiranja i upisa chunk-ova proverava ulazni format i normalizovane
    duplikate inventarnih brojeva iz fajla; kasnija pojava istog normalizovanog
    broja dobija `duplicate_inventory_number`. Postojeće kopije, uključujući
    arhivirane, proverava jednim upitom po chunk-u, ne upitom po redu. To je
    provera stanja u tom trenutku, ne zauzimanje broja; stvarni upis ponavlja
    proveru jedinstvenosti. Jedinstvenost opcionog `seq_number` proverava se pri
    stvarnom uvozu, dok priprema proverava njegov 32-bitni celobrojni format.
11. U kratkoj transakciji postavlja `status = ready` i oslobađa lease, bez promene prvobitnog `started_at`. Ulazne greške mogu imati status reda `failed`, ostali redovi su `pending`, još neizabrani. Odsustvo/nevažeća kontrolna cifra ISBN-a samo po sebi nije greška pripreme, jer režim još nije izabran.
12. Ne dispečuje automatski procesor: preview prikazuje Excel brojeve ili oznaku automatske dodele pri uvozu, filtere, checkboxove i izbor režima; korisnik pokreće uvoz. Tokom pripreme vide se već učitani chunk-ovi kao nepotpuni, bez mogućnosti pokretanja.
13. Priprema ne kreira naslove/kopije, ne generiše niti upisuje barkodove i ne poziva generator ili poravnanje inventarne sekvence.

Ako priprema padne:

- import dobija `failed`,
- `failure_code` i `failure_message`,
- delimično pripremljeni redovi mogu se očistiti pod proverom lease-a; inventarna sekvenca i stvarni fond ostaju nepromenjeni.

Greške fajla (`no_rows`, `too_many_rows`, `file_unreadable` i greške zaglavlja)
su konačne za pripremu i brišu delimične redove. Neočekivana greška se prijavljuje
queue mehanizmu; sledeći pokušaj bezbedno resetuje pripremu. Na poslednjem pokušaju
upisuje se `job_failed`. `failed()` callback ne menja import pod živim lease-om:
posle nasilnog prekida takav import čeka oporavak isteklog lease-a (recovery komanda
je deo faze 3). Job zadržava jezik upload zahteva i vraća prethodni jezik workera.
Trenutni brojači uključuju i ulazno neispravne redove, dok brojači pokušaja ostaju
nula, jer stvarna obrada još nije počela.

### 9.3 Faza C — izbor u previewu i standardna obrada

1. Korisnik u `ready` previewu bira `pending` redove (ID-jevi ili ceo filtrirani rezultat), zatim režim `isbn_check` ili `excel_direct`.
2. `POST /api/v1/book-imports/{id}/start` u kratkoj DB transakciji zaključava import, validira selekciju, postavlja izabranim redovima `processing_requested_at` i `attempt_mode`, a importu `processing`.
3. API vraća `202` sa `selected_rows`; job se dispečuje posle commit-a. Zahtev ne radi COBISS HTTP poziv niti kreira knjige/kopije.
4. Neizabrani redovi zadržavaju `pending`, bez zahteva za obradu i bez zauzimanja brojeva. Filtriranje i sortiranje prikaza ne dodeljuju brojeve.

`ProcessBookImportJob` u `isbn_check` režimu:

1. Atomski preuzima DB lease importa i proverava da je status `processing` i da sačuvani režim odgovara poslu.
2. Samo posle bezbednog preuzimanja isteklog lease-a vraća zaostale `processing` redove na `pending`, prema odeljku 15.3; aktivan pokušaj se ne resetuje.
3. Bira batch od ~20 izabranih `pending` redova gde `processing_requested_at` nije null i `attempt_mode = isbn_check`, po `row_number`, i za svaki poziva `BookImportRowProcessor`. Neobrađeni neizabrani redovi ne ulaze u batch.
4. Svaki red:
   - inkrementira `attempts`,
   - postavlja `attempt_mode = isbn_check`,
   - postavlja status `processing` pod DB zaključavanjem i proverava vlasništvo lease-a.
5. Posle batch-a:
   - preračunava brojače,
   - ako ima još redova, oslobađa lease i posle commit-a dispečuje sledeći batch,
   - završava tekuće pokretanje samo kada više nema izabranih `pending`/`processing` redova; preostali neizabrani redovi vraćaju import na `ready`.

Finalizacija:

- ako postoje neizabrani `pending` redovi → `ready`, `finished_at = null`,
- inače ako `failed_rows = 0` → `completed`,
- inače → `completed_with_errors`,
- za završne statuse `finished_at = now()`.
- Lease se oslobađa u istoj transakciji; zastareli job ne sme finalizovati import.

Ako job konačno padne:

- uz DB zaključavanje i proveru vlasništva, preostali izabrani `pending`/`processing` redovi tekućeg pokretanja se označavaju `failed`; neizabrani redovi ostaju netaknuti, a zastareli job ne menja redove koje je preuzeo novi worker,
- `failure_code = job_failed`,
- import se vraća na `ready` ako ima neizabranih redova, inače dobija `failed` ili `completed_with_errors` ako je bilo uspešnih redova.
- Završetak započetog pokušaja ažurira `completed_attempts`; redovi koji nisu ni započeti ne povećavaju kumulativni broj pokušaja.

### 9.4 Faza D — ponovna obrada neuspešnih redova

1. Korisnik po potrebi ispravlja podatke, pa bira failed redove i režim: ponovna provera `isbn_check` ili `excel_direct` bez ISBN provere.
2. `POST /api/v1/book-imports/{id}/retry`
3. Podržano:
   - `row_ids: [1, 2, 3]`,
   - ili `all_matching: true` uz `filters`, za sve failed redove trenutnog filtriranog rezultata, nezavisno od paginacije.
4. Validacija:
   - redovi pripadaju tom importu,
   - redovi su u statusu `failed`,
   - priprema nije u toku i import nije `processing`; sopstveni `ready` preview dozvoljava retry,
   - biblioteka nema drugi aktivan import; parcijalni unique indeks štiti i ovu promenu statusa.
5. Redovi se postavljaju na:
   - `status = pending`,
   - `attempt_mode` prema izabranom režimu,
   - `processing_requested_at = now()`,
   - `failure_code = null`,
   - `failure_message = null`,
   - `resolved_payload = null`, `field_sources = null`, da prethodni rezultat ne izgleda kao ishod novog pokušaja; `raw_payload` se ne menja,
   - `processed_at = null`; `attempts` i `completed_attempts` se ne resetuju.
6. Izbor se razrešava na serveru, a promene redova, prelazak importa u `processing`, `finished_at = null` i trenutni brojači ažuriraju se u jednoj DB transakciji.
7. Posle commit-a dispečuje se `ProcessBookImportJob` u izabranom režimu. Broj iz efektivnog Excel ulaza ponovo se proverava; bez inventarne kolone broj će se generisati tek pri upisu kopije.
8. Fallback batch može biti veći, npr. 100, jer nema eksternih poziva.
9. Na kraju se ponovo računaju brojači i finalizuje status.

### 9.5 Direktni Excel režim iz početnog previewa

- Isti `/start` endpoint prima `mode = excel_direct` za izabrane `pending` redove; COBISS neuspeh nije preduslov.
- `ProcessBookImportJob` može obrađivati veći batch, npr. 100, ali svaki red ima sopstvenu kratku transakciju.
- `excel_direct` koristi isti Excel-only procesor iz odeljka 12 pri početnom pokretanju i retry-ju. Endpoint i stanje reda određuju kontekst ponovnog pokušaja, ne dodatna vrednost režima.
- Ne pozivaju se eksterni ISBN servisi, scraping ni metapodaci iz njihovog keša. Ne traži se zamena naslova preko ISBN-a; postojeći naslov može se povezati lokalno po naslovu i autorima.
- Prazan ISBN nije greška u ovom režimu. Naslov, pravila inventarnog broja i ostala pravila valjanog upisa i dalje važe.
- Ne proverava se ISBN kontrolna cifra i ne menja se ISBN prema katalogu: neprazna vrednost se čuva iz efektivnog Excel ulaza kao trimovan tekst do 32 znaka. Predugačak ili netekstualni neobradiv unos i dalje podleže tehničkoj validaciji. Original ostaje u `raw_payload`.
- Uspeh/greška, pokušaji, poreklo podataka i brojači beleže se kao kod standardnog job-a. Datum dodavanja nastaje tek pri uspešnom upisu, ne u previewu.

### 9.6 Ispravke u previewu pre ponovnog pokušaja

- Dozvoljene su izmene `pending` i `failed` redova kada priprema/obrada nije u toku. `processing` i `imported` redovi se ne menjaju ovim API-jem.
- `PATCH /book-imports/{id}/rows/{row}` prima samo dozvoljena ulazna polja i `input_version`. Pogrešna verzija ili pokušaj izmene tokom obrade vraća `409`.
- Ispravke idu u `input_overrides`, a originalni `raw_payload` ostaje neizmenjen. Pretraga prati efektivni ulaz; UI može prikazati original i ispravku.
- Ispravka ne pokreće job automatski, ne resetuje brojače pokušaja niti skriva razlog prethodnog pada pre novog pokretanja.
- Kada inventarna kolona postoji, njen broj može se ispraviti kao i ostala ulazna polja. Proveravaju se format i duplikati, bez pomeranja sekvence; slobodan broj potvrđuje se ponovo u transakciji kreiranja kopije.
- Kada inventarna kolona ne postoji, nema ručnog zadavanja automatskog broja kroz ispravku. Datum dodavanja, barkod, ID-jevi i sistemski statusi nisu korisnički ulaz.
- Ponovni pokušaj koristi efektivne ispravljene vrednosti. Bez inventarne kolone dobija tada sledeći automatski broj, ne broj iz neuspešnog pokušaja; Excel-only režim ne preuzima ranije COBISS metapodatke.

---

## 10. Pravila inventarnih brojeva

### 10.1 Kolona postoji

- Vrednost se trimuje.
- Normalizuje se kroz `InventoryNumberService::normalizeOrderNumber`.
- Mora imati samo cifre.
- Maksimalno 12 cifara.
- Proverava se jedinstvenost:
  - u bazi,
  - uključujući soft-deleted kopije,
  - u okviru istog importa, nakon normalizacije.
- Ne primenjuje se pravilo „bez preskakanja“ redovnog ručnog unosa: istorijski fond može imati praznine i nesortirane brojeve. To ne ukida proveru jedinstvenosti ni zabranu smanjivanja sekvence.
- Barkod se izvodi kroz `InventoryNumberService::barcodeFor` tek pri stvarnom kreiranju kopije.
- Sekvenca se poravnava prema odeljku 10.6 i nikada se ne smanjuje.

### 10.2 Kolona ne postoji

- U pripremi previewa nema numeracije ni rezervacija. Pri kreiranju kopije poziva se `InventoryNumberService::next()` u istoj transakciji sa upisom kopije i uspešnog ishoda reda.
- Ovo važi i za biblioteku u ručnom režimu, uz upozorenje u UI-ju.
- Broj se generiše atomski, uz zaključavanje sekvence.
- Neposredno pre generisanja sekvenca se pod istim zaključavanjem poravnava sa već korišćenim brojevima, uključujući arhivu, prema odeljku 10.6.
- Ne garantuje se numerički redosled po Excel redovima ili kopijama istog naslova. Neuspešan/neizabran red ne zauzima broj; kasniji retry dobija tada sledeći broj.

### 10.3 Prazna ćelija

- Ako kolona postoji, a ćelija je prazna:
  - red pada sa `missing_inventory_number`.

### 10.4 Duplikati

- Duplikat u bazi → `duplicate_inventory_number`.
- Duplikat u istom importu → `duplicate_inventory_number`.
- Provera u previewu nije rezervacija; ako drugi unos u međuvremenu zauzme broj, konačni upis vraća `duplicate_inventory_number`, bez automatske zamene Excel broja.
- Kolizija sa arhivom pri automatskom generisanju → `inventory_collision`.

### 10.5 Zašto odvojen servis za kopije

`BookCopyService`:

- u auto režimu ne dozvoljava ručni broj,
- u ručnom režimu primenjuje strogo gap pravilo,
- nije pogodan za bulk migracioni uvoz.

Zato se uvodi `BookImportCopyService` koji:

- poštuje odluku da fajl ima prednost,
- proverava format i jedinstvenost,
- dozvoljava istorijske brojeve sa preskakanjima,
- poravnava sekvencu prema odeljku 10.6,
- koristi broj iz Excel-a ili poziva postojeći generator pri stvarnom upisu kopije.

### 10.6 Poravnanje sekvence za uvoz

- Pre automatske dodele polazna vrednost je `max(trenutna sekvenca, maxUsed(biblioteka))`; generator zatim dodeljuje sledeći broj. Ako nema korišćenih brojeva, njihov maksimum je 0.
- Kod broja iz Excel-a uspešan upis postavlja sekvencu na `max(trenutna sekvenca, maxUsed(biblioteka), upravo upisan inventarni broj)`. Niži slobodan broj je dozvoljen i ne vraća sekvencu unazad.
- Primer prazne biblioteke: uspešan uvoz broja 500 podiže sekvencu na 500; sledeći Excel red sa slobodnim brojem 3 ostaje 3, a naredni automatski unos dobija 501. Fajl ne mora biti sortiran niti počinjati od 1.
- Operacija je atomska, u kratkoj transakciji kreiranja jedne kopije, uz zaključavanje iste per-library sekvence koju koristi `next()`. Poravnanje, dodela, kopija, barkod i uspešan ishod reda zajedno se komituju ili vraćaju.
- Za ovaj tok se ne koristi `syncToMaxExisting()`: on računa samo aktivne kopije i može da smanji sekvencu. Potrebna je monotona operacija u postojećem inventarnom servisu, ne novi nezavisni generator.
- Maksimum celog fajla ne pomera sekvencu unapred. Neispravni, neuspešni i neizabrani redovi ne utiču na nju; na kraju uspešne obrade sekvenca obuhvata sve stvarno uvezene brojeve.
- Pre generisanja proverava se da sledeći broj staje u 12 cifara; nema preliva ili skraćivanja. Greška upisa vraća i promenu sekvence iz te transakcije.

### 10.7 Dodela tek pri kreiranju kopije

- Preview čuva samo ulazno stanje i ispravke. Nema polja `reserved_inventory_number`, posebne tabele rezervacija, zauzimanja barkodova ili pomeranja inventarne sekvence.
- Sa inventarnom kolonom koristi se efektivna Excel vrednost posle provere formata i jedinstvenosti; bez nje poziva se postojeći `InventoryNumberService::next()` tek kada je red spreman za upis.
- Normalizacija, provera postojećih kopija i računanje barkoda koriste postojeće metode inventarnog servisa. Ne poziva se `BarCode::generate()`, jer on koristi sekvencu korisničkih kartica.
- U transakciji se proverava idempotentnost reda, zaključava sekvenca, ponavlja provera slobodnog broja i upisuju kopija sa izvedenim barkodom, konačni podaci i uspešan status.
- Redosled zaključavanja finalnog upisa je import → per-library sekvenca → import red → kopija. Eksterni HTTP zahtevi nisu deo te transakcije.
- Ako transakcija padne, broj nije dodeljen tom redu. Kasniji retry koristi tadašnje stanje sekvence; ne obećava se isti automatski broj niti isti barkod kao u neuspešnom pokušaju.
- Ako je commit uspeo, red je `imported` sa `book_copy_id`; dupli job/recovery preskače red i ne troši novi broj. Gubitak odgovora posle commit-a ne sme izazvati drugo kreiranje.
- Neizabrani redovi i `ready` import ne zauzimaju inventarne brojeve. Redovan unos može se nastaviti dok preview čeka; zato se Excel broj proverava ponovo pri upisu.
- Ne uvode se rezervacione provere u redovan unos ili reconciliation. Ostaju zajednička sekvenca i postojeća DB ograničenja; posebna dozvola istorijskih praznina odnosi se samo na import.

---

## 11. Standardni uvoz reda

`BookImportRowProcessor` u `isbn_check` režimu:

1. Uzima `BookImportRowDataDTO`.
2. Ako inventarna kolona postoji, proverava njen efektivni broj:
   - format, praznu vrednost i poznate duplikate,
   - greška prekida red pre COBISS lookup-a,
   - konačna provera baze ponavlja se pri upisu; bez kolone se broj još ne generiše.
3. Normalizuje ISBN.
4. Ako ISBN nedostaje:
   - `missing_isbn`.
5. Ako ISBN nije validan:
   - `invalid_isbn`,
   - bez eksternog poziva.
6. Proverava lokalnu bazu:
   - `book_copies.isbn` po svim formama ISBN-10/13,
   - unutar biblioteke.
7. Ako postoji lokalna kopija:
   - koristi se njen `book_id`,
   - postojeći naslov i njegovi autori/kategorije ostaju neizmenjeni,
   - nova kopija koristi podatke iz Excel-a; ne nasleđuje proizvoljno metadata prve lokalne kopije, jer njihovo poreklo možda nije COBISS.
8. Ako nema lokalno:
   - poziva se eksplicitni COBISS-only lookup, bez prolaska kroz OpenLibrary/Google Books.
9. Ako metapodaci postoje:
   - naslov i autori iz metapodataka,
   - Excel popunjava prazna polja,
   - kategorija preko `CobissCategoryMapper`,
   - `BookMatcher::duplicates` pronalazi postojeći naslov,
   - ako nema, `BookService` kreira novi naslov i povezuje autore.
10. Ako metapodaci ne postoje:
    - `isbn_not_found` samo za potvrđeno odsustvo rezultata,
    - `lookup_failed` za neuspešnu/nepotpunu proveru, uz `lookup_diagnostics` prema odeljku 11.4.
11. Kreira se kopija:
    - bibliografska polja prate COBISS → Excel prema matrici 11.2,
    - lokalni podaci primerka dolaze iz efektivnog Excel ulaza; inventarni broj se uzima iz Excel-a ili generiše u transakciji kreiranja kopije, a datum dodavanja određuje aplikacija,
    - nema dopune iz nedozvoljenih provajdera ni proizvoljnog nasleđivanja podataka drugih kopija.
12. U istoj transakciji:
    - kreira se `book_copy`,
    - ažurira se red na `imported`,
    - upisuju se `book_id`, `book_copy_id`, `resolved_payload` i `field_sources`,
    - `raw_payload` ostaje sačuvano Excel stanje.

### 11.1 Redosled i transakcije

- Eksterni lookup se izvodi van DB transakcije.
- Kreiranje naslova i kopije je u kratkoj transakciji.
- Ako kreiranje kopije padne, rollback obuhvata i novo-kreirani naslov.
- Ako je naslov postojeći, rollback ne dira njegove podatke.
- Pre upisa naslova, kopije ili ishoda zaključava se import i proverava važeći `processing_token` i lease, pa uzima zaključavanje sekvence i reda prema odeljku 10.7. Zastareli izvršilac ne sme ništa da upiše.
- Završetak pokušaja, `completed_attempts` reda i status reda upisuju se zajedno sa kopijom ili zajedno sa neuspešnim ishodom.

### 11.2 Precedenca podataka

**Pravilo po polju:** validna neprazna vrednost iz odgovarajućeg COBISS zapisa ima prednost nad Excel vrednošću. Kada COBISS to polje ne vrati, koristi se Excel. Ne traži se zamena kod OpenLibrary/Google Books.

Ovo važi za bibliografske podatke. Podaci konkretne bibliotečke kopije nisu isto što i kataloški podaci: cena nabavke nije cena iz kataloga, a lokalna polica nije COBISS lokacija.

| Kolona šablona | Kada je COBISS zapis pronađen | Bez vrednosti za to polje u COBISS-u / Excel fallback |
|---|---|---|
| Inventarni broj | Isključivo Excel ako kolona postoji; inače postojeći generator pri kreiranju kopije | Ista pravila u svim režimima; prazan unos u postojećoj koloni je greška, automatski retry dobija tada sledeći broj |
| ISBN | Validirani ISBN iz Excel-a je ključ pretrage; ne zamenjuje se drugim ISBN-om iz zapisa | Originalna vrednost ostaje sačuvana; nevažeći ISBN ne koristi se za lookup |
| Naslov | COBISS → Excel | Excel; za kreiranje novog naslova mora postojati |
| Autori | COBISS → Excel, kao celina liste autora, bez spajanja konfliktnih lista | Excel |
| Primarna kategorija | COBISS kategorija/UDK mapiranje → Excel | Excel naziv/putanja; u fallback-u može UDK mapiranje iz samog Excel-a |
| Sekundarna kategorija | Pouzdano mapirana COBISS vrednost → Excel | Excel; trenutni metadata ugovor nema zasebno polje |
| Opis | COBISS → Excel | Excel |
| URL korice | COBISS → Excel | Excel, bez preuzimanja slike |
| Redni broj | Isključivo Excel | Excel ili `null`; ne nasleđuje se |
| Izdavač | COBISS → Excel | Excel |
| Mesto izdanja | COBISS → Excel | Excel |
| Godina izdanja | COBISS → Excel | Excel |
| Broj izdanja | Pouzdano mapirana COBISS vrednost → Excel | Excel dok parser ne pruža taj podatak |
| Broj strana | COBISS → Excel | Excel |
| Dimenzije | COBISS → Excel | Excel |
| Tom | Pouzdano mapirana COBISS vrednost → Excel | Excel dok parser ne pruža taj podatak |
| UDK | COBISS → Excel | Excel |
| Povez | Pouzdano mapirana COBISS vrednost → Excel | Excel dok parser ne pruža taj podatak |
| Način nabavke | Isključivo Excel | Excel ili postojeća podrazumevana vrednost |
| Broj knjige | Isključivo Excel | Excel ili `null` |
| Mesto na polici | Isključivo Excel | Excel ili `null` |
| Cena | Isključivo Excel: cena nabavke konkretne kopije | Excel; prazno/neispravno prati postojeće pravilo parsiranja cene, nikada COBISS cenu |
| Napomena | Isključivo Excel: napomena uz konkretnu kopiju | Excel ili `null` |

- „Nema vrednosti“ znači `null`, prazan/whitespace string ili praznu listu, ne automatski svaku falsy vrednost. Validna nula nije odsustvo vrednosti.
- Koriste se samo provereni podaci odgovarajućeg ISBN izdanja. Sličan naslov ili druga knjiga iz rezultata nisu validan izvor.
- Nepotpuni, ali potvrđeni odgovarajući COBISS zapis dopunjava se iz Excel-a polje po polje; to nije ručni Excel fallback celog reda.
- Neispravna COBISS vrednost se evidentira u dijagnostici i ne koristi; validna Excel vrednost može je zameniti. Neispravna izabrana Excel vrednost podleže pravilima validacije, ne postaje tiho prazna.
- Ako polje nedostaje u oba izvora, opciono ostaje prazno/podrazumevano, a obavezno daje jasnu grešku.
- Postojeći naslov se ne prepisuje automatski tamo gde već ima vrednost. Ako naslov, autori, primarna/sekundarna kategorija, opis ili korica nedostaju, popunjavaju se validnim vrednostima koje su razrešene iz COBISS-a pa Excel-a. Popunjena polja se zadržavaju; `field_sources` za svako polje beleži da li je zadržano (`existing_book`) ili dopunjeno (`cobiss`/`excel`/`preview_edit`).
- Lokalni ISBN pogodak ostaje put ponovnog korišćenja postojećeg naslova bez eksternog poziva. Prazna polja tada se dopunjavaju iz Excel-a; lokalne kopije nisu zamenski eksterni provajder.
- Datum dodavanja nije kolona šablona: aplikacija ga dodeljuje prilikom uspešnog kreiranja kopije, sa poreklom `generated`. Ne preuzima se iz COBISS-a, Excel-a ni druge kopije.
- Oznake pogrešnog zavođenja nisu podržan ulaz i ne nasleđuju se iz postojeće kopije.
- Matrica koristi efektivni ulaz (`raw_payload` + `input_overrides`). U direktnom Excel režimu nema COBISS grane: sva bibliografska polja dolaze iz tog ulaza. Automatski inventarni broj i datum nastaju tek pri kreiranju kopije.

#### Ishod pretrage i ponašanje reda

| Situacija | Ponašanje |
|---|---|
| Lokalni ISBN postoji u aktivnoj biblioteci | Ponovno korišćenje naslova bez automatskog prepisivanja; nova kopija iz Excel-a po navedenim pravilima |
| COBISS vrati potvrđeni odgovarajući zapis | Standardni uvoz; COBISS → Excel po bibliografskom polju |
| COBISS zapis je delimičan | Excel dopunjava prazna polja; red uspeva ako su ispunjeni obavezni uslovi |
| COBISS potvrdi da nema rezultata | `failed` / `isbn_not_found`; sačuvani Excel red čeka korisnički izbor fallback-a |
| Timeout, HTTP/parser greška ili nedostupnost COBISS-a | `failed` / `lookup_failed` sa dijagnostikom; ne predstavlja se kao „nije pronađeno“ i ne prelazi se na druge provajdere |
| ISBN nedostaje ili nije validan | `failed` / `missing_isbn` ili `invalid_isbn`; nema eksternog poziva, korisnik može pokrenuti Excel fallback |
| Korisnik odmah izabere direktni Excel import | Job koristi efektivne Excel vrednosti i dodeljuje/proverava inventarni broj pri upisu, bez prethodne COBISS provere; nema potrebe prvo izazvati `failed` |
| Korisnik izričito pokrene Excel fallback | Job koristi efektivne Excel vrednosti i tada dodeljuje/proverava inventarni broj; ne koristi zaostale COBISS podatke prethodnog pokušaja |

U Excel-only režimima ISBN se ne proverava kod provajdera niti se validira kontrolna cifra. Čuva se efektivna Excel tekstualna vrednost u okviru dužine kolone, ili `null` ako nedostaje; original ostaje u `raw_payload`. To ne važi za standardni COBISS režim, koji zahteva validan ISBN.

### 11.3 HTTP retry

Postojeći `IsbnMetadataService` van importa prolazi kroz više provajdera. Import koristi samo COBISS put.

Dodati kontrolisani HTTP retry:

- samo za connection/timeout/5xx,
- ne ponavljati „not found“,
- konfiguracija `ISBN_HTTP_RETRIES` i `ISBN_HTTP_RETRY_DELAY_MS`,
- poštovati postojeći NBS crawl delay.

### 11.4 Strukturisani ishodi ISBN lookup-a

Sadašnji `?BookMetadata` rezultat ne razlikuje prazan rezultat od HTTP/parser greške. Za import promena mora obuhvatiti COBISS provider i njegov HTTP sloj, ne samo orkestrator.

- Uvesti tipizovani rezultat, npr. `IsbnLookupResultDTO`, i status `IsbnLookupStatusEnum`: `found`, `not_found`, `failed`. Za dijagnostiku pojedinačnog provajdera dozvoljen je i `skipped` kada je namerno isključen.
- Rezultat nosi metapodatke samo za `found`, a po provajderu strukturisane detalje: identifikator, ishod, kod razloga, HTTP status ako postoji i broj HTTP pokušaja.
- Očekivani razlozi neuspeha uključuju `connection_error`, `timeout`, `rate_limited`, `http_error`, `invalid_response`, `parse_error` i `configuration_error`.
- `not_found` znači uspešno završen upit i potvrđeno odsustvo odgovarajućeg ISBN zapisa; prazan/promenjen HTML ili neispravan JSON nisu dokaz da knjiga ne postoji.
- U importu ne postoji nastavak lanca na OpenLibrary ili Google Books. COBISS određuje `found`, potvrđeni `not_found` ili `failed`.
- Isključen/pogrešno konfigurisan COBISS daje konačni `failed` zbog konfiguracije, ne `not_found`. `skipped` je dijagnostički ishod, ne dozvola da se pozove drugi provider.
- Na redu se čuvaju `failure_code`, lokalizovana poruka i `lookup_diagnostics` poslednjeg lookup-a; ne čuvaju se API ključevi, sirovi HTTP odgovori ili osetljivi detalji izuzetaka.
- Import keš je odvojen po izvoru i ISBN-u, npr. `isbn:imports:nbs:{isbn}`. Postojeći zajednički ključ `isbn:metadata:{isbn}` može sadržati Google Books/OpenLibrary rezultat i ne sme se koristiti kao import izvor; proverava se i poreklo keširane vrednosti.
- Unutar importa može se ponovo koristiti potvrđeni COBISS `not_found`; privremeni `failed` ne sme postati trajni negativni keš.
- Uvesti eksplicitni COBISS-only ulaz u servis ili import resolver koji koristi `NbsCatalogProvider` i tipizovan rezultat; ne koristiti neograničeni zajednički `lookup()` pa naknadno odbacivati Google/OpenLibrary podatke.
- Postojeći ručni ISBN tok van importa zadržava izbor provajdera i ponašanje; pri eventualnoj promeni zajedničkog ugovora svi direktno zavisni pozivaoci moraju ostati dosledni.

---

## 12. Excel-only uvoz reda — početni direktni režim i fallback

`BookImportRowProcessor` koristi istu Excel-only obradu u režimu `excel_direct`, i za početni uvoz i za ponovni pokušaj neuspešnih redova:

1. Ne poziva `IsbnMetadataService`.
   - Niti poziva COBISS direktno, druge provajdere ili njihove keševe; ne koristi zaostali rezultat standardnog pokušaja.
2. Uzima naslov iz efektivnih Excel podataka, uključujući sačuvane ispravke previewa.
3. Ako naslov nedostaje:
   - `missing_title`.
4. Ako inventarna kolona postoji, proverava njen efektivni broj; bez kolone se dodela odlaže do kreiranja kopije.
5. Kategorija:
   - `CategoryResolverService` pronalazi ili kreira primarnu i sekundarnu kategoriju prema nazivima/putanjama iz Excel-a,
   - ako nema primarne kategorije, može se koristiti UDK mapiranje.
6. Naslov:
   - `BookMatcher::duplicates` po naslovu i autorima,
   - ako postoji, reuse,
   - ako ne, `BookService` kreira novi naslov sa opisom, obe kategorije i URL-om korice iz Excel-a; postojeći naslov se ne prepisuje.
7. Autori:
   - `AuthorResolverService` uz transliteraciju.
8. Kopija:
   - `BookImportCopyService`,
   - broj iz Excel-a ili generator pri upisu, a barkod se izvodi iz tog broja.
9. U kratkoj transakciji:
   - kopija sa datumom stvarnog kreiranja + status reda + konačne vrednosti i njihovo poreklo; originalni `raw_payload` ostaje neizmenjen.
10. Ako padne:
    - `failed` sa novim razlogom.

Ograničenje:

- Fallback ne zaobilazi duplikat inventarnog broja. Korisnik ispravlja konfliktan Excel broj u previewu, pa ponovo pokreće job; slobodan broj potvrđuje se tek u transakciji upisa.

---

## 13. API

### 13.1 Endpoints

| Metoda | Putanja | Namena |
|---|---|---|
| `GET` | `/api/v1/book-imports` | lista uvoza aktivne biblioteke |
| `POST` | `/api/v1/book-imports` | upload `.xlsx` fajla |
| `GET` | `/api/v1/book-imports/{bookImport}` | detalj uvoza |
| `GET` | `/api/v1/book-imports/{bookImport}/rows` | paginirani redovi |
| `POST` | `/api/v1/book-imports/{bookImport}/start` | pokretanje izabranih čekajućih redova iz previewa |
| `PATCH` | `/api/v1/book-imports/{bookImport}/rows/{row}` | dozvoljene ispravke preview reda |
| `POST` | `/api/v1/book-imports/{bookImport}/retry` | ponovna standardna ili Excel-only obrada izabranih neuspešnih redova |

### 13.2 Lista uvoza

Query parametri:

- `page`
- `per_page`
- `sort`
- `direction`

Sort whitelist:

- `created_at`
- `status`
- `total_rows`
- `imported_rows`
- `failed_rows`
- `requested_by`

Scope:

- aktivna biblioteka.

### 13.3 Detalj redova

Query parametri:

- `status` — `pending`, `processing`, `imported`, `failed`
- `failure_code` — jedan od dozvoljenih kodova greške
- `attempt_mode` — `isbn_check` ili `excel_direct`
- `search` — broj reda, ISBN, naslov, inventarni broj
- `page`
- `per_page` — podrazumevano 200, maksimalno 200
- `sort`
- `direction`

Sort whitelist:

- `row_number`
- `status`
- `attempts`
- `created_at`

Pretraga naslova koristi transliteraciju preko `AppliesTransliteratedSearch`.

Response meta, pored paginacije, vraća `matching_pending_rows` i `matching_failed_rows`: broj odgovarajućih redova u celom filtriranom rezultatu, pre paginacije. Isti filteri i pravila pretrage koriste se za pregled i masovnu selekciju.

Resurs svakog reda vraća `raw_payload`, `input_overrides`, `input_version`, efektivni ulaz, `resolved_payload`, `field_sources` i bezbednu lookup dijagnostiku. Dodeljeni inventarni broj i barkod vraćaju se iz uspešnog rezultata/povezane kopije; pre toga automatski broj je `null`. Frontend ne spaja izvore ponovo niti izračunava budući broj iz rednog broja ili sekvence. Pretraga obuhvata efektivni ulaz i stvarno dodeljeni inventarni broj uspešnog reda; original ostaje zasebno dostupan.

### 13.4 Upload

Request:

```text
multipart/form-data
file: .xlsx
```

Response `201`:

```json
{
  "data": {
    "id": 1,
    "status": "pending",
    "has_inventory_column": true,
    "total_rows": 0,
    "processed_rows": 0,
    "imported_rows": 0,
    "failed_rows": 0,
    "completed_attempts": 0
  }
}
```

Greške:

- `422` — neispravan fajl, neispravno zaglavlje,
- `409` — već postoji aktivan uvoz za biblioteku.

### 13.5 Retry

U oba primera obavezan je `mode`: `isbn_check` za ponovni COBISS put ili `excel_direct` za Excel-only obradu. Dozvoljene vrednosti su iste kao pri prvom pokretanju; izbor bez režima se odbija, bez skrivenog podrazumevanja.

Request:

```json
{
  "mode": "excel_direct",
  "row_ids": [11, 12, 13]
}
```

ili:

```json
{
  "mode": "excel_direct",
  "all_matching": true,
  "filters": {
    "status": "failed",
    "failure_code": "isbn_not_found",
    "attempt_mode": "isbn_check",
    "search": "Andrić"
  }
}
```

- `row_ids` i `all_matching` su međusobno isključivi načini izbora.
- Ručni izbor šalje tačne ID-jeve; `filters` se u tom režimu ne šalje.
- `all_matching` na serveru primenjuje iste validirane filtere kao lista redova i dodatno obavezan uslov `status = failed`. Drugi eksplicitni status se odbija.
- `filters` može biti prazan objekat za izbor svih failed redova importa. Raniji predlog `all_failed` zamenjen je ovim ugovorom.
- `page`, `per_page`, `sort` i `direction` nisu selekcioni filteri i ne prihvataju se unutar `filters`.
- Izbor se definiše stanjem pri prihvatanju zahteva, ne kasnije u worker-u. Prelazak izabranih redova na `pending` uz `processing_requested_at` predstavlja sačuvanu selekciju za to pokretanje; ne dodeljuje brojeve.

Response `202` vraća i stvarni `selected_rows` prihvaćen za obradu.

Greške:

- `422` — nema izabranih failed redova, nevažeći filteri ili konfliktni načini izbora,
- `409` — ovaj ili drugi import iste biblioteke je još u obradi.

#### Početno pokretanje iz previewa

`POST /book-imports/{id}/start`:

```json
{
  "mode": "excel_direct",
  "all_matching": true,
  "filters": {
    "status": "pending",
    "search": ""
  }
}
```

- Umesto `all_matching` može `row_ids`; režim je obavezan i dozvoljava `isbn_check` ili `excel_direct`.
- Važe ista pravila selekcije preko svih stranica kao za retry, ali se biraju samo neizabrani `pending` redovi spremnog `ready` importa.
- Neispravna ili mešovita selekcija vraća `422`; pokretanje tokom pripreme/obrade ili konkurentno pokretanje vraća `409`.
- U jednoj kratkoj transakciji čuva se izbor/režim i import prelazi u `processing`. Posle commit-a zakazuje se job i vraća `202` sa stvarnim `selected_rows`.

#### Ispravka preview reda

`PATCH /book-imports/{id}/rows/{row}`:

```json
{
  "input_version": 1,
  "changes": {
    "title": "Ispravljen naslov",
    "publisher": "Ispravljen izdavač"
  }
}
```

- Whitelist ulaznih polja i validacija prate odeljke 8 i 9.6. Zabranjeno je menjanje barkoda/datuma, statusa i već uvezenog reda. Inventarni broj je izmenljiv samo kao ulaz kada fajl sadrži tu kolonu.
- Posle zaključavanja importa/reda proverava se verzija i dozvoljeno stanje, čuvaju ispravke i uvećava verzija; API vraća osvežen resurs. Ispravka broja ne pomera sekvencu i ne zauzima broj.
- Nevažeća izmena vraća `422`, zastarela verzija ili nedozvoljeno stanje `409`. Ispravka ne dispečuje procesor; korisnik ga pokreće zasebnom akcijom.

### 13.6 Query DTO i servisi

Po `AGENTS.md`:

- `BookImportListQueryDTO` — `SortingDTO`, `perPage`.
- `BookImportRowListQueryDTO` — `SortingDTO`, `status`, `failureCode`, `attemptMode`, `search`, `perPage`.
- `BookImportQueryService::build()` vraća `Builder<BookImport>`.
- `BookImportRowQueryService::build()` vraća `Builder<BookImportRow>`.

Kontroler:

- validira query parametre,
- pravi DTO,
- poziva query servis,
- paginira sa `$dto->perPage`,
- vraća resurs.

Filter deo query servisa koristi se i pri razrešavanju `all_matching`, bez paginacije i uz isti tipizovani DTO; filteri se ne implementiraju ponovo u retry kontroleru.

### 13.7 Policy i permisije

`BookImportPolicy`:

- `viewAny`, `create` — administrator biblioteke ili superadmin.
- `view`, `start`, `retry`, `updateRow` — superadmin ili administrator biblioteke kojoj import pripada.
- Bibliotekari i članovi nemaju pristup uvozu.
- Kontroler dodatno zahteva aktivnu biblioteku i proverava da import pripada tom kontekstu.

`AuthorizationService::globalPermissions()`:

- dodati `book_imports`.

Frontend:

- `can('book_imports.viewAny')`
- `can('book_imports.create')`
- `viewAny` kontroliše navigaciju i pregled; `create` kontroliše upload.
- Start, retry i izmene štiti serverski policy po biblioteci importa.

---

## 14. Frontend

### 14.1 Rute

- `/books/imports` — lista uvoza i upload.
- `/books/imports/:id` — detalj uvoza.

### 14.2 Lista uvoza

Prikaz:

- datum pokretanja,
- naziv fajla,
- ko je pokrenuo,
- režim inventarnog broja:
  - „iz fajla“,
  - „automatski“,
- status,
- ukupno / uvezeno / neuspešno,
- akcija za detalj.

Upload modal:

- izbor `.xlsx` fajla,
- kratko uputstvo o kolonama,
- napomena da se priprema izvršava u pozadini, a uvoz se pokreće tek izborom iz previewa,
- posle uspešnog upload-a prelazak na detalj.

### 14.3 Detalj uvoza

Prikaz:

- naziv fajla,
- datum,
- status,
- progres,
- trenutni brojači (`processed_rows`, `imported_rows`, `failed_rows`) i odvojeno „Ukupno završenih pokušaja“ (`completed_attempts`),
- detektovane kolone,
- režim inventarnog broja.

Upozorenja:

- nema kolone inventarnog broja → „Brojevi se dodeljuju pri kreiranju kopija. Numerički redosled ne mora pratiti Excel redove ili kopije istog naslova.“
- biblioteka je u ručnom režimu, a nema kolone → dodatno upozorenje.
- biblioteka je u auto režimu, a kolona postoji → „Koriste se brojevi iz fajla; sekvenca uzima u obzir i ranije korišćene brojeve i nikada se ne smanjuje.“
- pri fallback-u → trenutni broj završenih redova može da se smanji jer se izabrani redovi ponovo obrađuju; kumulativni broj završenih pokušaja ostaje sačuvan.

Tabela redova:

- preview sačuvanih `book_import_rows` pre pokretanja i live napredak tokom job obrade, ne ponovno čitanje fajla pri svakom osvežavanju,
- checkbox, broj Excel reda, status, razlog greške i link do naslova/kopije,
- svih 23 Excel kolona kroz horizontalno skrolovanje ili izbor vidljivih kolona; podrazumevano vidljivi ISBN, naslov, autori i inventarni broj,
- preklopnik „Originalni Excel“ / „Podaci za uvoz“ / „Korišćeni podaci“ odvaja original, efektivne ispravke i rezultat; poreklo uključuje „Ispravka u previewu“,
- kolona „Dodeljeni inventarni broj“ prikazuje broj tek posle uspešnog kreiranja kopije; do tada stoji „Dodeljuje se pri uvozu“ za automatski režim, a Excel broj ostaje vidljiv kao ulaz bez garancije zauzeća,
- datum dodavanja pojavljuje se samo među konačnim podacima posle kreiranja kopije, u formatu `DD-MM-YYYY`; nije korisnička ulazna kolona,
- dok rezultat nije spreman prikazuje se „Na čekanju“/„U obradi“, ne izmišljeni konačni podaci,
- nepotpun rezultat neuspešnog reda označen je kao neuvezen; ostaje moguće videti sve sačuvane Excel vrednosti za naknadni fallback,
- konflikt COBISS/Excel vrednosti može se uporediti iz konačne ćelije sa sačuvanom Excel vrednošću, bez njenog prepisivanja.

Po završetku pripreme preview čeka korisnika (`ready`). Dostupni su izbor redova, pretraga i dva početna režima: „Import sa ISBN proverom (COBISS)“ i **„Import bez provere ISBN broja na eksternim servisima (COBISS)“**. Dugme „Pokreni uvoz“ dispečuje obradu izabranih redova; tek tada kreće COBISS ili direktni Excel job. Preview ostaje isti ekran za praćenje rezultata, ispravke i retry.

Filteri:

- status: svi, neuspešni, uvezeni, na čekanju, u obradi,
- razlog greške,
- režim poslednjeg pokušaja: „Sa ISBN proverom“ (`isbn_check`) ili „Bez ISBN provere“ (`excel_direct`), nezavisno od toga da li je u pitanju prvo pokretanje ili retry.

Pretraga:

- broj reda,
- ISBN,
- naslov,
- inventarni broj.

Selekcija:

- checkbox po `pending` redu za početno pokretanje ili po `failed` redu za retry; `processing`/`imported` redovi nisu dostupni,
- checkbox u zaglavlju bira sve redove dozvoljenog statusa celog trenutnog filtriranog rezultata, preko svih stranica, ne samo vidljive redove; mešanje početne i retry selekcije nije dozvoljeno,
- paginacija podrazumevano prikazuje 200 redova i ne menja selekcioni skup,
- ručni izbor pamti ID-jeve preko stranica; promena filtera/pretrage resetuje selekciju,
- „Izabrano svih N redova za trenutne filtere“ koristi odgovarajući `matching_pending_rows` ili `matching_failed_rows`, a ne broj redova na stranici,
- masovna selekcija može da se poništi istim checkbox-om; zatim korisnik može ručno da bira pojedinačne redove,
- polling ne sme tiho promeniti značenje selekcije; konačan skup server razrešava pri prihvatanju zahteva.

Akcija:

- „Pokreni uvoz“ uz izabrani početni režim,
- „Ispravi podatke“ za dozvoljene redove, sa čuvanjem pre retry-ja,
- „Pokušaj ponovo sa COBISS proverom“ ili „Uvezi iz Excel podataka“ za neuspešne redove,
- confirmation dijalog sa brojem redova, režimom i upozorenjem da Excel-only put ne proverava ISBN na eksternim servisima,
- prikaz broja izabranih redova i aktivnih filtera pri masovnom izboru,
- posle prihvatanja koristi se serverov `selected_rows` kao stvarni broj redova u obradi.

### 14.4 Polling

- Lista i detalj: polling tokom `pending`/`processing`, kada se priprema ili obrađuje job.
- `ready` čeka korisnika i nije signal za beskonačni polling ili automatsko pokretanje; invalidirati upite posle izmene/pokretanja.
- Interval: 5–10 sekundi.
- Toast na prelazak u `completed`, `completed_with_errors` ili `failed`.

### 14.5 API klijent

Dodati:

- `api.uploadFile(path, file, field = 'file', fields = {})`.

Zadržati postojeći `api.upload()` za slike.

### 14.6 Navigacija

- Dodati karticu „Uvoz knjižnog fonda“ na `BooksLandingPage`.
- Ikona: `Upload` ili `FileUp`.
- Dodati stavku u administratorski sidebar i import dugmad na listama naslova i kopija; prikazati ih administratoru biblioteke i superadminu.

### 14.7 Prevod

Dodati ključeve u:

- `frontend/src/i18n/locales/sr-Latn.json`
- `frontend/src/i18n/locales/sr-Cyrl.json`
- `frontend/src/i18n/locales/en.json`

Backend prevodi:

- `lang/sr-Latn/book_imports.php`
- `lang/sr-Cyrl/book_imports.php`
- `lang/en/book_imports.php`

---

## 15. Bezbednost, performanse i operativa

### 15.1 Bezbednost

- Fajl se čuva privatno, bez javnog URL-a.
- Validacija ekstenzije i MIME tipa.
- Maksimalna veličina fajla.
- Maksimalan broj redova.
- Autorizacija po biblioteci.
- Nema izvršavanja formula iz Excel-a.
- Nema download endpoint-a za originalni fajl u v1.

### 15.2 Performanse

- Streaming čitanje.
- Bulk insert redova u chunk-ovima.
- Batch obrada redova.
- Brojači se preračunavaju po batch-u, ne po redu.
- Indeksi za status, ISBN i broj reda.
- Denormalizovani `isbn`, `title`, `inventory_number` za pretragu.
- Izbegavanje N+1 upita.
- Keširanje autora unutar job-a.
- Keširanje kategorija unutar job-a.
- Unutar istog importa ponovo koristiti potvrđeni `not_found` za isti ISBN; privremene greške ne tretirati kao dokaz da ISBN ne postoji.

### 15.3 Queue i oporavak

- Poseban `imports` queue.
- Jedan worker.
- DB zaštita: parcijalni unique indeks za aktivan import biblioteke i lease sa tokenom za izvršavanje job-a.
- Self-dispatch.
- `retry_after` veći od timeout-a.
- `book-imports:recover` scheduled command prema pravilima ispod.

#### DB lease i bezbedan oporavak

- Svaki job pod kratkim `lockForUpdate()` zaključavanjem importa proverava status i postojeći lease. Samo kada nema važećeg vlasnika upisuje novi UUID `processing_token` i `lease_expires_at`.
- Parcijalni unique indeks garantuje jedan aktivan import biblioteke; lease tog importa garantuje jednog važećeg izvršioca, bez odvojenih ključeva za ISBN/Excel režime ili početno pokretanje/retry.
- Lease traje duže od hard timeout-a job-a, uz sigurnosnu marginu. Svako novo preuzimanje koristi novi token; dupli queue posao sa važećim tuđim lease-om ne preuzima redove.
- Pre svake trajne izmene (chunk pripreme, preuzimanje reda, upis kopije/ishoda, brojači, finalizacija, failure handler) zaključava se import, proveravaju token i rok važenja, pa zaključavaju potrebni redovi. Eksterni HTTP pozivi ostaju van tih transakcija.
- Izvršilac sa isteklim ili zamenjenim tokenom prekida obradu bez upisa rezultata. Time se sprečava da spori stari worker upiše kopiju nakon što recovery preuzme posao.
- Na kraju batch-a vlasnik u transakciji oslobađa lease i zakazuje nastavak posle commit-a. Dupli dispatch je dozvoljen jer ponovno preuzimanje proverava trenutno DB stanje.
- Recovery traži aktivne importe sa isteklim lease-om ili bez lease-a i bez napretka duže od konfigurisanog perioda. Ne koristi starost `created_at` kao dokaz zaglavljenosti dugog uvoza.
- Recovery invalidira istekli token pod DB zaključavanjem i dispečuje nastavak. Novi job mora ponovo atomski preuzeti lease; više recovery poziva ne daje više vlasnika.
- Tek novi vlasnik vraća zaostale `processing` redove na `pending`, bez brisanja `attempts`, `completed_attempts` ili ispravki. Režim i selekcija nastavka određuju se iz sačuvanog `attempt_mode`/`processing_requested_at`, ne iz pretpostavke da je uvek standardni.
- Za `pending` import sa prekinutom pripremom nastavlja se priprema prema odeljku 9.2; za `processing` import nastavlja se obrada izabranih redova. Nepotvrđena dodela broja vraćena je sa transakcijom i ne čuva se radi retry-ja.
- `ready` import koji čeka korisnika ne dispečuje se kroz recovery i ne finalizuje se kao neuspešan zbog starosti. Ne zauzima brojeve dok čeka.
- Kopija i uspešan ishod reda nastaju u istoj transakciji. Ako je commit već uspeo, recovery vidi `imported` i ne pravi novu kopiju.
- Implementacija faze 3: `ProcessBookImportJob` obrađuje najviše 20 izabranih
  redova i dispečuje nastavak posle commit-a. Pre novog reda ostavlja vreme za
  najgori konfigurisani broj COBISS zahteva, timeout, pauze i ponovne pokušaje.
  Ako ni jedan lookup ne staje u hard timeout, posao prijavljuje konfiguracionu
  grešku umesto beskonačnog dispečovanja praznih batch-eva.
- Neočekivana greška konkretnog reda beleži se kao `unexpected_error`, uz
  rollback svih njegovih upisa i invalidaciju keširanih autora/kategorija iz
  transakcije. Ostali redovi nastavljaju. Greška samog job-a ponovo se baca queue-u;
  nakon iscrpljenih pokušaja samo izabrani nedovršeni redovi dobijaju `job_failed`,
  import postaje `failed`, a uspešni i neizabrani redovi ostaju sačuvani. Nepočeti
  redovi ne dobijaju izmišljene pokušaje.
- Svež queue `failed()` callback ne poništava živ tuđi lease; tada prepušta
  oporavak scheduler-u. Recovery pod DB lock-om osvežava `updated_at` i kada su
  token i rok već prazni, da ponovljen poziv ne dispečuje isti posao iznova.

### 15.4 Logovanje

Logovati:

- početak pripreme,
- broj redova,
- početak obrade,
- svaki batch: obrađeno / uspešno / neuspešno,
- finalizaciju,
- fatalne greške,
- recovery akcije.

Koristiti `imports` log kanal.

---

## 16. Testiranje

### 16.1 Unit testovi

- detekcija zaglavlja,
- aliasi ćirilica/latinica,
- dupli header-i,
- mapiranje `signatura` → `udk`,
- svih 23 zaglavlja XLSX šablona i varijanta sa obrisanom inventarnom kolonom,
- preskakanje redova uputstva i pomoćnih listova šablona,
- parsiranje autora,
- parsiranje cene,
- normalizacija ISBN,
- `BookImportRowDataDTO`.

### 16.2 Feature testovi — API

- upload zahteva aktivnu biblioteku,
- upload odbija neispravan fajl,
- upload odbija fajl bez ISBN-a i naslova,
- upload detektuje inventarnu kolonu,
- upload vraća 409 ako postoji aktivan uvoz,
- dva konkurentna upload-a iste biblioteke ne mogu oba napraviti aktivan import,
- fallback starog importa vraća 409 dok drugi import iste biblioteke radi,
- lista je scope-ovana na biblioteku,
- detalj poštuje policy,
- redovi se filtriraju po statusu,
- pretraga radi po broju reda, ISBN-u, naslovu i inventarnom broju,
- transliterovana pretraga radi u oba smera,
- sort whitelist radi,
- `per_page` se primenjuje,
- retry validira pripadnost redova importu,
- retry odbija redove koji nisu failed,
- retry odbija import koji je u obradi,
- priprema završava sa `ready`, bez promene inventarne sekvence, kreiranja kopija/barkodova, COBISS poziva ili automatskog procesorskog job-a,
- `/start` bira samo čekajuće redove sa eksplicitnim početnim režimom, bez dodele brojeva u zahtevu,
- direktni Excel režim može se pokrenuti pre bilo kog neuspešnog standardnog pokušaja,
- `ready` blokira novi upload iste biblioteke, ali recovery ga ne pokreće automatski,
- ispravka previewa čuva original, primenjuje `input_version`, ažurira efektivnu pretragu i odbija izmenu tokom obrade,
- Excel broj se može ispraviti pre upisa bez promene sekvence, a automatski broj nije ručno izmenljiv i vraća se kao `null` pre uspešnog uvoza,
- ručni izbor radi preko više stranica,
- `all_matching` obuhvata ceo filtrirani rezultat, uključujući više od 200 redova,
- izbor po filteru i lista koriste iste uslove, uključujući transliteraciju,
- konfliktni izbori i paginaciona polja u `filters` se odbijaju,
- `matching_pending_rows`, `matching_failed_rows` i `selected_rows` odgovaraju stvarnom skupu.

### 16.3 Feature testovi — obrada

- lokalni ISBN pronalazi postojeći naslov,
- COBISS metadata kreira novi naslov,
- COBISS neuspeh ostavlja red failed,
- invalid ISBN ne poziva COBISS,
- duplikat inventarnog broja ne poziva COBISS,
- auto režim + kolona koristi brojeve iz fajla,
- auto režim + kolona pomera sekvencu,
- poravnanje nikada ne smanjuje sekvencu i uključuje najveći arhivirani broj,
- poravnanje bez inventarne kolone sprečava generisanje broja već korišćenog u arhivi,
- konkurentno poravnanje i generisanje koriste isto zaključavanje sekvence,
- manual režim + nema kolone generiše brojeve,
- preview, ponovljena priprema, filtriranje, ispravke i izbor redova ne menjaju inventarnu sekvencu niti dodeljuju brojeve,
- COBISS neuspeh ne poziva generator; pad transakcije kreiranja kopije vraća i promenu sekvence,
- retry automatskog reda posle drugog uspešnog unosa dobija tada sledeći broj, bez zahteva za Excel redosledom,
- sekvenca, broj, izvedeni barkod, kopija i uspešan ishod reda komituju se zajedno; dupli job posle commit-a ne troši novi broj,
- redovan unos dok preview čeka može zauzeti Excel broj; import zatim prijavljuje konflikt bez tihog generisanja zamene,
- konkurentni automatski unos i import koriste istu sekvencu i ne kreiraju iste brojeve/barkodove,
- prazan fond prihvata nesortirane istorijske brojeve 500 pa 3, zadržava sekvencu 500 i sledeći automatski broj 501; manji broj ne vraća sekvencu unazad,
- neuspešan/neizabran Excel red sa visokim brojem ne podiže sekvencu; uspešan red je podiže u istoj transakciji sa kopijom,
- barkod je normalizovani inventarni broj dopunjen do 12 cifara + EAN-13 kontrolna cifra, nezavisno od `seq_number` i sekvence korisničkih kartica,
- izbor podskupa vraća import na `ready` ako ima neizabranih redova, bez zauzimanja njihovih brojeva,
- `excel_direct` pri prvom pokretanju i retry-ju ne poziva COBISS, HTTP, keš provajdera ni ISBN lookup i koristi istu obradu po redu,
- direktni Excel režim prihvata prazan ili checksum-nevažeći ISBN u granicama tekstualnog polja, a i dalje odbija nedostajući naslov i nevažeći inventarni unos,
- prazna ćelija inventarnog broja daje `missing_inventory_number`,
- fallback ne poziva COBISS,
- fallback kreira kategoriju,
- primarna i sekundarna kategorija rešavaju se po punoj putanji i biblioteci,
- novi naslov dobija opis i URL korice po pravilima precedence, a postojeći naslov ostaje neizmenjen,
- redni broj i oznake pogrešnog zavođenja ne nasleđuju se iz postojeće kopije,
- fallback pronalazi postojeći naslov,
- fallback kreira novi naslov,
- fallback čuva `book_id` i `book_copy_id`,
- brojači i statusi su tačni,
- fallback smanjuje trenutni napredak, ali ne smanjuje kumulativne završene pokušaje,
- prekinuti i ponovljeni pokušaji pravilno menjaju `attempts` i `completed_attempts`,
- potvrđeni prazan lookup daje `isbn_not_found`, a timeout/HTTP/parser greška daje `lookup_failed`,
- OpenLibrary i Google Books se nikada ne pozivaju u importu, ni posle COBISS greške ili praznog polja,
- Google/OpenLibrary vrednost pod zajedničkim cache ključem ne koristi se u COBISS-only importu,
- COBISS neprazna bibliografska vrednost ima prednost nad različitom Excel vrednošću,
- delimični COBISS zapis dopunjava se Excel podacima, bez poziva drugih provajdera,
- prazne liste/stringovi ne blokiraju Excel dopunu, a validna nula nije automatski odsustvo vrednosti,
- cena, nabavka, polica, brojevi i napomena primerka ostaju Excel podaci i kada COBISS vrati slično imenovanu vrednost,
- `raw_payload` ostaje neizmenjen tokom obrade, ispravki i retry-ja; denormalizovana polja menjaju se samo korisničkom ispravkom, a konačne vrednosti imaju tačan izvor,
- bez COBISS rezultata nema automatskog Excel fallback-a, a izričiti fallback ne koristi stare COBISS vrednosti,
- datum dodavanja postavlja se na dan uspešnog kreiranja kopije, uključujući fallback drugog dana; upload datum i zastarela Excel kolona ne utiču na njega,
- ponavljanje uspešnog reda ne menja već dodeljeni datum,
- privremeni neuspeh se ne koristi kao trajni negativni keš,
- ponovno pokretanje job-a ne pravi duplikate,
- dupli job ne preuzima važeći lease, a stari token ne može da upiše kopiju ili finalizuje import,
- recovery prekinute pripreme ne duplira redove i nastavlja pravi režim obrade,
- job failure označava preostale redove kao `job_failed`.

### 16.4 Test podataka

- Generisati `.xlsx` fajl kroz OpenSpout writer u test helperu.
- Koristiti `Storage::fake('imports')`.
- Mock-ovati eksplicitni COBISS-only ulaz za standardni tok i posebno dokazati da nedozvoljeni provajderi nisu pozvani.
- Za fallback testove proveriti da nema poziva metadata servisu, COBISS-u ni drugim eksternim izvorima.
- Testovi koriste PostgreSQL bazu, kao i ostatak projekta.

### 16.5 Ručna provera

- Generisati fajl sa ~10.000 redova.
- Uploadovati.
- Proveriti:
  - da request traje kratko,
  - da UI prikazuje progres,
  - da preview prikaže Excel brojeve/oznaku automatske dodele i izbor režima, bez pomeranja sekvence, COBISS poziva ili upisa kopije/barkoda pre „Pokreni uvoz“,
  - da direktni Excel režim i standardni režim rade preko job-ova, a ispravljeni failed red dobije broj tek pri uspešnom upisu,
  - da se aplikacija može koristiti dok uvoz radi,
  - da su brojači tačni,
  - da failed redovi mogu da se filtriraju i ponovo uvezu,
  - da fallback ne poziva COBISS,
  - da su inventarni brojevi i barkodovi ispravni.

---

## 17. Faze implementacije

### Faza 1 — infrastruktura i model

**Implementirano 26.11.2026.** Operativna uputstva su u README odeljku „Excel uvoz — faza 1“.
Konfiguracija koristi timeout 300s, lease 360s i recovery period bez napretka 900s;
database `retry_after` podrazumevano iznosi 900s. Privatni imports disk baca grešku
pri neuspelom čuvanju umesto tihog nastavka.

Zadaci:

- dodati `openspout/openspout:^4.33`,
- dodati privatni `imports` disk,
- dodati `imports` log kanal,
- dodati `config/book_imports.php`,
- napraviti migracije `book_imports` i `book_import_rows`, uključujući `ready` u parcijalnom unique indeksu, ulazne ispravke/verziju, selekciju za obradu, lease i brojače; bez inventarnih rezervacija,
- napraviti modele `BookImport`, `BookImportRow`,
- napraviti enumeracije `BookImportStatusEnum`, `BookImportRowStatusEnum`, `BookImportFailureEnum`, `BookImportModeEnum`,
- napraviti `BookImportPolicy`,
- dodati `book_imports` u `AuthorizationService::globalPermissions()`,
- dodati `queue-imports` u `docker-compose.yml`,
- podesiti `DB_QUEUE_RETRY_AFTER`.

Rezultat:

- baza i permission sloj spremni,
- queue worker spreman,
- nema još poslovne logike.

### Faza 2 — Excel ulaz

**Implementirano i pokriveno testovima.** API priprema podatke za budući preview;
korisnički ekran i API lista pojedinačnih redova nisu deo ove faze.

Zadaci:

- `ExcelImportReader` — zajednički OpenSpout streaming,
- `ExcelImportMapper` i knjiška implementacija `BookImportMapper`,
- `BookImportHeaderMapper` — detekcija i aliasi,
- `BookImportRowPayloadMapper` — mapiranje ćelija,
- `ExcelImportHeaderMapDTO`,
- `BookImportRowDataDTO`,
- `StoreBookImportRequest`,
- `BookImportsController` — `index`, `store`, `show`,
- sinhrona provera zaglavlja pri uploadu,
- `PrepareBookImportJob` job,
- upis redova u chunk-ovima,
- detekcija inventarne kolone,
- provera ulaznih inventarnih vrednosti i normalizovanih duplikata bez promene sekvence,
- prelazak na `ready` bez dodele brojeva, kreiranja kopija/barkodova ili automatskog pokretanja procesora,
- DB lease za bezbednu pripremu i sprečavanje duplog upisa chunk-ova.

Rezultat:

- upload radi,
- redovi se upisuju,
- import ima `total_rows`, `has_inventory_column` i status `ready`, bez dodele brojeva, promene sekvence, COBISS poziva i kreiranja kopija/barkodova.

### Faza 3 — standardni uvoz

Zadaci:

- `BookImportRowProcessor`,
- `BookImportBookResolver`,
- `BookImportCopyService`,
- `BookImportCounterService`,
- `ProcessBookImportJob` u `isbn_check` režimu, sa eksplicitnim trajnim izborom redova,
- lokalni ISBN lookup,
- strukturisani COBISS ishodi i eksplicitni COBISS-only ulaz sa izolovanim kešom; ostali ISBN tokovi ostaju nepromenjeni,
- `BookMatcher`, `BookService`, `AuthorResolverService`, `CobissCategoryMapper`,
- kreiranje kopije sa validnim Excel brojem ili automatskom dodelom kroz postojeći inventarni servis u transakciji upisa,
- monotono poravnanje sekvence sa trenutnom vrednošću, arhivom i upravo kreiranom kopijom; rollback obuhvata i dodelu broja,
- kratke transakcije,
- brojači i statusi,
- spajanje po matrici 11.2, očuvanje `raw_payload`, konačni payload i izvori po poljima,
- automatsko dodeljivanje datuma dodavanja pri stvarnom kreiranju kopije,
- DB lease, provera tokena i recovery komanda,
- self-dispatch,
- kontrolisani HTTP retry za COBISS.

Rezultat:

- 10.000 redova može da se obradi asinhrono,
- svaki red ima status i rezultat,
- failed redovi su sačuvani za fallback.

### Faza 4 — direktni Excel režim, ispravke, retry i API

Zadaci:

- `POST /book-imports/{id}/retry`,
- `POST /book-imports/{id}/start` sa izborom početnog režima,
- `PATCH /book-imports/{id}/rows/{row}` za dozvoljene verzionisane ispravke,
- `BookImportRowQueryService`,
- `BookImportQueryService`,
- `BookImportListQueryDTO`,
- `BookImportRowListQueryDTO`,
- filteri, pretraga, sortiranje, paginacija,
- zajednička Excel-only obrada u `ProcessBookImportJob` za `excel_direct` pri prvom pokretanju i retry-ju, kao i `isbn_check` retry ispravljenih redova,
- selekcija redova i `all_matching` sa zajedničkim filterima, bez paginacionog ograničenja,
- resursi `BookImportResource`, `BookImportRowResource`,
- policy provere.

Rezultat:

- korisnik može da bira režim i redove u pripremljenom previewu, ispravlja podatke i asinhrono pokreće prvi uvoz ili retry,
- API je spreman za frontend.

### Faza 5 — frontend

Zadaci:

- `book-imports-page.tsx`,
- `book-import-detail-page.tsx`,
- `book-import-upload-modal.tsx`,
- hook za polling,
- `api.uploadFile()`,
- API putanje,
- TypeScript tipovi,
- selekcija redova,
- preview pre pokretanja sa svim Excel kolonama, oznakom kasnije automatske dodele, ispravkama, izborom režima i live rezultatima/dodeljenim brojevima posle upisa,
- „izaberi sve neuspešne za trenutne filtere“ preko svih stranica, uz podrazumevano 200 redova po stranici,
- confirmation dijalog,
- upozorenja o inventarnoj koloni,
- kartica na `BooksLandingPage`,
- prevodi u tri lokalizacije.

Rezultat:

- korisnik može da uploaduje, prati i ponovo uveze redove kroz UI.

### Faza 6 — testovi i završna provera

Zadaci:

- unit testovi,
- feature testovi,
- testovi job-ova,
- test sa 10.000 redova,
- provera queue worker-a,
- provera `retry_after`,
- provera logova,
- provera recovery komande,
- dokumentacija u README/CHANGELOG.

Rezultat:

- funkcionalnost je pokrivena testovima,
- operativa je dokumentovana,
- spreman za produkciju.

---

## 18. Kriterijumi prihvatanja

- [ ] `.xlsx` fajl od ~10.000 redova može da se uploaduje bez blokiranja aplikacije.
- [ ] Uvoz se obrađuje u pozadini preko `imports` queue-a.
- [ ] Svaki red ima broj reda, status, podatke i razlog greške.
- [ ] Standardni uvoz koristi lokalno povezivanje i isključivo NBS/COBISS scraping kao eksterni izvor; Google Books/OpenLibrary se ne pozivaju niti koriste iz keša.
- [ ] Bibliografska polja prate COBISS → Excel; lokalni podaci primerka ostaju Excel/generator prema matrici 11.2.
- [ ] Preview pre pokretanja prikazuje sačuvano Excel stanje i korisničke ispravke, bez dodele brojeva ili upisa barkodova/promene sekvence; korisnik bira redove/režim pa pokreće job.
- [ ] Direktni Excel režim dostupan je od početka, bez COBISS pokušaja i eksternih poziva; oba režima koriste job-ove i kratke transakcije po redu.
- [ ] Automatski broj nastaje tek sa kopijom; neuspešan/neizabran red ga ne zauzima, a retry nema garanciju istog broja niti numeracije po Excel redosledu.
- [ ] Redovan unos i import koriste istu sekvencu i DB zaštitu od duplikata; slobodan Excel broj ponovo se proverava pri upisu.
- [ ] Neuspešan red, uključujući Excel inventarni broj, može se ispraviti u previewu bez menjanja originalnog Excel snimka.
- [ ] Datum dodavanja nije ulazna kolona; dodeljuje se pri uspešnom kreiranju kopije, ne pri upload-u.
- [ ] Neuspešni redovi ne prekidaju uvoz.
- [ ] Failed redovi mogu da se filtriraju, pretražuju i selektuju.
- [ ] Fallback uvoz ne poziva COBISS.
- [ ] Fallback kreira/reuse-uje kategoriju, naslov i kopiju.
- [ ] Inventarni brojevi iz fajla imaju prednost i u auto režimu.
- [ ] Sekvenca se pri upisu nikada ne smanjuje i uključuje trenutnu vrednost, arhivirane brojeve i uspešno uvezene brojeve; neuspešni/neizabrani redovi je ne pomeraju.
- [ ] Prazna ćelija inventarnog broja daje `missing_inventory_number`.
- [ ] Duplikat inventarnog broja daje `duplicate_inventory_number`.
- [ ] Biblioteka bez inventarne kolone dobija automatski generisane brojeve.
- [ ] UI prikazuje upozorenje kada kolona nije detektovana.
- [ ] DB parcijalni unique indeks garantuje samo jedan aktivan uvoz po biblioteci, uključujući fallback.
- [ ] Brojači i statusi su tačni nakon standardnog i fallback uvoza.
- [ ] Trenutni napredak i kumulativni završeni pokušaji prate se odvojeno.
- [ ] Lookup razlikuje potvrđeni `not_found` od neuspešne provere i čuva dijagnostiku provajdera.
- [ ] „Izaberi sve“ obuhvata ceo filtrirani rezultat, bez obzira na paginaciju od 200 redova.
- [ ] Recovery i dupli job ne mogu da omoguće zastarelom izvršiocu upis nove kopije.
- [ ] Queue worker je izolovan od email/default queue-a.
- [ ] `retry_after` je veći od timeout-a job-a.
- [ ] Testovi pokrivaju glavne tokove i ivice.
- [ ] Postojeći ručni/automatski unos zadržava ponašanje bez rezervacionog sistema; istorijske praznine i nesortirani brojevi dozvoljeni su u importu uz jedinstvenost i monotono poravnanje sekvence.

---

## 19. Pretpostavke i ograničenja v1

- Podržan je samo `.xlsx`.
- Obrađuje se samo prvi list u fajlu.
- Jedan red = jedna kopija.
- Nema podrške za `.xls` i `.csv`.
- Dozvoljene su validirane ispravke čekajućih/neuspešnih redova u previewu, ne izmena već uvezenih kopija kroz import API.
- Nema brisanja niti otkazivanja uvoza u v1.
- Nema eksporta failed redova u v1.
- Nema download-a originalnog fajla kroz API.
- Ako je red pao zbog postojećeg duplikata inventarnog broja, fallback ne zaobilazi konflikt; korisnik ispravlja ulazni broj u previewu, a konačna provera se ponavlja pri upisu.
- Superadmin radi u kontekstu aktivne biblioteke, kao i ostale katalog liste.
- Za ovaj import merodavan je samo NBS/COBISS scraping; Excel dopunjava bibliografske praznine i određuje lokalne podatke primerka. Globalni ISBN lanac van importa ostaje nepromenjen.
- Pripremljeni preview čeka izbor redova i režima; standardni ili direktni Excel import zatim radi preko job-a. Bez COBISS pogotka korisnik može ispraviti red i pokrenuti standardni retry ili Excel fallback.
- Datum dodavanja određuje aplikacija pri uspešnom upisu kopije, ne korisnički Excel fajl.
- Uvoz koristi `BookImportCopyService` i postojeći inventarni servis; ne uvodi nezavisni generator, rezervacione kolone ili rezervacione provere u druge puteve unosa.

---

## 20. Zaključak

Plan je usklađen sa postojećim Laravel/React projektom i zaključenim odlukama:

- asinhrona obrada preko queue-a,
- streaming Excel čitanje,
- praćenje svakog reda,
- ISBN kao primarni put,
- izbor standardnog ili direktnog Excel režima u pripremljenom previewu,
- dodela inventarnog broja i barkoda tek pri uspešnom kreiranju kopije, bez rezervacija ili garantovanog Excel redosleda,
- isključivo NBS/COBISS kao eksterni izvor i precizna matrica COBISS/Excel prioriteta,
- sačuvani Excel redovi i live preview konačnih vrednosti sa poreklom,
- fallback iz Excel podataka,
- inventarni brojevi iz fajla sa monotonim pomeranjem sekvence pri uspešnom upisu,
- jedan aktivan uvoz po biblioteci,
- jasno razdvojeni servisi i job-ovi,
- UI za praćenje i ponovni uvoz.

Faza 1 je implementirana; sledeći korak je faza 2 — Excel ulaz i priprema previewa.
