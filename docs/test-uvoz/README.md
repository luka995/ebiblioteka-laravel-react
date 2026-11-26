# Test fajlovi za uvoz knjiga

Fajlovi su napravljeni od šablona [sablon-uvoza-knjiga.xlsx](../sablon-uvoza-knjiga.xlsx). Svaki fajl ima **5.000 redova** podataka.

Podaci su prikupljeni sa legacy COBISS sajta: 5.404 zapisa kroz pretragu i bulk RIS export. Od toga je 1.849 zapisa imalo ISBN, a 1.579 jedinstvenih validnih ISBN-ova. Za Excel-only fajl iskorišćeno je 4.294 jedinstvena naslova/autora (3.796 različitih naslova).

| Fajl | ISBN kolona | Inventarni brojevi | Namena |
|---|---|---|---|
| `01-uvoz-sa-isbn.xlsx` | da | ne | Standardni `isbn_check` uvoz bez inventarnih brojeva; brojevi se generišu. Sadrži sve 1.579 jedinstvene ISBN naslove, ponovljene do 5.000 redova. |
| `02-uvoz-samo-excel.xlsx` | ne | ne | `excel_direct` uvoz samo iz Excel podataka, bez COBISS poziva; brojevi se generišu. Sadrži 4.294 jedinstvena naslova/autora, uz manje ponavljanje do 5.000 redova. |
| `03-uvoz-sa-inv-brojevima.xlsx` | da | da, `900001`–`905000` | Standardni `isbn_check` uvoz sa inventarnim brojevima iz fajla. Isti fajl može poslužiti i za `excel_direct` sa inventarnim brojevima. |

Napomene:

- Sva tri fajla imaju zaglavlje u redu 14 i podatke od reda 15 do reda 5014.
- U fajlovima bez inventarnih brojeva kolona A je namerno prazna, pa parser ne detektuje inventarnu kolonu.
- U fajlu `02-uvoz-samo-excel.xlsx` kolona B je namerno prazna, pa nema ISBN kolone.
- `01` i `03` sadrže sve raspoložive jedinstvene ISBN naslove; preostali redovi do 5.000 su ponovljene kopije istih naslova.
- Ako biblioteka već koristi inventarne brojeve iz opsega `900001`–`905000`, izaberite drugu test biblioteku ili prilagodite opseg pre uvoza.
- `izvor-knjige.json` sadrži izvorne COBISS zapise i služi za reprodukciju test fajlova.
