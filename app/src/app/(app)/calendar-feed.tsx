import * as Clipboard from 'expo-clipboard';
import { useState } from 'react';
import { StyleSheet, TextInput, View } from 'react-native';

import {
  AppText,
  ErrorState,
  Notice,
  PrimaryButton,
  Screen,
  ScreenHeader,
  SecondaryButton,
  Skeleton,
  Switch,
  TextLink,
} from '@/components';
import { useAuth } from '@/lib/auth';
import { formatDate, formatShortDate, toIsoDate } from '@/lib/format';
import type { CalendarFeed, CalendarFeedOptions, Resource } from '@/lib/types';
import { useQuery } from '@/lib/useQuery';
import { colors, fonts, radius } from '@/theme';

const OPTIONS: { key: keyof CalendarFeedOptions; title: string; sub: string }[] = [
  { key: 'charges', title: 'Abbuchungen', sub: 'Ganztägiger Termin am Abbuchungstag' },
  { key: 'deadlines', title: 'Kündigungsfristen', sub: 'Letzter Tag, an dem du kündigen kannst' },
  { key: 'reminders', title: 'Vorab-Erinnerungen', sub: 'Zusätzlich 14 Tage vor jeder Frist' },
  { key: 'amounts', title: 'Betrag im Titel', sub: 'z. B. „Netflix · 13,99 €“. Sichtbar für alle, die deinen Kalender sehen.' },
];

