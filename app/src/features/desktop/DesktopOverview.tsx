import { router } from 'expo-router';
import { Bell, FileUp, Plus, Search } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, TextInput, View } from 'react-native';

import { AppText, IconButton, Notice, SkeletonList, UsageCheckCard } from '@/components';
import { HistoryChart } from '@/components/HistoryChart';
import { formatCents, formatDate, formatLongDate, formatShortDate } from '@/lib/format';
import { useDashboard, useHistory, useSubscriptions } from '@/lib/queries';
import type { Dashboard, Subscription } from '@/lib/types';
import { categoryColors, colors, fonts, radius } from '@/theme';

/** Desktop overview (web ≥ 1024 px): key figures, 12-month history, categories, searchable table. */
export function DesktopOverview({ dashboard }: { dashboard: Dashboard }) {
  const yearly = useDashboard('year');
  const history = useHistory(12);
  const [query, setQuery] = useState('');
  const [q, setQ] = useState('');

  useEffect(() => {
    const t = setTimeout(() => setQ(query.trim()), 250);
    return () => clearTimeout(t);
  }, [query]);

  const list = useSubscriptions({ status: 'active', sort: 'price', q: q || undefined });
  const change = history.data?.change_cents ?? null;
  const longCount = dashboard.converted_count;
  const shortCount = dashboard.subscription_count - longCount;
  const next = dashboard.deadlines.next;

  return (
    <ScrollView contentContainerStyle={styles.page}>
      <View style={styles.head}>
        <View style={styles.flex}>
          <AppText variant="small" color="textMuted">
            Stand: {formatLongDate(new Date())}
          </AppText>
          <AppText variant="h1" accessibilityRole="header">
            Deine Fixkosten
          </AppText>
        </View>
        <IconButton
          icon={Bell}
          accessibilityLabel={dashboard.notices_count > 0 ? `Hinweise, ${dashboard.notices_count} neu` : 'Hinweise'}
          onPress={() => router.push('/price-changes')}
          badge={dashboard.notices_count > 0 ? <View style={styles.badge} /> : undefined}
        />
        <Pressable accessibilityRole="button" onPress={() => router.push('/import')} style={({ pressed }) => [styles.ghost, pressed && styles.pressed]}>
          <FileUp size={16} strokeWidth={2} color={colors.text} />
          <AppText variant="body" style={styles.semi}>
            CSV importieren
          </AppText>
        </Pressable>
        <Pressable accessibilityRole="button" onPress={() => router.push('/add')} style={({ pressed }) => [styles.primary, pressed && styles.pressed]}>
          <Plus size={16} strokeWidth={2.4} color={colors.onAccent} />
          <AppText variant="body" color="onAccent" style={styles.semi}>
            Abo hinzufügen
          </AppText>
        </Pressable>
      </View>

      <View style={styles.kpis} accessibilityLabel="Kennzahlen">
        <Kpi
          label="Pro Monat"
          value={formatCents(dashboard.total_cents)}
          sub={change === null ? ' ' : change === 0 ? 'wie im Vormonat' : `${change > 0 ? '+' : '−'}${formatCents(Math.abs(change))} zum Vormonat`}
        />
        <Kpi label="Pro Jahr" value={yearly.data ? formatCents(yearly.data.total_cents) : '…'} sub="alle aktiven Abos" />
        <Kpi
          label="Aktive Abos"
          value={String(dashboard.subscription_count)}
          sub={`${shortCount} monatlich · ${longCount} seltener`}
        />
        <Kpi
          label="Fristen in 30 Tagen"
          value={String(dashboard.deadlines.count)}
          sub={next ? `nächste: ${next.name} am ${formatShortDate(next.date)}` : 'keine offenen Fristen'}
          warn={dashboard.deadlines.count > 0}
          onPress={next ? () => router.push(`/subscription/${next.subscription_id}`) : undefined}
        />
      </View>

      <UsageCheckCard />

      <View style={styles.split}>
        <View style={[styles.panel, styles.historyPanel]}>
          <View style={styles.panelHead}>
            <AppText variant="h2" accessibilityRole="header">
              Monatliche Fixkosten
            </AppText>
            <AppText variant="caption" color="textMuted">
              letzte 12 Monate
            </AppText>
          </View>
          {history.data ? <HistoryChart history={history.data} height={180} /> : <View style={styles.chartPlaceholder} />}
        </View>

        <View style={[styles.panel, styles.catPanel]}>
          <View style={styles.panelHead}>
            <AppText variant="h2" accessibilityRole="header">
              Nach Kategorie
            </AppText>
            <AppText variant="caption" color="textMuted">
              pro Monat
            </AppText>
          </View>
          {dashboard.categories.map((c) => {
            const max = dashboard.categories[0]?.total_cents || 1;
            return (
              <View key={c.category?.id ?? 'none'} style={styles.cat}>
                <View style={styles.catRow}>
                  <AppText variant="small" style={styles.medium} numberOfLines={1}>
                    {c.label}
                  </AppText>
                  <AppText variant="small" amount color="textMuted">
                    <AppText variant="small" amount color="text">
                      {formatCents(c.total_cents)}
                    </AppText>{' '}
                    · {Math.round(c.share * 100)} %
                  </AppText>
                </View>
                <View style={styles.track}>
                  <View
                    style={[
                      styles.fill,
                      { width: `${(c.total_cents / max) * 100}%`, backgroundColor: c.category?.color ?? categoryColors.other },
                    ]}
                  />
                </View>
              </View>
            );
          })}
        </View>
      </View>

      <View style={styles.panel}>
        <View style={styles.tableHead}>
          <AppText variant="h2" accessibilityRole="header" style={styles.flex}>
            Alle Abos
          </AppText>
          <View style={styles.search}>
            <Search size={16} strokeWidth={2} color={colors.textMuted} />
            <TextInput
              value={query}
              onChangeText={setQuery}
              placeholder="Suchen …"
              placeholderTextColor={colors.placeholder}
              accessibilityLabel="Abos durchsuchen"
              style={styles.searchInput}
            />
          </View>
        </View>

        {list.stale && <Notice tone="info">Keine Verbindung. Du siehst den letzten bekannten Stand.</Notice>}

        <View accessibilityRole="list">
          <View style={[styles.tr, styles.th]} importantForAccessibility="no-hide-descendants">
            {COLUMNS.map((c) => (
              <AppText key={c.key} variant="caption" color="textMuted" style={[styles.cell, { flex: c.flex }, c.right && styles.right]}>
                {c.label}
              </AppText>
            ))}
          </View>
          {list.loading && !list.data ? (
            <SkeletonList rows={5} />
          ) : (list.data ?? []).length === 0 ? (
            <AppText variant="body" color="textMuted" style={styles.empty}>
              {q ? 'Keine Abos gefunden.' : 'Noch keine aktiven Abos.'}
            </AppText>
          ) : (
            (list.data ?? []).map((s) => <TableRow key={s.id} s={s} />)
          )}
        </View>
      </View>
    </ScrollView>
  );
}

