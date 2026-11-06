import AsyncStorage from '@react-native-async-storage/async-storage';
import * as DocumentPicker from 'expo-document-picker';
import { router } from 'expo-router';
import { FileText, FileUp, ShieldCheck, TriangleAlert } from 'lucide-react-native';
import { useEffect, useMemo, useState } from 'react';
import { Platform, ScrollView, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import {
  AppText,
  Card,
  CheckboxRow,
  Chip,
  ErrorState,
  Notice,
  PrimaryButton,
  Screen,
  ScreenHeader,
  SecondaryButton,
  SkeletonList,
  StepProgress,
  TextLink,
} from '@/components';
import { useAuth } from '@/lib/auth';
import { formatCents } from '@/lib/format';
import type { BankFormat, ImportApplyResult, ImportBatch, ImportCandidate, Resource } from '@/lib/types';
import { invalidate, useQuery } from '@/lib/useQuery';
import { colors, fonts, radius, screenPadding } from '@/theme';

const FORMAT_KEY = 'abo-tracker.bank-format';

type Step =
  | { step: 1 }
  | { step: 2; batch: ImportBatch }
  | { step: 3; result: ImportApplyResult; priceChanges: number };

export default function ImportScreen() {
  const [state, setState] = useState<Step>({ step: 1 });

  const subtitle = state.step === 1 ? 'Datei wählen' : state.step === 2 ? 'Prüfen' : 'Fertig';
  const title = state.step === 3 ? 'Import abgeschlossen' : state.step === 2 ? 'Abos aus Kontoauszug' : 'CSV importieren';

  return (
    <Screen bare>
      <View style={styles.header}>
        <ScreenHeader overline={`Schritt ${state.step} von 3 · ${subtitle}`} title={title} nav="close" />
        <View style={styles.progress}>
          <StepProgress step={state.step} total={3} />
        </View>
      </View>

      {state.step === 1 && <ChooseFile onUploaded={(batch) => setState({ step: 2, batch })} />}
      {state.step === 2 && (
        <Review
          batch={state.batch}
          onBack={() => setState({ step: 1 })}
          onApplied={(result) => setState({ step: 3, result, priceChanges: state.batch.counts.price_change })}
        />
      )}
      {state.step === 3 && <Result result={state.result} priceChanges={state.priceChanges} />}
    </Screen>
  );
}

function ChooseFile({ onUploaded }: { onUploaded: (batch: ImportBatch) => void }) {
  const { api } = useAuth();
  const formats = useQuery('imports:formats', async () => (await api.get<Resource<BankFormat[]>>('imports/formats')).data, { persist: true });
  const [format, setFormat] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Remember the bank format (asked only on the first import)
  useEffect(() => {
    AsyncStorage.getItem(FORMAT_KEY)
      .then((saved) => saved && setFormat((f) => f ?? saved))
      .catch(() => {});
  }, []);

  const selected = format ?? formats.data?.[0]?.key ?? null;
  const current = formats.data?.find((f) => f.key === selected);

  async function pick() {
    if (!selected) return;
    setError(null);
    const result = await DocumentPicker.getDocumentAsync({
      type: ['text/csv', 'text/comma-separated-values', 'text/plain', 'application/vnd.ms-excel', '*/*'],
      copyToCacheDirectory: true,
      multiple: false,
    });
    if (result.canceled || !result.assets?.[0]) return;
    const asset = result.assets[0];

    if (asset.size && asset.size > 2 * 1024 * 1024) {
      setError('Die Datei ist zu groß (höchstens 2 MB). Wähl einen kürzeren Zeitraum.');
      return;
    }

    const form = new FormData();
    form.append('bank_format', selected);
    if (Platform.OS === 'web' && asset.file) {
      form.append('file', asset.file, asset.name);
    } else {
      // React Native's FormData accepts { uri, name, type }
      form.append('file', { uri: asset.uri, name: asset.name, type: asset.mimeType ?? 'text/csv' } as unknown as Blob);
    }

    setBusy(true);
    try {
      const { data } = await api.upload<Resource<ImportBatch>>('imports', form);
      await AsyncStorage.setItem(FORMAT_KEY, selected).catch(() => {});
      onUploaded(data);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Das hat nicht geklappt.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <ScrollView contentContainerStyle={styles.scroll}>
      <AppText variant="h2" style={styles.heading}>
        Deine Bank
      </AppText>
      {formats.data ? (
        <View accessibilityRole="radiogroup" accessibilityLabel="Bankformat" style={styles.chips}>
          {formats.data.map((f) => (
            <Chip key={f.key} label={f.label} selected={f.key === selected} onPress={() => setFormat(f.key)} />
          ))}
        </View>
      ) : formats.error ? (
        <ErrorState message={formats.error.message} onRetry={formats.refetch} />
      ) : (
        <SkeletonList rows={1} />
      )}

      {current && (
        <Card style={styles.block}>
          <AppText variant="rowTitle">So exportierst du die Umsätze</AppText>
          <AppText variant="body" color="textMuted">
            {current.hint}
          </AppText>
        </Card>
      )}

      <View style={[styles.block, styles.privacy]}>
        <ShieldCheck size={20} strokeWidth={1.8} color={colors.success} />
        <AppText variant="small" color="textMuted" style={styles.flex}>
          Die Datei wird nur ausgewertet und danach sofort gelöscht. Gespeichert werden nur erkannte Abos, keine
          Kontonummern und keine anderen Umsätze.
        </AppText>
      </View>

      {error && (
        <View style={styles.block}>
          <Notice tone="danger">{error}</Notice>
        </View>
      )}

      <View style={styles.block}>
        <PrimaryButton label="CSV-Datei wählen" icon={FileUp} onPress={pick} loading={busy} disabled={!selected} />
      </View>
    </ScrollView>
  );
}

function tagFor(c: ImportCandidate): { text: string; warn: boolean } {
  const found = `${c.occurrences}× gefunden`;
  if (c.status === 'duplicate') return { text: 'Bereits angelegt', warn: false };
  if (c.status === 'price_change') return { text: 'Preisänderung erkannt', warn: true };
  if (c.confidence === 'check') return { text: `Rhythmus prüfen · ${found}`, warn: true };
  return { text: [found, c.category?.name].filter(Boolean).join(' · '), warn: false };
}

function Review({ batch, onBack, onApplied }: { batch: ImportBatch; onBack: () => void; onApplied: (r: ImportApplyResult) => void }) {
  const { api } = useAuth();
  const insets = useSafeAreaInsets();
  const candidates = useQuery(`imports:${batch.id}:candidates`, async () => (await api.get<Resource<ImportCandidate[]>>(`imports/${batch.id}/candidates`)).data);
  const [selected, setSelected] = useState<Set<number> | null>(null);
  const [sum, setSum] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Default: every new suggestion is selected
  const effective = useMemo(
    () => selected ?? new Set((candidates.data ?? []).filter((c) => c.status === 'new').map((c) => c.id)),
    [selected, candidates.data],
  );
  const ids = useMemo(() => [...effective].sort((a, b) => a - b), [effective]);

  // Sum of the selection comes from the server (exact, rounded once)
  useEffect(() => {
    let cancelled = false;
    api
      .post<Resource<{ monthly_added_cents: number }>>(`imports/${batch.id}/summary`, { candidate_ids: ids })
      .then((r) => !cancelled && setSum(r.data.monthly_added_cents))
      .catch(() => !cancelled && setSum(null));
    return () => {
      cancelled = true;
    };
  }, [api, batch.id, ids]);

  function toggle(id: number) {
    const next = new Set(effective);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    setSelected(next);
  }

  async function apply() {
    setBusy(true);
    setError(null);
    try {
      const { data } = await api.post<Resource<ImportApplyResult>>(`imports/${batch.id}/apply`, { candidate_ids: ids });
      invalidate('subscriptions', 'dashboard');
      onApplied(data);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Das hat nicht geklappt.');
    } finally {
      setBusy(false);
    }
  }

  const list = candidates.data ?? [];
  const recurring = list.length;
  const count = ids.length;

  return (
    <View style={styles.flex}>
      <ScrollView contentContainerStyle={styles.scroll}>
        <View style={styles.fileCard}>
          <View style={styles.fileIcon}>
            <FileText size={20} strokeWidth={1.8} color={colors.accent} />
          </View>
          <View style={styles.flex}>
            <AppText variant="body" numberOfLines={1} style={styles.semi}>
              {batch.file_name ?? 'Kontoauszug'}
            </AppText>
            <AppText variant="caption" color="textMuted">
              {batch.months === 1 ? '1 Monat ausgewertet' : `${batch.months} Monate ausgewertet`}
            </AppText>
          </View>
          <TextLink label="Ändern" onPress={onBack} />
        </View>

        {candidates.loading ? (
          <View style={styles.block}>
            <SkeletonList rows={4} />
          </View>
        ) : candidates.error ? (
          <View style={styles.block}>
            <ErrorState message={candidates.error.message} onRetry={candidates.refetch} />
          </View>
        ) : recurring === 0 ? (
          <View style={styles.block}>
            <Notice tone="info" title="Keine wiederkehrenden Zahlungen gefunden">
              Im gewählten Zeitraum gab es keine Abbuchungen, die wie ein Abo aussehen. Probier einen längeren Zeitraum,
              am besten ab Januar.
            </Notice>
          </View>
        ) : (
          <>
            <AppText variant="body" color="textMuted" style={styles.block}>
              <AppText variant="body" style={styles.semi}>
                {recurring === 1 ? '1 wiederkehrende Zahlung' : `${recurring} wiederkehrende Zahlungen`}
              </AppText>{' '}
              gefunden. Wähl aus, was du übernehmen möchtest.
            </AppText>

            {batch.counts.price_change > 0 && (
              <View style={[styles.block, styles.priceHint]}>
                <TriangleAlert size={18} strokeWidth={2} color={colors.warn} />
                <AppText variant="small" color="warnText" style={styles.flex}>
                  {batch.counts.price_change === 1
                    ? 'Bei einem vorhandenen Abo hat sich der Preis geändert.'
                    : `Bei ${batch.counts.price_change} vorhandenen Abos hat sich der Preis geändert.`}{' '}
                  Du findest das nach dem Import unter „Preisänderungen“.
                </AppText>
              </View>
            )}

            <View style={[styles.block, styles.list]}>
              {list.map((c) => {
                const tag = tagFor(c);
                return (
                  <CheckboxRow
                    key={c.id}
                    title={c.subscription?.name ?? c.name}
                    tag={tag.text}
                    tagWarn={tag.warn}
                    amount={formatCents(c.amount_cents)}
                    amountMeta={c.rhythm_label}
                    checked={effective.has(c.id)}
                    disabled={c.status !== 'new'}
                    onToggle={() => toggle(c.id)}
                  />
                );
              })}
            </View>
          </>
        )}

        {error && (
          <View style={styles.block}>
            <Notice tone="danger">{error}</Notice>
          </View>
        )}
      </ScrollView>

      <View style={[styles.footer, { paddingBottom: Math.max(insets.bottom, 12) + 16 }]}>
        <View style={styles.footerLine}>
          <AppText variant="small" color="textMuted">
            Neue Fixkosten
          </AppText>
          <AppText variant="small" amount style={styles.semi}>
            {sum === null ? '–' : `+ ${formatCents(sum)} / Monat`}
          </AppText>
        </View>
        <PrimaryButton
          label={count === 0 ? 'Nichts übernehmen' : count === 1 ? '1 Abo übernehmen' : `${count} Abos übernehmen`}
          onPress={apply}
          loading={busy}
          disabled={candidates.loading}
        />
      </View>
    </View>
  );
}

function Result({ result, priceChanges }: { result: ImportApplyResult; priceChanges: number }) {
  return (
    <ScrollView contentContainerStyle={styles.scroll}>
      <Card size="hero" tone="accent" style={styles.block}>
        <AppText variant="small" color="accentText">
          {result.created_count === 1 ? '1 Abo übernommen' : `${result.created_count} Abos übernommen`}
        </AppText>
        <AppText variant="displayM" amount>
          + {formatCents(result.monthly_added_cents)}
        </AppText>
        <AppText variant="small" color="accentText">
          neue Fixkosten pro Monat
        </AppText>
      </Card>

      {result.created.length > 0 && (
        <View style={styles.block}>
          {result.created.map((s) => (
            <View key={s.id} style={styles.createdRow}>
              <AppText variant="body" style={styles.flex} numberOfLines={1}>
                {s.name}
              </AppText>
              <AppText variant="body" color="textMuted" amount>
                {formatCents(s.amount_cents)} {s.rhythm_label}
              </AppText>
            </View>
          ))}
          <AppText variant="caption" color="textMuted" style={styles.note}>
            Prüf bei den neuen Abos Kategorie und Kündigungsfrist. Beim nächsten Import erkennt der Abo-Tracker sie
            automatisch wieder.
          </AppText>
        </View>
      )}

      {priceChanges > 0 && (
        <View style={styles.block}>
          <Notice
            tone="info"
            title={priceChanges === 1 ? '1 Preisänderung erkannt' : `${priceChanges} Preisänderungen erkannt`}
            action={<TextLink label="Preisänderungen ansehen" onPress={() => router.replace('/price-changes')} />}
          >
            Entscheide, ob du den neuen Preis übernimmst.
          </Notice>
        </View>
      )}

      <View style={[styles.block, styles.list]}>
        <PrimaryButton label="Zur Übersicht" onPress={() => router.replace('/')} />
        <SecondaryButton label="Abos ansehen" onPress={() => router.replace('/subscriptions')} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  header: {
    paddingHorizontal: screenPadding - 4,
  },
  progress: {
    paddingHorizontal: 4,
    paddingTop: 12,
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
  heading: {
    marginTop: 22,
    marginBottom: 10,
  },
  chips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  block: {
    marginTop: 16,
  },
  privacy: {
    flexDirection: 'row',
    gap: 10,
    alignItems: 'flex-start',
  },
  fileCard: {
    marginTop: 18,
    padding: 14,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  fileIcon: {
    width: 40,
    height: 40,
    borderRadius: 12,
    backgroundColor: colors.accentSurface,
    alignItems: 'center',
    justifyContent: 'center',
  },
  priceHint: {
    flexDirection: 'row',
    gap: 10,
    padding: 12,
    borderRadius: radius.iconButton,
    borderWidth: 1,
    borderColor: colors.warnBorder,
    backgroundColor: colors.warnSurface,
  },
  list: {
    gap: 8,
  },
  footer: {
    paddingTop: 12,
    paddingHorizontal: screenPadding,
    gap: 10,
    backgroundColor: colors.surfaceBar,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  footerLine: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'baseline',
  },
  createdRow: {
    flexDirection: 'row',
    gap: 12,
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: colors.divider,
  },
  note: {
    marginTop: 10,
  },
});
