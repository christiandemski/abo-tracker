import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';

import {
  AppText,
  CategoryTile,
  ErrorState,
  Notice,
  Screen,
  ScreenHeader,
  SectionHeader,
  SkeletonList,
  TextLink,
} from '@/components';
import { useAuth } from '@/lib/auth';
import { formatCents, formatDate, formatPercent, formatShortDate } from '@/lib/format';
import type { PriceChangeItem, PriceChangesResponse } from '@/lib/types';
import { invalidate, useQuery } from '@/lib/useQuery';
import { colors, fonts, radius, screenPadding } from '@/theme';

export default function PriceChangesScreen() {
  const { api } = useAuth();
  const query = useQuery('price-changes', () => api.get<PriceChangesResponse>('price-changes'));

  return (
    <Screen bare>
      <View style={styles.header}>
        <ScreenHeader title="Preisänderungen" nav="back" />
      </View>
      <ScrollView
        contentContainerStyle={styles.scroll}
        refreshControl={<RefreshControl refreshing={query.refreshing && !query.loading} onRefresh={query.refetch} tintColor={colors.accent} />}
      >
        {query.loading ? (
          <View style={styles.block}>
            <SkeletonList rows={3} />
          </View>
        ) : query.data ? (
          <Content data={query.data} onChanged={query.refetch} />
        ) : query.error ? (
          <View style={styles.block}>
            <ErrorState message={query.error.message} onRetry={query.refetch} />
          </View>
        ) : null}
      </ScrollView>
    </Screen>
  );
}

function Content({ data, onChanged }: { data: PriceChangesResponse; onChanged: () => Promise<void> }) {
  const { meta } = data;
  const increases = meta.increase_count;

  return (
    <>
      {data.data.length > 0 ? (
        <View style={[styles.summary, increases > 0 ? styles.summaryWarn : styles.summaryNeutral]} accessibilityRole="summary">
          <AppText variant="small" color={increases > 0 ? 'warnText' : 'textMuted'}>
            {increases === 1 ? '1 Erhöhung' : `${increases} Erhöhungen`}
            {meta.pending_count > 0 ? ` · ${meta.pending_count} offen` : ''}
          </AppText>
          <AppText variant="displayM" amount color={increases > 0 ? 'warn' : 'text'}>
            + {formatCents(meta.monthly_increase_cents)} / Monat
          </AppText>
          <AppText variant="small" color={increases > 0 ? 'warnText' : 'textMuted'}>
            Das sind {formatCents(meta.yearly_increase_cents)} mehr im Jahr.
          </AppText>
        </View>
      ) : (
        <View style={styles.block}>
          <Notice tone="success" title="Keine offenen Preisänderungen">
            Neue Preise erkennt der Abo-Tracker beim CSV-Import. Angekündigte Erhöhungen trägst du in den Abo-Details ein.
          </Notice>
        </View>
      )}

      {data.data.length > 0 && (
        <View style={styles.section}>
          <SectionHeader title="Neu" />
          <View style={styles.cards}>
            {data.data.map((pc) => (
              <ChangeCard key={pc.id} change={pc} onChanged={onChanged} />
            ))}
          </View>
        </View>
      )}

      {meta.trends.length > 0 && (
        <View style={styles.section}>
          <SectionHeader title="Seit Vertragsbeginn" />
          <View style={styles.trends}>
            {meta.trends.map((t, i) => (
              <Pressable
                key={t.subscription_id}
                accessibilityRole="button"
                onPress={() => router.push(`/subscription/${t.subscription_id}`)}
                style={[styles.trendRow, i < meta.trends.length - 1 && styles.divider]}
              >
                <AppText variant="body" style={styles.flex} numberOfLines={1}>
                  {t.name}
                </AppText>
                <AppText
                  variant="body"
                  amount
                  color={t.percent !== null && t.percent > 0 ? 'warn' : 'textMuted'}
                  style={t.percent !== null && t.percent > 0 && styles.semi}
                >
                  {t.percent === null || t.percent === 0 ? 'unverändert' : formatPercent(t.percent)}
                </AppText>
              </Pressable>
            ))}
          </View>
        </View>
      )}
    </>
  );
}

