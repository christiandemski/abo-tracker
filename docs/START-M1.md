# Auftrag M1 · Fundament

> **Start-Prompt für Claude in der IDE** (einfach so in den Chat kopieren):
>
> Lies `CLAUDE.md`, `docs/SPEC.md` und `docs/DESIGN.md`. Setze dann Meilenstein M1 aus `docs/START-M1.md` um. Mach zuerst einen kurzen Plan mit den Schritten und frag nach, wenn etwas unklar ist. Arbeite in kleinen Commits.

## Ziel

Am Ende von M1 kann ich mich per Einladung registrieren, in der Android-App und im Browser einloggen und sehe einen leeren, aber fertig gestylten Übersichts-Screen. Das Backend läuft auf abo.codekorb.de, der Cronjob ist eingerichtet.

## Aufgaben

### Repo
- [ ] Monorepo mit `api/`, `app/`, `docs/`, `design/` (die Übergabe-Dateien liegen schon drin).
- [ ] `.gitignore` für Laravel und Expo, `README.md` mit lokalen Start-Befehlen.
- [ ] GitHub Actions: PHP-Tests + Lint, App-Typecheck + Lint bei jedem Push.

### Backend (`api/`)
- [ ] Laravel-Projekt (aktuelle Version), MySQL, Sanctum installiert. Zeitzone `Europe/Berlin`, Locale `de`.
- [ ] Migrationen für `users`, `invitations`, `categories` (mit Seeder der Standardkategorien und Farben aus `docs/DESIGN.md`), `personal_access_tokens`, `jobs`.
- [ ] Endpunkte unter `/api/v1`: `POST /auth/register` (nur mit gültigem Einladungstoken), `POST /auth/login`, `POST /auth/logout`, `POST /auth/forgot-password`, `POST /auth/reset-password`, `GET /me`, `POST /invitations` (nur für Admin-Nutzer), `GET /invitations/{token}`, `GET /health`.
- [ ] Artisan-Befehl `invite:create {email}` für die erste Einladung (es gibt noch keinen Admin).
- [ ] Rate Limit auf Login (5/min), Passwort-Mindestlänge 12, E-Mail-Bestätigung.
- [ ] Global Scope + Policy-Grundgerüst für `user_id`-Modelle (Trait `BelongsToUser`) und ein Muster-Feature-Test „Nutzer B sieht nichts von Nutzer A“.
- [ ] SPA-Fallback: alle Nicht-`/api`-Pfade liefern `public/app/index.html` (Web-Build der Expo-App).
- [ ] Scheduler-Grundgerüst in `routes/console.php`: `queue:work --stop-when-empty` minütlich, Platzhalter für `reminders:dispatch` täglich 07:00.
- [ ] Mail-Konfiguration für SMTP (Werte nur in `.env.example` als Platzhalter).

### App (`app/`)
- [ ] Expo-Projekt mit TypeScript und Expo Router; Ziele Android + Web.
- [ ] `src/theme.ts` aus `design/theme.ts`, Schriften Inter + Space Grotesk per `@expo-google-fonts`, Icons `lucide-react-native`.
- [ ] Basis-Komponenten aus `docs/DESIGN.md`: `Card`, `PrimaryButton`, `SecondaryButton`, `TextField`, `Segmented`, `Switch`, `TabBar` mit Plus-Button.
- [ ] API-Client (fetch-Wrapper) mit Basis-URL aus `app.config.ts`/Env, Token in `expo-secure-store` (Web: Fallback auf Session-Storage), 401 → Logout.
- [ ] Screens: Login, Registrierung (Token aus Einladungslink per Deep Link), Passwort vergessen, leere Übersicht mit Onboarding-Karte („CSV importieren“ / „Erstes Abo anlegen“, Buttons noch ohne Funktion).
- [ ] Formatierungs-Helfer `formatCents(cents)` → `1.234,56 €` mit Tests.
- [ ] EAS-Konfiguration mit Profil `preview` (APK).

### Deployment
- [ ] Deploy-Skript (`deploy.sh` oder GitHub Action): Composer `--no-dev`, Expo-Web-Export nach `api/public/app/`, Upload per rsync/SFTP, danach `migrate --force` und `optimize`.
- [ ] Kurze Anleitung `docs/DEPLOY.md`: Subdomain + Dokumentenwurzel `api/public`, PHP-Version, Datenbank, `.env`, Cronjob-Zeile, SMTP.

## Abnahme

1. `php artisan test` grün, inklusive Mandanten-Test.
2. Einladung per `php artisan invite:create` → Mail mit Link → Registrierung in der App → Login funktioniert auf Android (APK) und im Browser.
3. Leere Übersicht sieht aus wie das Design (Farben, Schriften, Tab-Bar, Abstände).
4. `https://abo.codekorb.de/api/v1/health` antwortet mit `{"status":"ok"}`.

## Noch zu klären (vor oder während M1)

- Erlaubt das Netcup-Paket SSH und minütliche Cronjobs? Davon hängt ab, ob das Deploy-Skript per SSH oder nur per SFTP läuft.
- App-Name und Paket-ID für Android (Vorschlag: `de.codekorb.abo`).
