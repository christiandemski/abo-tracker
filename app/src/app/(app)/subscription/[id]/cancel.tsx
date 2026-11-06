import { router, useLocalSearchParams } from 'expo-router';
import { CircleCheck, Clock } from 'lucide-react-native';
import { useState } from 'react';
import { Linking, Pressable, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import {
  AppText,
  ErrorState,
  Notice,
  PrimaryButton,
  Screen,
  ScreenHeader,
  SecondaryButton,
  Skeleton,
  TextLink,
} from '@/components';
import { useAuth } from '@/lib/auth';
import { formatCents, formatDate, formatDaysUntil, formatWeekdayLongDate } from '@/lib/format';
import { useSubscription } from '@/lib/queries';
import { saveFile } from '@/lib/saveFile';
import type { CancelMethod, CancellationLetter, Resource, Subscription } from '@/lib/types';
import { invalidate, useQuery } from '@/lib/useQuery';
import { colors, fonts, radius, screenPadding } from '@/theme';

type Way = Exclude<CancelMethod, 'other'>;

const WAYS: { id: Way; title: string; sub: string; cta: string }[] = [
  { id: 'online', title: 'Online beim Anbieter', sub: 'Kündigungsbutton im Kundenkonto öffnen', cta: 'Zum Kundenkonto' },
  { id: 'mail', title: 'Per E-Mail', sub: 'Schreiben als Mail-Entwurf öffnen', cta: 'E-Mail öffnen' },
  { id: 'pdf', title: 'Als PDF', sub: 'Zum Ausdrucken oder als Einschreiben', cta: 'PDF erstellen' },
];

export default function CancelScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const subscription = useSubscription(id);
  const [done, setDone] = useState<Subscription | null>(null);

  const s = done ?? subscription.data;

  return (
    <Screen bare>
      <View style={styles.header}>
        <ScreenHeader overline={s?.name} title={done ? 'Kündigung vermerkt' : 'Kündigen'} nav="back" />
      </View>
      {s && done ? (
        <Done subscription={done} onUndone={() => setDone(null)} />
      ) : s && s.status === 'cancelled' ? (
        <Done subscription={s} onUndone={() => subscription.refetch()} />
      ) : s ? (
        <Assistant subscription={s} onCancelled={setDone} />
      ) : subscription.error ? (
        <View style={styles.padded}>
          <ErrorState message={subscription.error.message} onRetry={subscription.refetch} />
        </View>
      ) : (
        <View style={styles.padded}>
          <Skeleton height={320} />
        </View>
      )}
    </Screen>
  );
}

