# Entscheidungen

Punkte, die SPEC.md und DESIGN.md offen ließen und die mit dem Nutzer geklärt wurden (Stand: 06.11.2026, M1–M6 umgesetzt).

## Arbeitsweise

- **Commits ohne `Co-Authored-By`-Zeile** (ausdrücklicher Wunsch). Commit-Mail: christian.demski@outlook.com.
- Vor jedem Meilenstein kurz planen, offene Punkte nachfragen, dann in kleinen lauffähigen Commits arbeiten.
- Lokal: bevorzugt DDEV (`.ddev/`, PHP 8.5, MySQL 8.4, Mailpit), siehe README. Ohne DDEV: PHP 8.5 über scoop (`~/scoop/shims/php.exe`).

## M2 · Abos & Übersicht

- „Demnächst“: orange Fristkarten nur für Abos mit Mindestlaufzeit/automatischer Verlängerung; jederzeit kündbare Abos erscheinen als normale graue Abbuchungskarten.
- Erinnerung pro Abo an-/abschaltbar (`remind`, Standard an); die Abstände gelten global pro Nutzer.
- Rhythmus-Auswahl: monatlich / vierteljährlich / jährlich plus „Anderer Rhythmus“ (wöchentlich, halbjährlich, alle N Wochen/Monate/Jahre).
- Betragsänderungen legen eine angenommene manuelle Preisänderung an (Preisverlauf auf der Detailseite).

## Fristen (Korrektur nach Browser-Test, 06.11.2026)

- `term_end_date` ist der **Verlängerungstag** (erster Tag der neuen Laufzeit); der Vertrag läuft bis zum Vortag.
- `cancel_by` = Verlängerungstag − Kündigungsfrist − 1 Tag, damit die volle Frist vor Vertragsende liegt. Beispiel: Vertrag endet 30.11., Frist 1 Monat → Kündigung muss bis 31.10. da sein.
- „Behalten“ gilt für die Laufzeit mit `term_end_date <= kept_until`, nicht für Fristen vor `kept_until`.

## M3 · Erinnerungen

- Mail: eine Sammel-Mail pro Nutzer und Lauf; Push: einer pro Abo, bei mehr als 3 ein zusammenfassender Push. Deduplizierung in `sent_reminders` weiter pro Abo/Abstand/Kanal.
- Abstände: feste Mehrfachauswahl aus 30, 14, 7, 3, 1 Tagen; mindestens einer; Standard 14 + 3.
- „Abbuchung morgen“: standardmäßig aus; wenn an, nur für Abos mit Rhythmus seltener als monatlich.

## M4 · CSV-Import

- Einzelbuchungen werden nur dann Kandidaten, wenn es SEPA-Lastschriften mit Gläubiger-ID und Mandatsreferenz sind („Rhythmus prüfen“).
- Nicht übernommene Kandidaten werden direkt nach „Übernehmen“ gelöscht, spätestens nach 24 Stunden.
- **Offen:** Der Sparkasse-Adapter (CSV-CAMT V2) ist nur gegen eine synthetische Datei getestet. Für die Abnahme fehlt noch ein echter, anonymisierter Export.

## M5 · Sparen

- Absenderadresse wird im Konto gespeichert; Kundennummer und Kündigungs-Mail des Anbieters als optionale Felder am Abo.
- Keine echte Erinnerung nach „Ich habe gekündigt“, nur ein Hinweis in der Zeitleiste.
- Quartals-Check: wegklickbare Karte in der Übersicht (wenn letzter Check > 3 Monate her) plus fester Link im Tab „Abos“; keine Benachrichtigungen dafür.
- Banner: „Kündigung vormerken“ öffnet den Assistenten; „Behalten“ blendet Banner, orange Karte und Fristen-Erinnerungen bis zur nächsten Verlängerung aus (rücknehmbar auf der Detailseite).

## M6 · Überblick

- Monatsansicht: Tagesfarben in drei Blaustufen (< 30 / 30–100 / > 100 €), **nicht** Orange wie in `Monat.dc.html` – Orange bedeutet nur Handlungsbedarf.
- iCal-Feed: Standard Abbuchungen an, Fristen an, Vorab-Erinnerungen (14 Tage) aus, **Betrag im Titel aus** (Datenschutz).
- iCal-Link wird nur direkt nach dem Erzeugen angezeigt (gespeichert ist nur der SHA-256-Hash); danach nur neu erzeugen oder abschalten. Route: `/api/v1/ical/{token}.ics`.
- Statistik-Verlauf wird rekonstruiert (Startdatum, Kündigungsende, angenommene Preisänderungen), keine Snapshots. Abos ohne Startdatum zählen für alle vergangenen Monate.
- Rücklage-Diagramm: Monate mit Abbuchung grau umrandet statt orange.
- Desktop (Web ab 1024 px): Seitenleiste statt Tab-Leiste, eigene Desktop-Übersicht; Unterseiten mit begrenzter Breite.

## Noch offen

- Browser-Test M4–M6 erledigt (06.11.2026). Rückfragen daraus:
  - Import schlägt einen alten Preis als „Preisänderung“ vor, wenn der Preis nach der letzten Abbuchung im Auszug manuell geändert wurde.
  - Gekündigte Abos fallen sofort aus „Fixkosten pro Monat“; der Kündigungs-Screen sagt aber „Danach fällt es aus deinen Fixkosten“.
  - „Kündbar bis“ bleibt in der Zeitleiste der Detailseite orange, auch wenn „Behalten“ gewählt ist.
  - „Nächste Jahreszahlungen“ (Statistik) listet auch Quartalsabos.
- Echter Sparkasse-Export (siehe M4).
- Server-Einrichtung: Netcup, GitHub-Secrets, Cronjob, EAS/FCM, `EAS_PROJECT_ID` (siehe `docs/DEPLOY.md`).
