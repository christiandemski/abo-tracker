# Abo-Tracker

Persönlicher Abo- und Fixkosten-Tracker: monatliche Kosten, Kündigungsfristen, Preiserhöhungen, CSV-Import aus Kontoauszügen. Laravel-API plus Expo-App (Android + Web), gehostet unter https://abo.codekorb.de.

| Ordner | Inhalt |
| --- | --- |
| `api/` | Laravel 13 (PHP 8.5), Sanctum, Pest |
| `app/` | Expo SDK 58, Expo Router, TypeScript |
| `docs/` | `SPEC.md`, `DESIGN.md`, `START-M1.md`, `DEPLOY.md` |
| `design/` | Design-Tokens und Screen-Vorlagen |

## Lokal starten

### Mit DDEV (empfohlen)

Voraussetzungen: Docker, [DDEV](https://ddev.com), Node 24. PHP 8.5 und MySQL 8.4 (wie in CI) laufen im Container, Mails landen in Mailpit.

```bash
ddev start                          # https://abo-tracker.ddev.site
ddev composer install
cp api/.env.example api/.env        # DDEV trägt DB- und Mail-Werte beim Start selbst ein
ddev artisan key:generate
ddev restart
ddev artisan migrate --seed
ddev artisan invite:create du@example.com --admin
ddev mailpit                        # Einladungsmail mit Link öffnen
ddev artisan test
```

Falls `MAIL_SCHEME=smtps` in der `.env` steht: für Mailpit auf `smtp` ändern.

Die App läuft weiter auf dem Rechner (nicht im Container), mit
`EXPO_PUBLIC_API_URL=https://abo-tracker.ddev.site/api/v1` in `app/.env`, dann `cd app && npm run web`.

### Ohne DDEV

Voraussetzungen: PHP 8.5 mit `pdo_sqlite`, `intl`, `mbstring`; Composer; Node 24.

### API

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed          # SQLite unter database/database.sqlite
php artisan serve                   # http://localhost:8000
```

Mails landen lokal in `storage/logs/laravel.log` (`MAIL_MAILER=log`).

Erste Einladung erzeugen und den Link aus dem Log öffnen:

```bash
php artisan invite:create du@example.com --admin
```

### App

```bash
cd app
npm install
cp .env.example .env                # EXPO_PUBLIC_API_URL=http://localhost:8000/api/v1
npm run web                         # Browser, http://localhost:8081
npm run android                     # Emulator oder Expo Go
```

Für ein Android-Gerät im WLAN `EXPO_PUBLIC_API_URL` auf die IP des Rechners setzen und die API mit `php artisan serve --host=0.0.0.0` starten.

Die Web-Version so ausliefern, wie sie später auf dem Server läuft (Laravel liefert sie unter `/` aus):

```bash
cd app && npm run export:web        # → api/public/app/
# dann http://localhost:8000 öffnen
```

## Prüfen

```bash
cd api && php artisan test && vendor/bin/pint --test
cd app && npm run typecheck && npm run lint && npm test
```

Dieselben Schritte laufen in GitHub Actions bei jedem Push (`.github/workflows/ci.yml`).

## Deployment

Siehe [`docs/DEPLOY.md`](docs/DEPLOY.md). Ausgerollt wird per GitHub Action „Deploy“ (manuell starten).

## Design-Referenz

- Design-Canvas: https://claude.ai/artifact/6AxQG2UrX3rDWgqo8Lu3iV
- Technik-Spezifikation (Original): https://claude.ai/artifact/9Cue9EVKHhyvhmjN712BpJ