export default function CalendarFeedScreen() {
  const { api } = useAuth();
  const query = useQuery('calendar-feed', async () => (await api.get<Resource<CalendarFeed>>('settings/calendar-feed')).data);
  const [state, setState] = useState<CalendarFeed | null>(null);
  // The link is only known right after creating it (the server keeps a hash only)
  const [url, setUrl] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);
  const [busy, setBusy] = useState<string | null>(null);
  const [confirm, setConfirm] = useState<'rotate' | 'off' | null>(null);
  const [error, setError] = useState<string | null>(null);

  const data = state ?? query.data;

  async function run(key: string, fn: () => Promise<Resource<CalendarFeed>>) {
    setBusy(key);
    setError(null);
    try {
      const res = await fn();
      setState(res.data);
      return res.data;
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Das hat nicht geklappt.');
      return null;
    } finally {
      setBusy(null);
    }
  }

  async function createLink() {
    const res = await run('rotate', () => api.post<Resource<CalendarFeed>>('settings/calendar-feed/rotate'));
    setConfirm(null);
    if (res?.url) {
      setUrl(res.url);
      setCopied(false);
    }
  }

  async function switchOff() {
    await run('off', () => api.delete<Resource<CalendarFeed>>('settings/calendar-feed'));
    setConfirm(null);
    setUrl(null);
  }

  async function toggle(key: keyof CalendarFeedOptions, value: boolean) {
    if (!data) return;
    const options = { ...data.options, [key]: value };
    setState({ ...data, options });
    const res = await run(key, () => api.put<Resource<CalendarFeed>>('settings/calendar-feed', { options }));
    if (!res) setState(data);
  }

  async function copy() {
    if (!url) return;
    await Clipboard.setStringAsync(url);
    setCopied(true);
  }

  return (
    <Screen>
      <ScreenHeader overline="Einstellungen" title="Kalender-Abo" nav="back" />
      <View style={styles.content}>
        <AppText variant="body" color="textMuted">
          Abonniere deinen privaten Link in deinem Kalender. Neue Abos und geänderte Fristen erscheinen dort automatisch.
        </AppText>

        {error && <Notice tone="danger">{error}</Notice>}

        {!data ? (
          query.error ? (
            <ErrorState message={query.error.message} onRetry={query.refetch} />
          ) : (
            <Skeleton height={300} rounded={18} />
          )
        ) : (
          <>
            <View style={styles.card} accessibilityLabel="Kalender-Link">
              <AppText variant="small" color="textMuted" style={styles.medium}>
                Privater iCal-Link
              </AppText>

              {url ? (
                <>
                  <TextInput
                    value={url}
                    editable={false}
                    selectTextOnFocus
                    accessibilityLabel="Privater iCal-Link"
                    style={styles.url}
                  />
                  <Notice tone="info">
                    Kopier den Link jetzt. Wir speichern nur eine Prüfsumme davon und können ihn später nicht noch mal anzeigen.
                  </Notice>
                  <PrimaryButton label={copied ? 'Kopiert' : 'Link kopieren'} onPress={copy} />
                </>
              ) : data.active ? (
                <AppText variant="body">
                  Dein Link ist aktiv
                  {data.created_at ? ` (erstellt am ${formatDate(toIsoDate(new Date(data.created_at)))})` : ''}.
                  {data.last_fetched_at
                    ? ` Zuletzt abgerufen am ${formatDate(toIsoDate(new Date(data.last_fetched_at)))}.`
                    : ' Noch von keinem Kalender abgerufen.'}{' '}
                  Aus Sicherheitsgründen können wir ihn nicht noch mal anzeigen.
                </AppText>
              ) : (
                <AppText variant="body" color="textMuted">
                  Noch kein Link erzeugt.
                </AppText>
              )}

              {!data.active ? (
                <PrimaryButton label="Link erzeugen" onPress={createLink} loading={busy === 'rotate'} />
              ) : confirm === 'rotate' ? (
                <View style={styles.confirm}>
                  <AppText variant="small" color="textMuted">
                    Der bisherige Link funktioniert danach nicht mehr. Du musst den neuen in deinem Kalender eintragen.
                  </AppText>
                  <View style={styles.row}>
                    <View style={styles.flex}>
                      <SecondaryButton label="Abbrechen" onPress={() => setConfirm(null)} />
                    </View>
                    <View style={styles.flex}>
                      <PrimaryButton label="Neu erzeugen" onPress={createLink} loading={busy === 'rotate'} />
                    </View>
                  </View>
                </View>
              ) : confirm === 'off' ? (
                <View style={styles.confirm}>
                  <AppText variant="small" color="textMuted">
                    Dein Kalender bekommt danach keine Termine mehr vom Abo-Tracker.
                  </AppText>
                  <View style={styles.row}>
                    <View style={styles.flex}>
                      <SecondaryButton label="Abbrechen" onPress={() => setConfirm(null)} />
                    </View>
                    <View style={styles.flex}>
                      <PrimaryButton label="Abschalten" onPress={switchOff} loading={busy === 'off'} />
                    </View>
                  </View>
                </View>
              ) : (
                <View style={styles.row}>
                  <View style={styles.flex}>
                    <SecondaryButton label="Neuen Link erzeugen" onPress={() => setConfirm('rotate')} />
                  </View>
                  <TextLink label="Abschalten" onPress={() => setConfirm('off')} />
                </View>
              )}
            </View>

            <View style={styles.section}>
              <AppText variant="h2" accessibilityRole="header">
                Was in den Kalender kommt
              </AppText>
              <View style={styles.list}>
                {OPTIONS.map((o, i) => (
                  <View key={o.key} style={[styles.option, i < OPTIONS.length - 1 && styles.divider]}>
                    <View style={styles.flex}>
                      <AppText variant="body" style={styles.medium}>
                        {o.title}
                      </AppText>
                      <AppText variant="caption" color="textMuted">
                        {o.sub}
                      </AppText>
                    </View>
                    <Switch
                      value={data.options[o.key]}
                      onValueChange={(v) => toggle(o.key, v)}
                      accessibilityLabel={o.title}
                      disabled={busy !== null && busy !== o.key}
                    />
                  </View>
                ))}
              </View>
            </View>

            <View style={styles.section}>
              <AppText variant="h2" accessibilityRole="header">
                Vorschau
              </AppText>
              <View style={[styles.list, styles.preview]} accessibilityLiveRegion="polite">
                {data.preview.length === 0 ? (
                  <AppText variant="body" color="textMuted">
                    {Object.values(data.options).some(Boolean) ? 'Keine anstehenden Termine.' : 'Nichts ausgewählt.'}
                  </AppText>
                ) : (
                  data.preview.map((e, i) => (
                    <View key={`${e.date}-${i}`} style={styles.event}>
                      <View
                        style={[
                          styles.bar,
                          {
                            backgroundColor:
                              e.type === 'deadline' ? colors.warn : e.type === 'reminder' ? colors.accent : (e.color ?? colors.accent),
                          },
                        ]}
                      />
                      <AppText variant="small" amount color="textMuted" style={styles.date}>
                        {formatShortDate(e.date)}
                      </AppText>
                      <AppText variant="body" style={styles.flex} numberOfLines={1}>
                        {e.title}
                      </AppText>
                    </View>
                  ))
                )}
              </View>
            </View>

            <AppText variant="small" color="textMuted">
              In Nextcloud: Kalender › „+ Neuer Kalender“ › „Neues Abonnement aus Link“ und den Link einfügen. Google Kalender:
              „Weitere Kalender“ › „Per URL“.
            </AppText>
          </>
        )}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  content: {
    gap: 18,
    marginTop: 12,
  },
  flex: {
    flex: 1,
  },
  medium: {
    fontFamily: fonts.bodyMedium,
  },
  card: {
    gap: 12,
    padding: 16,
    borderRadius: radius.card,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  url: {
    height: 48,
    paddingHorizontal: 12,
    borderRadius: radius.input,
    borderWidth: 1,
    borderColor: colors.borderStrong,
    backgroundColor: colors.bg,
    color: colors.text,
    fontFamily: fonts.body,
    fontSize: 13,
  },
  confirm: {
    gap: 10,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  section: {
    gap: 8,
  },
  list: {
    borderRadius: radius.card,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    paddingHorizontal: 14,
  },
  option: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 10,
  },
  divider: {
    borderBottomWidth: 1,
    borderBottomColor: colors.divider,
  },
  preview: {
    paddingVertical: 10,
    gap: 8,
  },
  event: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    minHeight: 28,
  },
  bar: {
    width: 4,
    height: 20,
    borderRadius: 4,
  },
  date: {
    width: 44,
  },
});