const COLUMNS: { key: string; label: string; flex: number; right?: boolean }[] = [
  { key: 'name', label: 'Abo', flex: 2.2 },
  { key: 'cat', label: 'Kategorie', flex: 1.5 },
  { key: 'rhythm', label: 'Rhythmus', flex: 1.1 },
  { key: 'amount', label: 'Betrag', flex: 1, right: true },
  { key: 'monthly', label: 'pro Monat', flex: 1, right: true },
  { key: 'next', label: 'Nächste Abbuchung', flex: 1.3, right: true },
  { key: 'due', label: 'Kündbar bis', flex: 1.3, right: true },
];

function TableRow({ s }: { s: Subscription }) {
  const hasDeadline = s.has_term && s.cancel_by_date !== null;
  const hot = hasDeadline && !s.is_kept && s.days_until_deadline !== null && s.days_until_deadline <= 30;
  const due = hasDeadline ? formatDate(s.cancel_by_date as string) : '—';
  const next = s.next_charge_date ? formatDate(s.next_charge_date) : '—';

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${s.name}, ${s.category?.name ?? 'ohne Kategorie'}, ${formatCents(s.amount_cents)} ${s.rhythm_label}, nächste Abbuchung ${next}${hasDeadline ? `, kündbar bis ${due}` : ''}`}
      onPress={() => router.push(`/subscription/${s.id}`)}
      style={({ pressed, hovered }: { pressed: boolean; hovered?: boolean }) => [styles.tr, styles.td, hovered && styles.rowHover, pressed && styles.pressed]}
    >
      <View style={[styles.cell, styles.nameCell, { flex: 2.2 }]}>
        <View style={[styles.dot, { backgroundColor: s.category?.color ?? categoryColors.other }]} />
        <AppText variant="body" style={styles.medium} numberOfLines={1}>
          {s.name}
        </AppText>
      </View>
      <AppText variant="small" color="textMuted" style={[styles.cell, { flex: 1.5 }]} numberOfLines={1}>
        {s.category?.name ?? 'Ohne Kategorie'}
      </AppText>
      <AppText variant="small" color="textMuted" style={[styles.cell, { flex: 1.1 }]} numberOfLines={1}>
        {s.rhythm_label}
      </AppText>
      <AppText variant="small" amount style={[styles.cell, styles.right, { flex: 1 }]}>
        {formatCents(s.amount_cents)}
      </AppText>
      <AppText variant="small" amount color="textMuted" style={[styles.cell, styles.right, { flex: 1 }]}>
        {formatCents(s.monthly_cents)}
      </AppText>
      <AppText variant="small" amount color="textMuted" style={[styles.cell, styles.right, { flex: 1.3 }]}>
        {next}
      </AppText>
      <View style={[styles.cell, styles.dueCell, { flex: 1.3 }]}>
        <AppText variant="small" amount color={hot ? 'warn' : 'textMuted'} style={[hot && styles.hot]}>
          {due}
        </AppText>
      </View>
    </Pressable>
  );
}

function Kpi({ label, value, sub, warn, onPress }: { label: string; value: string; sub: string; warn?: boolean; onPress?: () => void }) {
  const body = (
    <>
      <AppText variant="small" color={warn ? 'warnText' : 'textMuted'} style={styles.medium}>
        {label}
      </AppText>
      <AppText variant="displayM" amount color={warn ? 'warn' : 'text'}>
        {value}
      </AppText>
      <AppText variant="caption" color={warn ? 'warnTextMuted' : 'textMuted'} numberOfLines={1}>
        {sub}
      </AppText>
    </>
  );

  return onPress ? (
    <Pressable accessibilityRole="button" onPress={onPress} style={({ pressed }) => [styles.kpi, warn && styles.kpiWarn, pressed && styles.pressed]}>
      {body}
    </Pressable>
  ) : (
    <View style={[styles.kpi, warn && styles.kpiWarn]}>{body}</View>
  );
}

const styles = StyleSheet.create({
  page: {
    padding: 32,
    gap: 20,
    width: '100%',
    maxWidth: 1280,
    alignSelf: 'center',
  },
  head: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  flex: {
    flex: 1,
  },
  medium: {
    fontFamily: fonts.bodyMedium,
  },
  semi: {
    fontFamily: fonts.bodySemi,
  },
  pressed: {
    opacity: 0.85,
  },
  badge: {
    width: 8,
    height: 8,
    borderRadius: 99,
    backgroundColor: colors.warn,
    borderWidth: 2,
    borderColor: colors.surface,
  },
  ghost: {
    height: 44,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 16,
    borderRadius: radius.input,
    borderWidth: 1,
    borderColor: colors.borderStrong,
    backgroundColor: colors.surface,
  },
  primary: {
    height: 44,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 16,
    borderRadius: radius.input,
    backgroundColor: colors.accent,
  },
  kpis: {
    flexDirection: 'row',
    gap: 16,
  },
  kpi: {
    flex: 1,
    gap: 6,
    padding: 18,
    borderRadius: radius.card,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  kpiWarn: {
    borderColor: colors.warnBorder,
    backgroundColor: colors.warnSurface,
  },
  split: {
    flexDirection: 'row',
    gap: 16,
  },
  panel: {
    gap: 14,
    padding: 18,
    borderRadius: radius.card,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  historyPanel: {
    flex: 3,
  },
  catPanel: {
    flex: 2,
  },
  chartPlaceholder: {
    height: 200,
  },
  panelHead: {
    flexDirection: 'row',
    alignItems: 'baseline',
    justifyContent: 'space-between',
  },
  cat: {
    gap: 6,
  },
  catRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 8,
  },
  track: {
    height: 8,
    borderRadius: 99,
    backgroundColor: colors.surfaceBar,
    overflow: 'hidden',
  },
  fill: {
    height: '100%',
    borderRadius: 99,
  },
  tableHead: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  search: {
    width: 280,
    height: 44,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 12,
    borderRadius: radius.input,
    borderWidth: 1,
    borderColor: colors.borderStrong,
    backgroundColor: colors.bg,
  },
  searchInput: {
    flex: 1,
    height: 40,
    color: colors.text,
    fontFamily: fonts.body,
    fontSize: 14,
  },
  tr: {
    flexDirection: 'row',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: colors.divider,
  },
  th: {
    paddingBottom: 10,
  },
  td: {
    minHeight: 52,
  },
  rowHover: {
    backgroundColor: colors.surfaceSelected,
  },
  cell: {
    paddingHorizontal: 8,
  },
  nameCell: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  dueCell: {
    alignItems: 'flex-end',
  },
  right: {
    textAlign: 'right',
  },
  dot: {
    width: 10,
    height: 10,
    borderRadius: 3,
  },
  hot: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 8,
    overflow: 'hidden',
    backgroundColor: colors.warnSurface,
    fontFamily: fonts.bodySemi,
  },
  empty: {
    paddingVertical: 24,
    textAlign: 'center',
  },
});
