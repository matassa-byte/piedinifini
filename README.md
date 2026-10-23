# Piedinifini

E-shop di calzature per bambini di ALPICAT SRL, su WordPress + WooCommerce.
Sviluppato da [Fancy Factory](https://www.thefancyfactory.com).

Tutto gira in Docker: la stessa configurazione si usa sul Mac e sul server.

## Cosa c'è nel repository

| Percorso | Cosa contiene |
| --- | --- |
| `compose.yaml` | Servizi comuni: database (MariaDB), WordPress, WP-CLI |
| `compose.override.yaml` | Solo in locale: sito su http://localhost:8080 |
| `compose.prod.yaml` | Solo sul server: Caddy con HTTPS automatico |
| `config/` | Impostazioni PHP e di Caddy |
| `scripts/` | Installazione iniziale e scorciatoia per WP-CLI |
| `wp-content/themes/piedinifini` | Il nostro tema (child theme provvisorio di Twenty Twenty-Five) |
| `wp-content/plugins/piedinifini-magazzino` | Sincronizzazione giacenze e ordini con il gestionale (bozza) |

WordPress, WooCommerce, le foto caricate e il database **non** stanno nel repository:
vivono nei volumi Docker. Nel repository c'è solo il codice nostro.

## Sviluppo in locale

Serve [Docker Desktop](https://www.docker.com/products/docker-desktop/) acceso.

```bash
cp .env.example .env      # poi cambia le password dentro .env
docker compose up -d      # accende database e WordPress
./scripts/wp.sh setup     # solo la prima volta: installa WordPress, WooCommerce, tema e plugin
```

Il sito è su http://localhost:8080, il pannello su http://localhost:8080/wp-admin
(utente e password sono in `.env`).

Comandi utili:

```bash
docker compose stop                 # spegne tutto (i dati restano)
docker compose up -d                # riaccende
./scripts/wp.sh plugin list         # qualsiasi comando WP-CLI
docker compose down -v              # ATTENZIONE: cancella database e foto locali
```

## Messa online sul server

Su un VPS Linux con Docker installato:

1. Clona il repository e crea `.env` da `.env.example` con password nuove e
   `WP_URL=https://piedinifini.com`, `WP_ENVIRONMENT_TYPE=production`, `SITE_DOMAIN=piedinifini.com`.
2. Fai puntare il dominio al server: su Aruba, record **A** di `piedinifini.com` e `www`
   all'IP del VPS. **Non toccare i record MX**, altrimenti smettono di funzionare le email.
3. Accendi e installa:

   ```bash
   docker compose -f compose.yaml -f compose.prod.yaml up -d
   docker compose -f compose.yaml -f compose.prod.yaml run --rm -T -v "$PWD/scripts/setup.sh:/setup.sh:ro" wpcli sh /setup.sh
   ```

   Caddy ottiene da solo il certificato HTTPS appena il dominio punta al server.

Per portare online i contenuti preparati in locale (prodotti, pagine, foto) si esportano
database e cartella `uploads` e si importano sul server, sostituendo l'indirizzo
`http://localhost:8080` con quello vero (`wp search-replace`).

## Collegamento con il gestionale

Il plugin `piedinifini-magazzino` è una **bozza**: il formato dei dati va concordato
con Federico. Per ora:

- il gestionale aggiorna le giacenze con `POST /wp-json/piedinifini/v1/giacenze`
  e l'header `X-Piedinifini-Token`;
- lo shop manda ogni cambio di stato degli ordini all'indirizzo `PIEDINIFINI_MAGAZZINO_URL`.

I dettagli sono in testa al file del plugin.
