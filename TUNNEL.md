# Cloudflare Tunnel — deljenje lokalnog okruženja sa kolegama

Ovaj vodič dokumentuje **named tunnel** (stabilne adrese na `ebiblioteka.rs`)
kojim se lokalni eBiblioteka stack deli kolegama preko interneta.

## Šta se deli

| Servis | Kontejner | Lokalna adresa | Javna adresa |
| ------ | --------- | -------------- | ------------ |
| Laravel sajt (nginx) | `ebiblioteka-nginx` | `http://localhost:81` | `https://demo.ebiblioteka.rs` |
| React (Vite dev) | `ebiblioteka-frontend` | `http://localhost:3001` | `https://dashboard.ebiblioteka.rs` |

Adrese su **stabilne** (ne menjaju se pri restartu), ali tunel radi **samo dok
je proces `cloudflared` pokrenut** na ovoj mašini.

## Preduslovi

1. Docker stack pokrenut i oba porta dostupna:

   ```bash
   cd /var/www/ebiblioteka-new
   docker compose ps          # svi kontejneri "Up"
   curl -I http://localhost:81      # očekuj HTTP 200
   curl -I http://localhost:3001    # očekuj HTTP 200
   ```

2. Domen `ebiblioteka.rs` mora biti na Cloudflare DNS-u (nameserver-i
   `*.ns.cloudflare.com`).
3. Internet pristup sa ove mašine.

## 1. Instalacija `cloudflared`

Na Linux Mint 21.1 (Ubuntu 22.04 "jammy"):

```bash
wget https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb
sudo dpkg -i cloudflared-linux-amd64.deb
cloudflared --version
```

> Na ovoj mašini je instaliran u userski prostor (`~/bin/cloudflared`) jer
> `sudo` zahteva lozinku. Zameni `cloudflared` sa `~/bin/cloudflared` u
> komandama ispod ako nisi instalirao globalno.

## 2. Login na Cloudflare nalog (jednokratno)

```bash
cloudflared tunnel login
```

Browser → izaberi nalog i domen `ebiblioteka.rs` → autorizuj. Ovo kreira
`~/.cloudflared/cert.pem`.

> Ako izabereš "Dashboard put": kreiraj tunel na dash.cloudflare.com →
> Zero Trust → Networks → Tunnels → Create, pa pokreći sa
> `cloudflared tunnel run --token <TOKEN>` (config ispod se ne koristi).

## 3. Kreiranje tunela i DNS ruta (jednokratno)

```bash
cloudflared tunnel create ebiblioteka
cloudflared tunnel route dns ebiblioteka demo.ebiblioteka.rs
cloudflared tunnel route dns ebiblioteka dashboard.ebiblioteka.rs
```

Prva komanda ispisuje ID tunela (npr. `a6b53192-...`) i kreira credential fajl
u `~/.cloudflared/`. DNS zapisi su CNAME (Cloudflare ih flatten-uje u A zapise).

## 4. Konfiguracija ingress-a

Fajl `~/.cloudflared/config.yml`:

```yaml
tunnel: ebiblioteka
credentials-file: /home/hp/.cloudflared/a6b53192-2894-4b62-a726-908b3c5c70fe.json

ingress:
  - hostname: demo.ebiblioteka.rs
    service: http://localhost:81
  - hostname: dashboard.ebiblioteka.rs
    service: http://localhost:3001
  - service: http_status:404
```

> Prilagodi `credentials-file` stvarnom UUID-iju iz koraka 3.

## 5. Pokretanje tunela

```bash
cloudflared tunnel run ebiblioteka
```

Provera u drugom terminalu:

```bash
curl -I https://demo.ebiblioteka.rs
curl -I https://dashboard.ebiblioteka.rs
```

> Napomena: `curl` može dobiti HTTP 403 sa Cloudflare challenge stranicom
> ("Just a moment...") ako je na zoni uključen Bot Fight Mode / visok Security
> Level — to se dešava **pre** tunela i curl je ne prolazi. Pravi browser je
> prolazi automatski. Ako i browser dobije challenge koji ne prolazi, isključi
> Bot Fight Mode (Security → Bots) ili spusti Security Level na zoni.

## 6. Login linkovi ka React-u (`FRONTEND_URL`)

Login i "Kreiraj nalog" linkovi u Blade šablonima čitaju
`config('app.frontend_url')` (tj. `.env → FRONTEND_URL`). Sa named tunnel-om
je trajno podešen na:

```
FRONTEND_URL=https://dashboard.ebiblioteka.rs
```

**Ne koristi container name** (npr. `ebiblioteka-frontend`) — on je resolvabilan
samo unutar Docker mreže; browser ga ne može otvoriti. Container name je ispravan
samo za server→server pozive (npr. `fastcgi_pass ebiblioteka-php:9000`).

Za lokalni rad bez tunela, vrati na `http://localhost:3001`. Izmena `.env`
prolazi na sledeći request (config se ne kešira).

## 7. Autostart (opciono)

Da bi tunel preživeo restart mašine i log-ovanje/isključenje terminala, registruj
ga kao systemd servis:

```bash
sudo cloudflared service install
```

(ili ručno `nohup cloudflared tunnel run ebiblioteka > /tmp/cloudflared.log 2>&1 &`
za jednokratno pokretanje u pozadini).

## Dijagnostika

| Simptom | Uzrok / rešenje |
| ------- | --------------- |
| `DNS_PROBE_POSSIBLE` u browseru | Lokalni/ISP DNS keš; zapis postoji na Cloudflare NS. Sačekaj propagaciju (par sati) ili promeni DNS na `1.1.1.1`/`8.8.8.8`. |
| 403 "Just a moment..." | Cloudflare challenge na ivici (Bot Fight Mode). Otvori u pravom browseru ili isključi BF na zoni. |
| `dashboard` ne radi, `demo` radi | Vite host check — `allowedHosts` u `frontend/vite.config.ts` mora da sadrži domen; restartuj frontend posle izmene. |
| HTTP 000 / timeout | Tunel nije pokrenut ili DNS još propagira. Proveri `pgrep -af cloudflared`. |

## Bezbednosne napomene

- Tunel radi samo dok je ovaj uređaj uključen i proces živ.
- Ne otkrivaj adrese javno — svako ko ih dobije vidi lokalni dev okruženje
  (APP_DEBUG je uključen; pri grešci se prikazuju stack traceovi).
- Credential fajl (`~/.cloudflared/*.json`) je osetljiv — ne deli ga i ne
  komituj u repozitorijum.