function Assistant({ subscription: s, onCancelled }: { subscription: Subscription; onCancelled: (s: Subscription) => void }) {
  const { api } = useAuth();
  const insets = useSafeAreaInsets();
  const letter = useQuery(`subscriptions:letter:${s.id}`, async () =>
    (await api.get<Resource<CancellationLetter>>(`subscriptions/${s.id}/cancellation-letter`)).data,
  );
  const [way, setWay] = useState<Way>(s.provider_url ? 'online' : s.provider_email ? 'mail' : 'pdf');
  const [editing, setEditing] = useState(false);
  const [text, setText] = useState<string | null>(null);
  const [busy, setBusy] = useState<'send' | 'done' | null>(null);
  const [error, setError] = useState<string | null>(null);

  const letterText = text ?? letter.data?.text ?? '';
  const current = WAYS.find((w) => w.id === way)!;
  const ctaDisabled = way === 'online' && !s.provider_url;

  async function send() {
    setError(null);
    setBusy('send');
    try {
      if (way === 'online' && s.provider_url) {
        await Linking.openURL(s.provider_url);
      } else if (way === 'mail') {
        const subject = letter.data?.subject ?? `Kündigung ${s.name}`;
        // The mail body is the letter without the postal header
        const body = letterText.split(subject).pop()?.trim() ?? letterText;
        const to = letter.data?.recipient_email ?? '';
        await Linking.openURL(`mailto:${to}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`);
      } else {
        const pdf = await api.download(`subscriptions/${s.id}/cancellation-letter`, { text: letterText });
        await saveFile(pdf, `Kuendigung-${s.name.replace(/[^\p{L}\p{N}]+/gu, '-')}.pdf`, 'application/pdf');
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Das hat nicht geklappt.');
    } finally {
      setBusy(null);
    }
  }

  // Separate step: only the user knows whether the cancellation really went out.
  async function markCancelled() {
    setError(null);
    setBusy('done');
    try {
      const { data } = await api.post<Resource<Subscription>>(`subscriptions/${s.id}/cancel`, { cancel_method: way });
      invalidate('subscriptions', 'dashboard', 'calendar', 'stats', 'usage-check');
      onCancelled(data);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Das hat nicht geklappt.');
      setBusy(null);
    }
  }

  const missing = letter.data?.missing ?? [];

  return (
    <View style={styles.flex}>
      <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
        {s.has_term && s.cancel_by_date ? (
          <View style={styles.deadline} accessibilityRole="alert">
            <Clock size={18} strokeWidth={2} color={colors.warn} />
            <AppText variant="small" color="warnText" style={styles.flex}>
              <AppText variant="small" color="warn" style={styles.semi}>
                Frist: {formatWeekdayLongDate(s.cancel_by_date)}
              </AppText>
              {s.days_until_deadline !== null ? ` · ${formatDaysUntil(s.days_until_deadline)}` : ''}
            </AppText>
          </View>
        ) : (
          <View style={styles.block}>
            <Notice tone="info">
              Jederzeit kündbar{letter.data?.expected_end ? `, voraussichtlich zum ${formatDate(letter.data.expected_end)}` : ''}.
            </Notice>
          </View>
        )}

        {missing.length > 0 && (
          <View style={styles.block}>
            <Notice
              tone="info"
              title="Noch Platzhalter im Schreiben"
              action={
                <View>
                  {missing.includes('address') && <TextLink label="Adresse ergänzen" onPress={() => router.push('/settings')} />}
                  {missing.includes('customer_number') && (
                    <TextLink label="Kundennummer ergänzen" onPress={() => router.push(`/subscription/${s.id}/edit`)} />
                  )}
                </View>
              }
            >
              Ergänze die Angaben oder pass das Schreiben direkt an.
            </Notice>
          </View>
        )}

        <View style={styles.section}>
          <View style={styles.letterHead}>
            <AppText variant="h2" accessibilityRole="header">
              Kündigungsschreiben
            </AppText>
            <TextLink label={editing ? 'Fertig' : 'Bearbeiten'} onPress={() => setEditing((v) => !v)} />
          </View>
          {letter.loading ? (
            <Skeleton height={220} />
          ) : letter.error ? (
            <ErrorState message={letter.error.message} onRetry={letter.refetch} />
          ) : editing ? (
            <TextInput
              value={letterText}
              onChangeText={setText}
              multiline
              accessibilityLabel="Kündigungsschreiben bearbeiten"
              style={[styles.letter, styles.letterInput]}
              textAlignVertical="top"
            />
          ) : (
            <View style={styles.letter}>
              <AppText variant="small" style={styles.letterText}>
                {letterText}
              </AppText>
            </View>
          )}
        </View>

        <View style={styles.section}>
          <AppText variant="h2" style={styles.legend}>
            Versandweg
          </AppText>
          <View accessibilityRole="radiogroup" accessibilityLabel="Versandweg" style={styles.ways}>
            {WAYS.map((w) => {
              const on = w.id === way;
              return (
                <Pressable
                  key={w.id}
                  accessibilityRole="radio"
                  accessibilityState={{ checked: on }}
                  onPress={() => setWay(w.id)}
                  style={[styles.way, on ? styles.wayOn : styles.wayOff]}
                >
                  <View style={[styles.radio, on ? styles.radioOn : styles.radioOff]} />
                  <View style={styles.flex}>
                    <AppText variant="body" style={styles.semi}>
                      {w.title}
                    </AppText>
                    <AppText variant="caption" color="textMuted">
                      {w.id === 'online' && !s.provider_url
                        ? 'Kein Link hinterlegt. Ergänze ihn unter „Bearbeiten“.'
                        : w.id === 'mail' && !s.provider_email
                          ? 'Empfänger trägst du im Mail-Programm ein.'
                          : w.sub}
                    </AppText>
                  </View>
                </Pressable>
              );
            })}
          </View>
        </View>

        {error && (
          <View style={styles.block}>
            <Notice tone="danger">{error}</Notice>
          </View>
        )}
      </ScrollView>

      <View style={[styles.footer, { paddingBottom: Math.max(insets.bottom, 12) + 16 }]}>
        <PrimaryButton label={current.cta} onPress={send} loading={busy === 'send'} disabled={ctaDisabled || letter.loading} />
        <SecondaryButton label="Ich habe gekündigt" onPress={markCancelled} loading={busy === 'done'} />
      </View>
    </View>
  );
}

function Done({ subscription: s, onUndone }: { subscription: Subscription; onUndone: () => void }) {
  const { api } = useAuth();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function undo() {
    setBusy(true);
    setError(null);
    try {
      await api.post(`subscriptions/${s.id}/reactivate`);
      invalidate('subscriptions', 'dashboard', 'calendar', 'stats', 'usage-check');
      onUndone();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Das hat nicht geklappt.');
    } finally {
      setBusy(false);
    }
  }

  const steps = [
    { label: 'Kündigung gesendet', meta: s.cancelled_at ? formatDate(s.cancelled_at) : 'heute', on: true },
    { label: 'Bestätigung abwarten', meta: 'gut aufbewahren', on: false },
    { label: 'Keine Abbuchung mehr prüfen', meta: s.ends_at ? `nach dem ${formatDate(s.ends_at)}` : '', on: false },
  ];

  return (
    <ScrollView contentContainerStyle={styles.scroll}>
      <View style={styles.doneHead}>
        <View style={styles.doneIcon}>
          <CircleCheck size={28} strokeWidth={2} color={colors.success} />
        </View>
        <AppText variant="body" color="textMuted" style={styles.center}>
          {s.ends_at ? (
            <>
              Das Abo läuft noch bis{' '}
              <AppText variant="body" style={styles.semi}>
                {formatDate(s.ends_at)}
              </AppText>
              . Danach fällt es aus deinen Fixkosten.
            </>
          ) : (
            'Das Abo zählt nicht mehr zu deinen Fixkosten.'
          )}
        </AppText>
      </View>

      <View style={[styles.section, styles.timeline]}>
        {steps.map((step, i) => (
          <View key={step.label} style={[styles.stepRow, i < steps.length - 1 && styles.divider]}>
            <View style={[styles.stepDot, { backgroundColor: step.on ? colors.success : colors.checkboxOff }]} />
            <AppText variant="body" style={styles.flex}>
              {step.label}
            </AppText>
            <AppText variant="caption" color="textMuted">
              {step.meta}
            </AppText>
          </View>
        ))}
      </View>

      <View style={[styles.section, styles.savings]}>
        <AppText variant="small" color="success">
          Du sparst
        </AppText>
        <AppText variant="displayM" amount color="success">
          {formatCents(s.yearly_cents)} / Jahr
        </AppText>
      </View>

      {error && (
        <View style={styles.block}>
          <Notice tone="danger">{error}</Notice>
        </View>
      )}

      <View style={[styles.section, styles.ways]}>
        <PrimaryButton label="Zur Übersicht" onPress={() => router.replace('/')} />
        <SecondaryButton label="Rückgängig" onPress={undo} loading={busy} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  header: {
    paddingHorizontal: screenPadding - 4,
  },
  padded: {
    paddingHorizontal: screenPadding,
    marginTop: 16,
  },
  scroll: {
    paddingHorizontal: screenPadding,
    paddingBottom: 32,
  },
  flex: {
    flex: 1,
  },
  semi: {
    fontFamily: fonts.bodySemi,
  },
  center: {
    textAlign: 'center',
  },
  block: {
    marginTop: 16,
  },
  section: {
    marginTop: 22,
  },
  deadline: {
    marginTop: 16,
    padding: 12,
    borderRadius: radius.iconButton,
    borderWidth: 1,
    borderColor: colors.warnBorder,
    backgroundColor: colors.warnSurface,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  letterHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  letter: {
    padding: 16,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  letterInput: {
    minHeight: 280,
    color: colors.text,
    fontFamily: fonts.body,
    fontSize: 13,
    lineHeight: 19,
    borderColor: colors.accent,
  },
  letterText: {
    lineHeight: 19,
  },
  legend: {
    marginBottom: 10,
  },
  ways: {
    gap: 8,
  },
  way: {
    minHeight: 60,
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderRadius: radius.iconButton,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  wayOn: {
    backgroundColor: colors.surfaceSelected,
    borderWidth: 1.5,
    borderColor: colors.accent,
  },
  wayOff: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
  },
  radio: {
    width: 20,
    height: 20,
    borderRadius: 99,
  },
  radioOn: {
    borderWidth: 6,
    borderColor: colors.accent,
    backgroundColor: colors.bg,
  },
  radioOff: {
    borderWidth: 2,
    borderColor: colors.checkboxOff,
  },
  footer: {
    paddingTop: 12,
    paddingHorizontal: screenPadding,
    gap: 10,
    backgroundColor: colors.surfaceBar,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  doneHead: {
    marginTop: 24,
    alignItems: 'center',
    gap: 12,
  },
  doneIcon: {
    width: 56,
    height: 56,
    borderRadius: 18,
    backgroundColor: colors.successSurface,
    alignItems: 'center',
    justifyContent: 'center',
  },
  timeline: {
    borderRadius: 18,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    paddingHorizontal: 14,
  },
  stepRow: {
    minHeight: 52,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  stepDot: {
    width: 10,
    height: 10,
    borderRadius: 99,
  },
  divider: {
    borderBottomWidth: 1,
    borderBottomColor: colors.divider,
  },
  savings: {
    padding: 16,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: colors.successBorder,
    backgroundColor: colors.successSurface,
    gap: 4,
  },
});
