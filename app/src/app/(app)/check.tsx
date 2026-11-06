import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';

import {
  AppText,
  CategoryTile,
  ErrorState,
  Notice,
  PrimaryButton,
  Screen,
  ScreenHeader,
  SecondaryButton,
  Skeleton,
  StepProgress,
} from '@/components';
import { useAuth } from '@/lib/auth';
import { formatCents } from '@/lib/format';
import type { Resource, UsageAnswer, UsageCheckState } from '@/lib/types';
import { invalidate, useQuery } from '@/lib/useQuery';
import { colors, fonts, radius, screenPadding } from '@/theme';

const ANSWERS: { value: Exclude<UsageAnswer, 'skip'>; label: string }[] = [
  { value: 'often', label: 'Oft' },
  { value: 'rare', label: 'Selten' },
  { value: 'never', label: 'Nie' },
];

export default function UsageCheckScreen() {
  const { api } = useAuth();
  const query = useQuery('usage-check', async () => (await api.get<Resource<UsageCheckState>>('usage-check/next')).data);
  const [state, setState] = useState<UsageCheckState | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const data = state ?? query.data;

  async function answer(value: UsageAnswer) {
    if (!data?.current) return;
    setBusy(value);
    setError(null);
    try {
      const res = await api.post<Resource<UsageCheckState>>('usage-check', { subscription_id: data.current.id, answer: value });
      setState(res.data);
      invalidate('dashboard', 'calendar', 'stats');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Das hat nicht geklappt.');
    } finally {
      setBusy(null);
    }
  }

  async function restart() {
    setBusy('restart');
    try {
      setState((await api.post<Resource<UsageCheckState>>('usage-check/restart')).data);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Das hat nicht geklappt.');
    } finally {
      setBusy(null);
    }
  }

  const progress = data
    ? data.done
      ? 'fertig'
      : `${Math.min(data.progress.answered + 1, data.progress.total)} von ${data.progress.total}`
    : '';

  return (
    <Screen bare>
      <View style={styles.header}>
        <ScreenHeader overline={`Quartals-Check${progress ? ` · ${progress}` : ''}`} title="Lohnt sich das noch?" nav="close" />
        {data && data.progress.total > 0 && (
          <View style={styles.progress}>
            <StepProgress step={data.progress.answered + 1} total={data.progress.total} />
          </View>
        )}
      </View>

      <ScrollView contentContainerStyle={styles.scroll}>
        {!data ? (
          query.error ? (
            <View style={styles.block}>
              <ErrorState message={query.error.message} onRetry={query.refetch} />
            </View>
          ) : (
            <View style={styles.block}>
              <Skeleton height={320} rounded={24} />
            </View>
          )
        ) : data.progress.total === 0 ? (
          <View style={styles.block}>
            <Notice tone="info" title="Keine aktiven Abos">
              Sobald du Abos angelegt hast, kannst du hier prüfen, welche sich noch lohnen.
            </Notice>
          </View>
        ) : data.current ? (
          <View style={styles.card} accessibilityLiveRegion="polite">
            <CategoryTile name={data.current.name} color={data.current.category?.color} size="hero" />
            <AppText variant="h1Sub" style={styles.center} accessibilityRole="header">
              {data.current.name}
            </AppText>
            <AppText variant="small" color="textMuted" style={styles.center}>
              {formatCents(data.current.amount_cents)} {data.current.rhythm_label}
              {data.current.category ? ` · ${data.current.category.name}` : ''}
            </AppText>
            <AppText variant="body" color="textMuted" style={styles.center}>
              Das sind{' '}
              <AppText variant="body" amount style={styles.semi}>
                {formatCents(data.current.yearly_cents)}
              </AppText>{' '}
              im Jahr.
            </AppText>

            <AppText variant="rowTitle" style={styles.question}>
              Wie oft nutzt du es?
            </AppText>
            <View style={styles.answers}>
              {ANSWERS.map((a) => (
                <Pressable
                  key={a.value}
                  accessibilityRole="button"
                  disabled={busy !== null}
                  onPress={() => answer(a.value)}
                  style={({ pressed }) => [styles.answer, answerStyles[a.value], pressed && styles.pressed]}
                >
                  <AppText variant="rowTitle" color={a.value === 'never' ? 'danger' : a.value === 'often' ? 'success' : 'text'}>
                    {a.label}
                  </AppText>
                </Pressable>
              ))}
            </View>
            <Pressable accessibilityRole="button" disabled={busy !== null} onPress={() => answer('skip')} style={styles.skip}>
              <AppText variant="small" color="textMuted">
                Überspringen
              </AppText>
            </Pressable>
          </View>
        ) : (
          <View style={[styles.block, styles.doneCard]}>
            <AppText variant="small" color="accentText">
              Check abgeschlossen
            </AppText>
            <AppText variant="displayM" amount>
              {formatCents(data.savings_yearly_cents)} / Jahr
            </AppText>
            <AppText variant="small" color="accentText">
              {data.candidates.length > 0
                ? 'möglich, wenn du die Kandidaten unten kündigst.'
                : 'Alle Abos werden genutzt. Gut so!'}
            </AppText>
            <View style={styles.doneActions}>
              <View style={styles.flex}>
                <SecondaryButton label="Neu starten" onPress={restart} loading={busy === 'restart'} />
              </View>
              <View style={styles.flex}>
                <PrimaryButton label="Zur Übersicht" onPress={() => router.replace('/')} />
              </View>
            </View>
          </View>
        )}

        {error && (
          <View style={styles.block}>
            <Notice tone="danger">{error}</Notice>
          </View>
        )}

        {data && data.progress.total > 0 && (
          <View style={styles.section}>
            <View style={styles.candHead}>
              <AppText variant="h2" accessibilityRole="header">
                Kündigungskandidaten
              </AppText>
              <AppText variant="small" amount color="textMuted">
                {formatCents(data.savings_yearly_cents)} / Jahr
              </AppText>
            </View>
            {data.candidates.length === 0 ? (
              <AppText variant="small" color="textMuted">
                Noch keine. Abos, die du selten oder nie nutzt, landen hier.
              </AppText>
            ) : (
              <View style={styles.candidates}>
                {data.candidates.map((c) => (
                  <Pressable
                    key={c.subscription_id}
                    accessibilityRole="button"
                    accessibilityLabel={`${c.name}, ${c.answer === 'never' ? 'nie genutzt' : 'selten genutzt'}, ${formatCents(c.yearly_cents)} pro Jahr, kündigen`}
                    onPress={() => router.push(`/subscription/${c.subscription_id}/cancel`)}
                    style={({ pressed }) => [styles.candRow, pressed && styles.pressed]}
                  >
                    <View style={[styles.tag, c.answer === 'never' ? styles.tagNever : styles.tagRare]}>
                      <AppText variant="caption" color={c.answer === 'never' ? 'danger' : 'warn'} style={styles.semi}>
                        {c.answer === 'never' ? 'Nie' : 'Selten'}
                      </AppText>
                    </View>
                    <AppText variant="body" style={styles.flex} numberOfLines={1}>
                      {c.name}
                    </AppText>
                    <AppText variant="body" amount color="textMuted">
                      {formatCents(c.yearly_cents)}
                    </AppText>
                  </Pressable>
                ))}
              </View>
            )}
          </View>
        )}
      </ScrollView>
    </Screen>
  );
}

