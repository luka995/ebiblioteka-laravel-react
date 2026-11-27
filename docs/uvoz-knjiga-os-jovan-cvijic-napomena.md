# Uvoz knjiga — OŠ „Dr Jovan Cvijić“, Zrenjanin

Izvorni fajl: `BAZA KNJIGA KONAČNA.xlsx` (list `KNJIGE`, 9.830 redova).
Konvertovani fajl: `uvoz-knjiga-os-jovan-cvijic.xlsx` (9.811 redova).

## Mapiranje kolona

| Naše polje | Izvor |
|---|---|
| Inventarni broj | `INVBR`, normalizovan na ceo broj bez vodećih nula |
| Naslov | `NASLOV1`, uz `NASL2` kao podnaslov gde postoji |
| Autori | `AUTOR`; `/` je zamenjen sa `;` |
| Primarna kategorija | izvedena iz `SIGNA` (UDK) |
| Izdavač | `STAMPA` |
| Mesto izdanja | `MESTO` |
| Godina izdanja | `DATSTA`, izvučena četvorocifrena godina |
| Broj izdanja | `IZDANE` |
| Broj strana | `BRSTR` |
| Dimenzije | `DIMEN` |
| Tom | `KOLO` |
| UDK | `SIGNA` |
| Povez | `POVEZ`: `1` → Tvrdi, `2` → Meki |
| Cena | `CENA` |
| Napomena | `ILUSTR`, `RAZRAS`, `BRUKOLU`, `STAMPAR` i originalni broj kod duplikata |

ISBN nije postojao u izvoru, pa u fajlu nema ISBN kolone. Uvoz treba pokrenuti u **`excel_direct`** režimu, bez COBISS provere.

## Duplikati inventarnih brojeva

- Uklonjeno je 19 potpuno identičnih duplikata (isti inventarni broj, naslov i autor).
- Kada isti inventarni broj ima više različitih naslova, prvi zadržava originalni broj, a dodatni dobijaju izvedeni broj (`original * 10 + redni broj`) i napomenu sa originalnim brojem.
- Redovi sa inventarnim brojem `0` ili neispravnom vrednošću dobili su slobodan broj iznad najvećeg broja u izvoru, uz napomenu sa originalnom vrednošću.