function ChangeCard({ change: pc, onChanged }: { change: PriceChangeItem; onChanged: () => Promise<void> }) {
  const { api } = useAuth();
  const [busy, setBusy] = useState<'accept' | 'dismiss' | null>(null);
  const [error, setError] = useState<string | null>(null);
  const increase = pc.diff_cents > 0;
  const pending = pc.status === 'pending';

  const origin =
    pc.source === 'import'
      ? `abgebucht am ${formatShortDate(pc.effective_date)} · Kontoauszug`
      : `${pc.in_future ? 'angekündigt ab' : 'gültig seit'} ${formatDate(pc.effective_date)} · manuell`;

  async function decide(action: 'accept' | 'dismiss') {
    setBusy(action);
    setError(null);
    try {
      await api.post(`price-changes/${pc.id}/${action}`);
      invalidate('subscriptions', 'dashboard', 'calendar', 'stats');
      await onChanged();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Das hat nicht geklappt.');
      setBusy(null);
    }
  }

  return (
    <View style={styles.card}>
      <View style={styles.cardTop}>
        <CategoryTile name={pc.subscription.name} color={pc.subscription.category?.color} />
        <View style={styles.flex}>
          <AppText variant="rowTitle" numberOfLines={1}>
            {pc.subscription.name}
          </AppText>
          <AppText variant="caption" color="textMuted">
            {origin}
          </AppText>
        </View>
        {pc.percent !== null && (
          <AppText variant="rowTitle" amount color={increase ? 'warn' : 'success'}>
            {formatPercent(pc.percent)}
          </AppText>
        )}
      </View>

      <View style={styles.prices}>
        <AppText variant="body" color="textMuted" amount style={styles.old}>
          {formatCents(pc.old_cents)}
        </AppText>
        <AppText variant="h2" amount>
          {formatCents(pc.new_cents)}
        </AppText>
        <AppText variant="caption" color="textMuted">
          {pc.subscription.rhythm_label}
        </AppText>
      </View>

      {error && <Notice tone="danger">{error}</Notice>}

      {pending ? (
        <View style={styles.actions}>
          <Pressable
            accessibilityRole="button"
            disabled={busy !== null}
            onPress={() => decide('accept')}
            style={({ pressed }) => [styles.acceptButton, pressed && styles.pressed]}
          >
            <AppText variant="body" color="onWarn" style={styles.semi}>
              {busy === 'accept' ? 'Wird übernommen …' : 'Preis übernehmen'}
            </AppText>
          </Pressable>
          <Pressable
            accessibilityRole="button"
            disabled={busy !== null}
            onPress={() => decide('dismiss')}
            style={({ pressed }) => [styles.dismissButton, pressed && styles.pressed]}
          >
            <AppText variant="body" style={styles.medium}>
              {busy === 'dismiss' ? 'Wird verworfen …' : 'Verwerfen'}
            </AppText>
          </Pressable>
        </View>
      ) : (
        increase && (
          <AppText variant="small" color="warnText">
            Bei Preiserhöhungen besteht oft ein Sonderkündigungsrecht. Prüf die Ankündigung deines Anbieters.
          </AppText>
        )
      )}

      <TextLink label="Kündigen prüfen" onPress={() => router.push(`/subscription/${pc.subscription.id}`)} />
    </View>
  );
}

const styles = StyleSheet.create({
  header: {
    paddingHorizontal: screenPadding - 4,
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
  medium: {
    fontFamily: fonts.bodyMedium,
  },
  block: {
    marginTop: 16,
  },
  section: {
    marginTop: 22,
  },
  summary: {
    marginTop: 16,
    padding: 18,
    borderRadius: 24,
    borderWidth: 1,
    gap: 6,
  },
  summaryWarn: {
    backgroundColor: colors.warnSurface,
    borderColor: colors.warnBorder,
  },
  summaryNeutral: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
  },
  cards: {
    gap: 10,
  },
  card: {
    padding: 16,
    gap: 12,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  cardTop: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  prices: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: 10,
  },
  old: {
    textDecorationLine: 'line-through',
  },
  actions: {
    flexDirection: 'row',
    gap: 8,
  },
  acceptButton: {
    flex: 1,
    height: 44,
    borderRadius: radius.input,
    backgroundColor: colors.warn,
    alignItems: 'center',
    justifyContent: 'center',
  },
  dismissButton: {
    flex: 1,
    height: 44,
    borderRadius: radius.input,
    borderWidth: 1,
    borderColor: colors.borderStrong,
    alignItems: 'center',
    justifyContent: 'center',
  },
  pressed: {
    opacity: 0.85,
  },
  trends: {
    borderRadius: 18,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    paddingHorizontal: 14,
  },
  trendRow: {
    minHeight: 48,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  divider: {
    borderBottomWidth: 1,
    borderBottomColor: colors.divider,
  },
});
