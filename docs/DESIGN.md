# Abo-Tracker · Design-Vorgaben

Dark Mode, ruhige Flächen, eine Akzentfarbe (Blau) für Aktionen und eine Signalfarbe (Orange) nur für Handlungsbedarf. Die wichtigste Zahl steht immer oben, darunter das, was als Nächstes eine Entscheidung braucht, danach Details.

- **Live-Canvas (Referenz, klickbar im Play-Modus):** https://claude.ai/artifact/6AxQG2UrX3rDWgqo8Lu3iV
- **Quelltext der Screens:** `design/screens/*.dc.html` – Inline-Styles mit exakten Maßen und Farben. Die Dateien laufen nicht eigenständig (sie brauchen die Canvas-Runtime), sind aber als Maß- und Strukturvorlage gedacht. Die `class Component … renderVals()`-Blöcke enthalten Beispieldaten und das gewünschte Verhalten.
- **Tokens als Code:** `design/theme.ts`

## Farben

| Token | Wert | Verwendung |
| --- | --- | --- |
| `bg` | `#0E1014` | App-Hintergrund |
| `surface` | `#171A21` | Karten, Eingabefelder, Listen-Container |
| `surfaceBar` | `#12151B` | Tab-Bar, fixierte Footer |
| `border` | `#22262F` | Kartenrahmen |
| `borderStrong` | `#2A2F3A` | Eingabefelder, Icon-Buttons |
| `divider` | `#1E222A` | Trennlinien in Listen |
| `text` | `#F2F3F5` | Primärtext |
| `textMuted` | `#A0A7B4` | Sekundärtext, Labels (Kontrast > 4,5:1 auf `surface`) |
| `accent` | `#8EA8FF` | Primärbuttons, aktive Tabs, Links, Auswahl |
| `accentHover` | `#B4C5FF` | Link-Hover (Web) |
| `onAccent` | `#0E1014` | Text auf `accent` |
| `accentSurface` / `accentBorder` / `accentText` | `#1A2033` / `#34406A` / `#B4C5FF` | Hervorgehobene Info-Karten (z. B. „pro Monat“) |
| `warn` | `#FFB25C` | **nur** Fristen, Preiserhöhungen, „Rhythmus prüfen“ |
| `warnSurface` / `warnBorder` / `warnText` | `#2A2013` / `#6B4A1F` / `#D9CFBF` | Fristen-Karten, Hinweisboxen |
| `onWarn` | `#1A1208` | Text auf `warn`-Button |
| `success` / `successSurface` / `successBorder` | `#5EE6B0` / `#15302A` / `#2B5A4D` | Bestätigungen, „Oft“ |
| `danger` / `dangerSurface` / `dangerBorder` | `#FF8A9A` / `#2E161B` / `#6B2F3A` | „Nie“, Minusstand, Löschen |

**Kategoriefarben** (Icon-Kachel, Balken, Punkt; Text auf Kachel = `onAccent`):

| Kategorie | Farbe |
| --- | --- |
| Telefon & Internet | `#8EA8FF` |
| Streaming | `#B5E36B` |
| Sport | `#4FD1C5` |
| Software & Cloud | `#C9A7FF` |
| Versicherung | `#F48FB1` |
| Mobilität | `#6B7385` |
| Sonstiges | `#9AA3B5` |

Regel: Keine Kategorie bekommt Orange. Kategoriefarbe nie als einziger Bedeutungsträger – immer Name daneben.

## Typografie

- **Space Grotesk** (500/600/700) für Überschriften und große Zahlen, **Inter** (400/500/600/700) für alles andere. In Expo über `@expo-google-fonts/space-grotesk` und `@expo-google-fonts/inter`.
- Alle Beträge mit `fontVariant: ['tabular-nums']`.

