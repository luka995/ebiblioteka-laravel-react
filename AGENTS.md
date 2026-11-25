# AGENTS.md

## Konvencije imenovanja

- Sve nove PHP enumeracije imenovati sa sufiksom `Enum` (npr. `ClassTransferRowStatusEnum`).
- Sve DTO klase imenovati sa sufiksom `DTO` (npr. `ClassTransferResultDTO`).
- Postojeće enumeracije i DTO klase ne preimenovati masovno bez posebnog zadatka.

## Pretrage, query servisi i sortiranje

- Za listu/pretragu modela, kontroler treba da pročita validirane query parametre, napravi tipizovani DTO iz `app/DTOs/Queries/` i pozove query servis. DTO je `final readonly`, sadrži samo vrednosti filtera/sortiranja/paginacije, ne prima `Request` niti `Builder`. Za sortiranje koristiti postojeći `SortingDTO`.
- Query servis gradi Eloquent `Builder` i vraća ga bez `get()`, `paginate()` ili formatiranja resursa. Navesti konkretan model u PHPDoc povratnom tipu, npr. `Builder<Book>`. Kontroler posle autorizacije i provere konteksta poziva servis, paginira sa `$dto->perPage` i vraća odgovarajući resource. Svako polje DTO-a, uključujući `perPage`, mora stvarno biti primenjeno.
- Koristiti naziv `build()` kada servis ima jedan prirodan query ulaz. Kada su operacije ili konteksti različiti, koristiti eksplicitne metode kao `forUser()`, `forLibrary()` ili `students()` umesto da se potpisi nasilno ujednačavaju. Prosleđivati kontekst kao tipizovan argument (`User`, `Library`, `SchoolClass`) kada je potreban za scope ili autorizaciju; ne tretirati query DTO kao izvor ovlašćenja.
- Ne uvoditi univerzalni `QueryServiceInterface` niti bazni query DTO samo radi istog potpisa. Servisi vraćaju različite modele i mogu zahtevati različite kontekstne argumente; generički potpis bi zato oslabio tipizaciju ili zahtevao dodatno grananje. Zajedničku apstrakciju dodati samo kada postoji konkretno zajedničko ponašanje koje je zaista koristi.
- Jednostavne filtere držati u query servisu. Složen ili ponovo upotrebljiv skup filtera izdvojiti u klasu poput `app/Queries/BorrowFilters.php`; takva klasa prima `Builder` i tipizovani query DTO, primenjuje uslove i vraća izmenjeni `Builder`. Ne pretvarati DTO u `array<string, mixed>` između servisa i filtera.
- Za tekstualnu pretragu srpskih naziva koristiti `App\Queries\Concerns\AppliesTransliteratedSearch` i `whereTransliterated()`, tako da pretraga radi između ćirilice i latinice. Barkodove, ISBN-ove i druge identifikatore filtrirati kao identifikatore, bez transliteracije.
- Sortiranje raditi preko zajedničkih `SortParser` i `QuerySortBuilder`: parseru proslediti samo ulazne `sort` i `direction`; u `QuerySortBuilder::allow()` definisati whitelistu kolona, a podrazumevanu kolonu i smer zadati samo jednom kroz `defaults(new SortRule(...))`. Ne prosleđivati korisnički sort direktno u SQL ili `orderBy`; builder dodaje stabilni ID tie-breaker.
- Testirati query servis direktnim DTO pozivom i API ponašanje: scope biblioteke/korisnika, filtere, transliteraciju u oba smera, dozvoljene/prazne/nevažeće sort vrednosti i `per_page` paginaciju.
