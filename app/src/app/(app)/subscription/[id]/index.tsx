import { router, useLocalSearchParams } from 'expo-router';
import {
  Bell,
  ChevronLeft,
  CircleSlash,
  Clock,
  CreditCard,
  ExternalLink,
  History,
  Pause,
  Play,
  RotateCcw,
  StickyNote,
  Trash2,
  type LucideIcon,
} from 'lucide-react-native';
import { useState, type ReactNode } from 'react';
import { Linking, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';

import {
  AppText,
  CategoryTile,
  ErrorState,
  IconButton,
  Notice,
  Screen,
  SecondaryButton,
  Skeleton,
  StatusBadge,
  Switch,
} from '@/components';
import { useAuth } from '@/lib/auth';
import {
  formatCents,
  formatDate,
  formatDaysUntil,
  formatPercent,
  formatShortDate,
  formatWeekdayLongDate,
} from '@/lib/format';
import { keys, useSubscription } from '@/lib/queries';
import type { Resource, Subscription } from '@/lib/types';
import { invalidate } from '@/lib/useQuery';
import { colors, fonts, radius, screenPadding } from '@/theme';

/** Deadline banner shows up this many days before the cancel-by date. */
const BANNER_DAYS = 30;

export default function SubscriptionDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const query = useSubscription(id);
  const s = query.data;

  return (
    <Screen bare>
      <View style={styles.header}>
        <IconButton
          icon={ChevronLeft}
          accessibilityLabel="Zurück"
          onPress={() => (router.canGoBack() ? router.back() : router.replace('/'))}
        />
        <AppText variant="rowTitle">Abo-Details</AppText>
        {s ? (
          <Pressable
            accessibilityRole="button"
            onPress={() => router.push(`/subscription/${s.id}/edit`)}
            style={({ pressed }) => [styles.editButton, pressed && styles.pressed]}
          >
            <AppText variant="body" style={styles.medium}>
              Bearbeiten
            </AppText>
          </Pressable>
        ) : (
          <View style={styles.editPlaceholder} />
        )}
      </View>

      <ScrollView
        contentContainerStyle={styles.scroll}
        refreshControl={
          <RefreshControl refreshing={query.refreshing && !query.loading} onRefresh={query.refetch} tintColor={colors.accent} />
        }
      >
        {query.loading ? (
          <View style={styles.gap}>
            <Skeleton height={56} />
            <Skeleton height={120} />
            <Skeleton height={80} />
          </View>
        ) : s ? (
          <Detail subscription={s} />
        ) : query.error ? (
          <ErrorState message={query.error.message} onRetry={query.refetch} retrying={query.refreshing} />
        ) : null}
      </ScrollView>
    </Screen>
  );
}