| Stil | Schrift | Größe / Gewicht | Einsatz |
| --- | --- | --- | --- |
| `display` | Space Grotesk | 46 / 600, letterSpacing −3 % | Gesamtsumme Übersicht |
| `displayM` | Space Grotesk | 34–40 / 600 | Summen auf Unterseiten |
| `h1` | Space Grotesk | 28 / 600 (Hauptscreens), 22–24 / 600 (Unterseiten) | Screen-Titel |
| `h2` | Inter | 16 / 600 | Abschnittsüberschriften |
| `body` | Inter | 15 / 600 (Listentitel), 14 / 400–500 | Fließtext, Zeilen |
| `small` | Inter | 13 / 400–500 | Labels, Meta |
| `caption` | Inter | 11–12 / 500 | Tab-Labels, Kategoriekürzel |

## Abstände, Radien, Größen

- Seitenrand 20 px; Abstände 8 / 10 / 12 / 14 / 16; zwischen Abschnitten 22 px.
- Radien: Segment-Buttons 9–10, Eingaben/Buttons 12, Icon-Buttons 14, Listenkarten 16–18, Hero-Karten 20–24, Handy-Rahmen 44.
- **Touch-Ziele ≥ 44 × 44 px.** Primärbutton 52 px hoch, Sekundärbutton 44 px.
- Icon-Kachel für Abos: 42 × 42 (Liste), 56 × 56 (Detail), 72 × 72 (Check); Initiale in Space Grotesk 700.

## Komponenten

| Komponente | Aufbau |
| --- | --- |
| `Card` | `surface`, 1 px `border`, Radius 16–24, Padding 14–18 |
| `HeroTotal` | Label + Monat/Jahr-Segment, `display`-Zahl, Untertitel, gestapelter Kategoriebalken (10 px hoch, 3 px Lücken), 3-spaltige Legende |
| `Segmented` | Container `bg` + Rahmen, Padding 3; aktiv = `accent` mit `onAccent`; Höhe 44 |
| `DeadlineCard` | `warnSurface` + `warnBorder`, Warn-Icon + „Kündbar bis …“ in `warn`, Name, Meta in `warnText` |
| `SubscriptionRow` | Icon-Kachel · Name (15/600) + Meta (12, muted) · Betrag rechts (15/600, tabular) · Trennlinie `divider` |
| `PrimaryButton` / `SecondaryButton` / `WarnButton` | 52 / 44 px, Radius 14–16; Sekundär = transparent + `borderStrong` |
| `Switch` | 48 × 28, Knopf 22; an = `accent`, aus = `#3A404D` |
| `Checkbox-Row` | ganze Zeile tippbar (min. 64 px), ausgewählt = `#171D2E` + Rahmen `#3A4878` |
| `TabBar` | 84 px, `surfaceBar`, 4 Tabs + zentraler Plus-Button (52 × 52, Radius 18, `accent`, leicht angehoben) |
| `StepProgress` | Segmente 4 px hoch, erledigt = `accent`, aktuell = `#4A5A8F`, offen = `borderStrong` |

## Screens