const answerStyles = StyleSheet.create({
  often: { borderColor: colors.successBorder, backgroundColor: colors.successSurface },
  rare: { borderColor: colors.borderStrong, backgroundColor: colors.surface },
  never: { borderColor: colors.dangerBorder, backgroundColor: colors.dangerSurface },
});

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
    paddingBottom: 40,
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
    marginTop: 18,
  },
  section: {
    marginTop: 24,
    gap: 10,
  },
  card: {
    marginTop: 18,
    padding: 20,
    borderRadius: 24,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    alignItems: 'center',
    gap: 8,
  },
  question: {
    marginTop: 12,
  },
  answers: {
    flexDirection: 'row',
    gap: 8,
    alignSelf: 'stretch',
  },
  answer: {
    flex: 1,
    height: 52,
    borderRadius: radius.iconButton,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  pressed: {
    opacity: 0.8,
  },
  skip: {
    minHeight: 44,
    justifyContent: 'center',
    paddingHorizontal: 16,
  },
  doneCard: {
    padding: 18,
    borderRadius: 24,
    borderWidth: 1,
    borderColor: colors.accentBorder,
    backgroundColor: colors.accentSurface,
    gap: 6,
  },
  doneActions: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 10,
  },
  candHead: {
    flexDirection: 'row',
    alignItems: 'baseline',
    justifyContent: 'space-between',
  },
  candidates: {
    borderRadius: 18,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    paddingHorizontal: 14,
  },
  candRow: {
    minHeight: 52,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.divider,
  },
  tag: {
    width: 56,
    paddingVertical: 3,
    borderRadius: 8,
    alignItems: 'center',
  },
  tagNever: {
    backgroundColor: colors.dangerSurface,
  },
  tagRare: {
    backgroundColor: colors.warnSurface,
  },
});