function Detail({ subscription: s }: { subscription: Subscription }) {
  const { api } = useAuth();
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [confirmDelete, setConfirmDelete] = useState(false);

  const showBanner =
    s.status === 'active' && s.has_term && s.days_until_deadline !== null && s.days_until_deadline <= BANNER_DAYS;
  const history = s.price_history ?? [];
  const trend = s.price_trend_percent ?? null;

  async function run(action: string, fn: () => Promise<unknown>, after?: () => void) {
    setBusy(action);
    setError(null);
    try {
      await fn();
      invalidate('subscriptions', 'dashboard');
      after?.();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Das hat nicht geklappt.');
    } finally {
      setBusy(null);
    }
  }

  const post = (path: string) => () => api.post<Resource<Subscription>>(`subscriptions/${s.id}/${path}`);

  return (
    <View>
      <View style={styles.title}>
        <CategoryTile name={s.name} color={s.category?.color} size="detail" />
        <View style={styles.titleText}>
          <AppText variant="h1Sub" accessibilityRole="header" style={styles.h1}>
            {s.name}
          </AppText>
          <View style={styles.subtitle}>
            <AppText variant="small" color="textMuted">
              {s.category?.name ?? 'Ohne Kategorie'}
            </AppText>
            {s.status !== 'active' && <StatusBadge status={s.status} />}
          </View>
        </View>
      </View>

      {error && (
        <View style={styles.block}>
          <Notice tone="danger">{error}</Notice>
        </View>
      )}

      {showBanner && <DeadlineBanner s={s} />}

      {s.status === 'cancelled' && (
        <View style={styles.block}>
          <Notice tone="info" title={s.ends_at ? `Gekündigt zum ${formatDate(s.ends_at)}` : 'Gekündigt'}>
            Das Abo zählt nicht mehr zu deinen Fixkosten.
          </Notice>
        </View>
      )}

      <View style={styles.costs} accessibilityLabel="Kosten">
        <CostTile label="Abbuchung" value={formatCents(s.amount_cents)} meta={s.rhythm_label} />
        <CostTile label="pro Monat" value={formatCents(s.monthly_cents)} meta={s.interval_unit === 'month' && s.interval_count === 1 ? 'monatlich' : 'umgerechnet'} accent />
        <CostTile label="pro Tag" value={formatCents(s.daily_cents)} meta="umgerechnet" />
      </View>

      <View style={styles.section}>
        <AppText variant="h2" accessibilityRole="header" style={styles.sectionTitle}>
          {'Laufzeit & Fristen'}
        </AppText>
        <Timeline s={s} />
      </View>

      <View style={[styles.section, styles.settings]}>
        <SettingRow icon={Bell} label="Erinnerung" meta={s.has_term ? '14 und 3 Tage vor Fristende' : 'Greift, sobald eine Laufzeit eingetragen ist'}>
          <Switch
            value={s.remind}
            accessibilityLabel="Vor Fristende erinnern"
            disabled={busy !== null}
            onValueChange={(remind) => run('remind', () => api.patch(`subscriptions/${s.id}`, { remind }))}
          />
        </SettingRow>
        <SettingRow icon={CreditCard} label="Zahlungsweg">
          <AppText variant="body" color="textMuted" numberOfLines={1} style={styles.settingValue}>
            {s.payment_method || 'Nicht angegeben'}
          </AppText>
        </SettingRow>
        {s.provider_url ? (
          <SettingRow icon={ExternalLink} label="Zum Anbieter" onPress={() => void Linking.openURL(s.provider_url!)}>
            <AppText variant="body" color="accent" numberOfLines={1} style={styles.settingValue}>
              {s.provider_url.replace(/^https?:\/\//, '')}
            </AppText>
          </SettingRow>
        ) : null}
        <SettingRow icon={History} label="Preisverlauf" last={!s.notes}>
          {trend !== null ? (
            <AppText variant="small" color={trend > 0 ? 'warn' : 'textMuted'} style={styles.semi}>
              {formatPercent(trend)} seit Start
            </AppText>
          ) : (
            <AppText variant="small" color="textMuted">
              unverändert
            </AppText>
          )}
        </SettingRow>
        {s.notes ? (
          <SettingRow icon={StickyNote} label="Notizen" meta={s.notes} last />
        ) : null}
      </View>

      {history.length > 0 && (
        <View style={styles.section}>
          {history
            .slice()
            .reverse()
            .map((p) => (
              <View key={p.id} style={styles.historyRow}>
                <AppText variant="small" color="textMuted" amount>
                  {formatDate(p.effective_date)}
                </AppText>
                <AppText variant="small" amount>
                  {formatCents(p.old_cents)} → {formatCents(p.new_cents)}
                  {p.percent !== null ? `  (${formatPercent(p.percent)})` : ''}
                </AppText>
              </View>
            ))}
        </View>
      )}

      <View style={[styles.section, styles.actions]}>
        {s.status === 'active' && (
          <>
            <SecondaryButton
              label="Pausieren"
              icon={Pause}
              loading={busy === 'pause'}
              onPress={() => run('pause', () => api.patch(`subscriptions/${s.id}`, { status: 'paused' }))}
            />
            <SecondaryButton
              label="Als gekündigt markieren"
              icon={CircleSlash}
              loading={busy === 'cancel'}
              onPress={() => run('cancel', post('cancel'))}
            />
          </>
        )}
        {s.status === 'paused' && (
          <SecondaryButton
            label="Fortsetzen"
            icon={Play}
            loading={busy === 'resume'}
            onPress={() => run('resume', () => api.patch(`subscriptions/${s.id}`, { status: 'active' }))}
          />
        )}
        {s.status === 'cancelled' && (
          <SecondaryButton
            label="Wieder aktivieren"
            icon={RotateCcw}
            loading={busy === 'reactivate'}
            onPress={() => run('reactivate', post('reactivate'))}
          />
        )}

        {confirmDelete ? (
          <View style={styles.confirm}>
            <AppText variant="body">„{s.name}“ wirklich löschen?</AppText>
            <View style={styles.confirmButtons}>
              <View style={styles.flex}>
                <SecondaryButton label="Abbrechen" onPress={() => setConfirmDelete(false)} />
              </View>
              <View style={styles.flex}>
                <DangerButton
                  label="Löschen"
                  loading={busy === 'delete'}
                  onPress={() =>
                    run('delete', () => api.delete(`subscriptions/${s.id}`), () => {
                      invalidate(keys.subscription(s.id));
                      if (router.canGoBack()) router.back();
                      else router.replace('/subscriptions');
                    })
                  }
                />
              </View>
            </View>
          </View>
        ) : (
          <SecondaryButton label="Abo löschen" icon={Trash2} onPress={() => setConfirmDelete(true)} />
        )}
      </View>
    </View>
  );
}

function DeadlineBanner({ s }: { s: Subscription }) {
  const renewal = s.renewal_months ?? 1;
  const renewalLabel = renewal === 1 ? '1 Monat' : `${renewal} Monate`;

  return (
    <View accessibilityRole="alert" style={styles.banner}>
      <Clock size={20} strokeWidth={2} color={colors.warn} style={styles.bannerIcon} />
      <View style={styles.bannerText}>
        <AppText variant="rowTitle" color="warn">
          Kündigungsfrist endet {formatDaysUntil(s.days_until_deadline ?? 0)}
        </AppText>
        <AppText variant="small" color="warnText" style={styles.bannerBody}>
          Kündbar bis {formatWeekdayLongDate(s.cancel_by_date!)}.
          {s.term_end_date
            ? ` Danach verlängert sich das Abo am ${formatShortDate(s.term_end_date)} automatisch um ${renewalLabel}.`
            : ''}
        </AppText>
      </View>
    </View>
  );
}

function CostTile({ label, value, meta, accent }: { label: string; value: string; meta: string; accent?: boolean }) {
  return (
    <View style={[styles.costTile, accent && styles.costTileAccent]}>
      <AppText variant="tab" color={accent ? 'accentText' : 'textMuted'}>
        {label}
      </AppText>
      <AppText variant="h2" amount>
        {value}
      </AppText>
      <AppText variant="tab" color={accent ? 'accentText' : 'textMuted'} numberOfLines={1}>
        {meta}
      </AppText>
    </View>
  );
}

type TimelineItem = { label: string; date: string; tone?: 'warn' | 'accent' };

function Timeline({ s }: { s: Subscription }) {
  const items: TimelineItem[] = [];

  if (s.has_term) {
    if (s.start_date) items.push({ label: 'Vertragsbeginn', date: s.start_date });
    if (s.cancel_by_date) items.push({ label: 'Kündbar bis', date: s.cancel_by_date, tone: 'warn' });
    if (s.term_end_date) items.push({ label: 'Nächste Verlängerung', date: s.term_end_date, tone: 'accent' });
  } else if (s.next_charge_date) {
    items.push({ label: 'Nächste Abbuchung', date: s.next_charge_date, tone: 'accent' });
  }
  if (s.status === 'cancelled' && s.ends_at) items.push({ label: 'Endet am', date: s.ends_at });

  return (
    <View>
      {items.map((item, index) => {
        const last = index === items.length - 1;
        const dotColor =
          item.tone === 'warn' ? colors.warn : item.tone === 'accent' ? colors.accent : colors.checkboxOff;
        return (
          <View key={item.label} style={styles.tlRow}>
            <View style={styles.tlRail}>
              <View style={[styles.tlDot, { backgroundColor: dotColor }, item.tone === 'warn' && styles.tlDotWarn]} />
              {!last && <View style={styles.tlLine} />}
            </View>
            <View style={[styles.tlContent, !last && styles.tlContentGap]}>
              <AppText variant="body" color={item.tone === 'warn' ? 'warn' : 'textMuted'} style={item.tone === 'warn' && styles.semi}>
                {item.label}
              </AppText>
              <AppText variant="body" amount style={item.tone === 'warn' && styles.semi}>
                {formatDate(item.date)}
              </AppText>
            </View>
          </View>
        );
      })}
      {!s.has_term && (
        <AppText variant="small" color="textMuted" style={styles.hint}>
          Jederzeit kündbar{s.notice_label ? ` mit ${s.notice_label} Frist` : ''}. Trag unter „Bearbeiten“ eine
          Mindestlaufzeit ein, wenn es eine gibt.
        </AppText>
      )}
    </View>
  );
}

function SettingRow({
  icon: Icon,
  label,
  meta,
  children,
  last,
  onPress,
}: {
  icon: LucideIcon;
  label: string;
  meta?: string;
  children?: ReactNode;
  last?: boolean;
  onPress?: () => void;
}) {
  return (
    <Pressable
      disabled={!onPress}
      onPress={onPress}
      accessibilityRole={onPress ? 'link' : undefined}
      style={[styles.settingRow, !last && styles.settingDivider]}
    >
      <Icon size={20} strokeWidth={1.8} color={colors.textMuted} />
      <View style={styles.settingText}>
        <AppText variant="body" style={styles.medium}>
          {label}
        </AppText>
        {meta ? (
          <AppText variant="caption" color="textMuted">
            {meta}
          </AppText>
        ) : null}
      </View>
      {children}
    </Pressable>
  );
}

function DangerButton({ label, onPress, loading }: { label: string; onPress: () => void; loading?: boolean }) {
  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      disabled={loading}
      style={({ pressed }) => [styles.danger, pressed && styles.pressed]}
    >
      <AppText variant="rowTitle" color="danger">
        {loading ? 'Wird gelöscht …' : label}
      </AppText>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingTop: 20,
    paddingHorizontal: 16,
  },
  editButton: {
    height: 44,
    paddingHorizontal: 14,
    borderRadius: radius.iconButton,
    borderWidth: 1,
    borderColor: colors.borderStrong,
    backgroundColor: colors.surface,
    justifyContent: 'center',
  },
  editPlaceholder: {
    width: 44,
  },
  pressed: {
    opacity: 0.8,
  },
  medium: {
    fontFamily: fonts.bodyMedium,
  },
  semi: {
    fontFamily: fonts.bodySemi,
  },
  scroll: {
    paddingHorizontal: screenPadding,
    paddingBottom: 40,
  },
  gap: {
    gap: 14,
    marginTop: 22,
  },
  title: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    paddingTop: 22,
  },
  titleText: {
    flex: 1,
    gap: 3,
  },
  h1: {
    fontSize: 24,
  },
  subtitle: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  block: {
    marginTop: 16,
  },
  banner: {
    marginTop: 20,
    padding: 16,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: colors.warnBorder,
    backgroundColor: colors.warnSurface,
    flexDirection: 'row',
    gap: 10,
  },
  bannerIcon: {
    marginTop: 1,
  },
  bannerText: {
    flex: 1,
    gap: 4,
  },
  bannerBody: {
    lineHeight: 19,
  },
  costs: {
    marginTop: 14,
    flexDirection: 'row',
    gap: 8,
  },
  costTile: {
    flex: 1,
    padding: 12,
    gap: 4,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  costTileAccent: {
    borderColor: colors.accentBorder,
    backgroundColor: colors.accentSurface,
  },
  section: {
    marginTop: 22,
  },
  sectionTitle: {
    marginBottom: 10,
  },
  tlRow: {
    flexDirection: 'row',
    gap: 12,
  },
  tlRail: {
    width: 12,
    alignItems: 'center',
  },
  tlDot: {
    width: 10,
    height: 10,
    borderRadius: 99,
    marginTop: 5,
  },
  tlDotWarn: {
    borderWidth: 3,
    borderColor: colors.warnSurface,
    width: 14,
    height: 14,
    marginTop: 3,
  },
  tlLine: {
    flex: 1,
    width: 2,
    backgroundColor: colors.borderStrong,
  },
  tlContent: {
    flex: 1,
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  tlContentGap: {
    paddingBottom: 14,
  },
  hint: {
    marginTop: 8,
  },
  settings: {
    borderRadius: 20,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    overflow: 'hidden',
  },
  settingRow: {
    minHeight: 56,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  settingDivider: {
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  settingText: {
    flex: 1,
    gap: 2,
  },
  settingValue: {
    maxWidth: '50%',
    textAlign: 'right',
  },
  historyRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 6,
  },
  actions: {
    gap: 10,
  },
  confirm: {
    gap: 10,
    padding: 14,
    borderRadius: radius.iconButton,
    borderWidth: 1,
    borderColor: colors.dangerBorder,
    backgroundColor: colors.dangerSurface,
  },
  confirmButtons: {
    flexDirection: 'row',
    gap: 10,
  },
  flex: {
    flex: 1,
  },
  danger: {
    height: 44,
    borderRadius: radius.iconButton,
    borderWidth: 1,
    borderColor: colors.dangerBorder,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