| Datei | Screen | Zweck & Verhalten | Meilenstein |
| --- | --- | --- | --- |
| `Main.dc.html` | Übersicht (Tab) | Gesamtsumme mit Monat/Jahr-Umschalter, Kategorie-Anteile, horizontale Leiste „Demnächst“ (Fristen zuerst, orange), größte Posten | M2 |
| `Detail.dc.html` | Abo-Detail | Fristen-Banner mit „Kündigung vormerken“/„Behalten“, Kosten als Abbuchung / pro Monat / pro Tag, Zeitleiste Laufzeit, Erinnerung, Zahlungsweg, Preisverlauf | M2 |
| `Add.dc.html` | Abo hinzufügen | Suche + 8 Vorlagen (ohne Preise), Formular: Name, Betrag, nächste Abbuchung, Rhythmus, Kategorie, Kündigungsfrist, Erinnerung; Live-Umrechnung im Footer; Speichern erst aktiv bei Name + Betrag | M2 |
| `Import.dc.html` | CSV-Import, Schritt 2 von 3 | erkannte Zahlungen als Checkbox-Zeilen, „Rhythmus prüfen“ orange, Duplikate ausgegraut; Footer mit neuer Monatssumme | M4 |
| `Preis.dc.html` | Preisänderungen | Summe der Erhöhungen, je Änderung alt → neu mit Prozent, „Preis übernehmen“/„Kündigen prüfen“, Verlauf seit Vertragsbeginn | M4/M5 |
| `Kuendigung.dc.html` | Kündigungsassistent | Frist-Hinweis, Schreiben mit Platzhaltern, Versandweg (online / E-Mail / PDF); **zwei getrennte Schritte:** erst zum Anbieter, dann „Ich habe gekündigt“; Bestätigungsansicht mit Rückgängig | M5 |
| `Check.dc.html` | Lohnt sich das noch? | ein Abo pro Karte, Oft/Selten/Nie/Überspringen, Fortschritt, Kandidatenliste mit Sparpotenzial pro Jahr | M5 |
| `Monat.dc.html` | Monatsansicht (Tab Kalender) | Monatsraster, Tagesfarbe nach Betrag (< 30 / 30–100 / > 100 €), heute markiert, Tag antippen → Einzelposten | M6 |
| `Ruecklage.dc.html` | Rücklage (Tab Statistik) | monatliche Rücklage, Stand über 12 Monate (positiv blau, negativ rot), Hinweis Startpuffer, nächste Jahreszahlungen | M6 |
| `Kalender.dc.html` | Kalender-Abo (Einstellungen) | iCal-Link kopieren/neu erzeugen, 4 Schalter, Live-Vorschau, Hinweis für Nextcloud | M6 |
| `Desktop.dc.html` | Desktop-Übersicht (Web) | Seitenleiste, 4 Kennzahlen, Verlauf 12 Monate, Kategorien, durchsuchbare Tabelle mit „Kündbar bis“ | M6 |

## Navigation

- Tabs (Handy): **Übersicht** · **Abos** · (+) **Hinzufügen** · **Kalender** (→ Monatsansicht) · **Statistik** (→ Rücklage, später Verlauf).
- Glocke oben rechts in der Übersicht → Preisänderungen/Hinweise (Badge = Anzahl offener Hinweise).
- Fristen-Karte → Abo-Detail → Kündigungsassistent.
- Desktop (Web ab ~1024 px): Seitenleiste statt Tab-Bar mit denselben Bereichen.

## UX-Regeln aus dem Review

1. Orange ausschließlich für Handlungsbedarf.
2. Touch-Ziele ≥ 44 px, auch für Segment-Buttons und Rhythmus-Auswahl.
3. Keine Aktion, die zwei Dinge auf einmal tut (Beispiel Kündigung: Anbieter öffnen ≠ als gekündigt markieren).
4. Keine Sackgassen: jeder Screen hat Zurück oder Schließen, und jede Funktion ist aus der Navigation erreichbar.
5. Vorlagen enthalten keine Preise (veralten schnell).
6. Beträge auf dem Sperrbildschirm (Push) nicht anzeigen.

## Noch nicht gestaltet (beim Bauen ergänzen)

- **Leerer Zustand / Onboarding:** Übersicht ohne Abos → Karte mit „CSV importieren“ und „Erstes Abo anlegen“.
- **Abo-Liste (Tab „Abos“):** Suche, Filter nach Kategorie und Status, Sortierung nach Preis / nächster Abbuchung / Name.
- **Gekündigte und pausierte Abos:** gedimmt (Opacity ~0,6) mit Status-Badge, nicht in Summen.
- **Login, Registrierung per Einladung, Passwort vergessen, Einstellungen.**
- **Import Schritt 1 (Datei wählen + Bankformat) und Schritt 3 (Ergebnis).**
- Lade- und Fehlerzustände (Skeletons in `surface`, Fehler als Karte mit Wiederholen).
