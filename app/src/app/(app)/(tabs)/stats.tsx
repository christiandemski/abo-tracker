import { router } from 'expo-router';
import { CalendarSync, ChevronRight, TriangleAlert } from 'lucide-react-native';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';

import { AppText, Card, ErrorState, Notice, Screen, ScreenHeader, Skeleton } from '@/components';
import { HistoryChart } from '@/components/HistoryChart';
import { ReserveChart } from '@/components/ReserveChart';
import { useAuth } from '@/lib/auth';
import { formatCents, formatMonthName, formatMonthShort, formatShortDate } from '@/lib/format';
import { useHistory, useReserve } from '@/lib/queries';
import type { History, Reserve } from '@/lib/types';
import { colors, fonts, radius, screenPadding } from '@/theme';

/** "März", "März und April", "März, April und Mai" */
function joinGerman(items: string[]): string {
  if (items.length <= 1) return items.join('');
  return `${items.slice(0, -1).join(', ')} und ${items[items.length - 1]}`;
}

export default function StatsScreen() {
  const { user } = useAuth();
  const reserve = useReserve();
  const history = useHistory(12);
  const verified = !!user?.email_verified;

  return (
    <Screen bare>
      <ScrollView
        contentContainerStyle={styles.scroll}
        refreshControl={
          verified ? (
            <RefreshControl
              refreshing={(reserve.refreshing && !reserve.loading) || (history.refreshing && !history.loading)}
              onRefresh={() => {
                reserve.refetch();
                history.refetch();
              }}
              tintColor={colors.accent}
            />
          ) : undefined
        }
      >
        <ScreenHeader title="Statistik" />

        {!verified ? (
          <View style={styles.section}>
            <Notice tone="info" title="Bitte bestätige zuerst deine E-Mail-Adresse">
              Danach siehst du hier deine Rücklage und den Verlauf deiner Fixkosten.
            </Notice>
          </View>
        ) : (
          <>
            {(reserve.stale || history.stale) && (
              <View style={styles.section}>
                <Notice tone="info">Keine Verbindung. Du siehst den letzten bekannten Stand.</Notice>
              </View>
            )}

            {reserve.data ? (
              <ReserveSection reserve={reserve.data} />
            ) : reserve.error ? (
              <View style={styles.section}>
                <ErrorState message={reserve.error.message} onRetry={reserve.refetch} retrying={reserve.refreshing} />
              </View>
            ) : (
              <View style={[styles.section, styles.gap]}>
                <Skeleton height={120} rounded={24} />
                <Skeleton height={220} rounded={18} />
              </View>
            )}

            {history.data ? (
              <HistorySection history={history.data} />
            ) : history.error ? (
              <View style={styles.section}>
                <ErrorState message={history.error.message} onRetry={history.refetch} retrying={history.refreshing} />
              </View>
            ) : (
              <View style={styles.section}>
                <Skeleton height={200} rounded={18} />
              </View>
            )}

            <Pressable
              accessibilityRole="button"
              onPress={() => router.push('/calendar-feed')}
              style={({ pressed }) => [styles.section, styles.link, pressed && styles.pressed]}
            >
              <CalendarSync size={20} strokeWidth={1.8} color={colors.textMuted} />
              <View style={styles.flex}>
                <AppText variant="body" style={styles.medium}>
                  Kalender-Abo
                </AppText>
                <AppText variant="caption" color="textMuted">
                  Abbuchungen und Fristen in deinem Kalender
                </AppText>
              </View>
              <ChevronRight size={20} strokeWidth={1.8} color={colors.textMuted} />
            </Pressable>
          </>
        )}
      </ScrollView>
    </Screen>
  );
}

function ReserveSection({ reserve }: { reserve: Reserve }) {
  if (reserve.subscription_count === 0) {
    return (
      <View style={styles.section}>
        <Card>
          <AppText variant="h2">Rücklage für Jahresabos</AppText>
          <AppText variant="body" color="textMuted">
            Du hast keine Abos, die jährlich oder vierteljährlich abgebucht werden. Sobald du welche anlegst, siehst du hier,
            wie viel du monatlich zurücklegen solltest.
          </AppText>
        </Card>
      </View>
    );
  }

  const first = reserve.months[0];
  const last = reserve.months[reserve.months.length - 1];
  const negative = reserve.months.filter((m) => m.balance_cents < 0).map((m) => formatMonthName(m.month));

  return (
    <>
      <View style={[styles.section, styles.hero]} accessibilityLabel="Empfohlene Rücklage">
        <AppText variant="small" color="accentText" style={styles.medium}>
          Lege monatlich zurück
        </AppText>
        <AppText variant="displayM" amount>
          {formatCents(reserve.monthly_cents)}
        </AppText>
        <AppText variant="small" color="accentText">
          für {reserve.subscription_count} {reserve.subscription_count === 1 ? 'Jahres- oder Quartalsabo' : 'Jahres- und Quartalsabos'} mit
          zusammen {formatCents(reserve.yearly_cents)} im Jahr
        </AppText>
      </View>

      <View style={[styles.section, styles.panel]}>
        <View style={styles.panelHead}>
          <AppText variant="h2" accessibilityRole="header">
            Stand der Rücklage
          </AppText>
          {first && last && (
            <AppText variant="caption" color="textMuted">
              {formatMonthShort(first.month, true)} – {formatMonthShort(last.month, true)}
            </AppText>
          )}
        </View>
        <ReserveChart months={reserve.months} />
      </View>

      {reserve.buffer_cents > 0 ? (
        <View style={[styles.section, styles.warn]}>
          <TriangleAlert size={18} strokeWidth={2} color={colors.warn} />
          <AppText variant="small" color="warnText" style={styles.flex}>
            Im <AppText variant="small" color="text" style={styles.semi}>{joinGerman(negative)}</AppText> reicht die Rücklage
            nicht ganz. Mit einem Startpuffer von{' '}
            <AppText variant="small" amount color="text" style={styles.semi}>
              {formatCents(reserve.buffer_cents)}
            </AppText>{' '}
            bleibt sie das ganze Jahr gedeckt.
          </AppText>
        </View>
      ) : (
        <View style={styles.section}>
          <Notice tone="success">Die Rücklage deckt alle Abbuchungen der nächsten 12 Monate.</Notice>
        </View>
      )}

      {reserve.upcoming.length > 0 && (
        <View style={styles.section}>
          <AppText variant="h2" accessibilityRole="header" style={styles.h2}>
            Nächste Jahreszahlungen
          </AppText>
          <View style={styles.list}>
            {reserve.upcoming.map((p, i) => (
              <Pressable
                key={p.subscription_id}
                accessibilityRole="button"
                accessibilityLabel={`${formatShortDate(p.date)}, ${p.name}, ${formatCents(p.amount_cents)}`}
                onPress={() => router.push(`/subscription/${p.subscription_id}`)}
                style={({ pressed }) => [styles.row, i < reserve.upcoming.length - 1 && styles.divider, pressed && styles.pressed]}
              >
                <AppText variant="small" amount color="textMuted" style={styles.date}>
                  {formatShortDate(p.date)}
                </AppText>
                <AppText variant="body" style={[styles.flex, styles.medium]} numberOfLines={1}>
                  {p.name}
                </AppText>
                <AppText variant="body" amount>
                  {formatCents(p.amount_cents)}
                </AppText>
              </Pressable>
            ))}
          </View>
        </View>
      )}
    </>
  );
}

function HistorySection({ history }: { history: History }) {
  const change = history.change_cents;

  return (
    <View style={[styles.section, styles.panel]}>
      <View style={styles.panelHead}>
        <AppText variant="h2" accessibilityRole="header">
          Monatliche Fixkosten
        </AppText>
        <AppText variant="caption" color="textMuted">
          letzte 12 Monate
        </AppText>
      </View>
      <View style={styles.historyHead}>
        <AppText variant="h1Sub" amount>
          {formatCents(history.current_cents)}
        </AppText>
        {change !== null && (
          <AppText variant="small" amount color="textMuted">
            {change === 0 ? 'wie im Vormonat' : `${change > 0 ? '+' : '−'}${formatCents(Math.abs(change))} zum Vormonat`}
          </AppText>
        )}
      </View>
      <HistoryChart history={history} />
      <AppText variant="caption" color="textMuted">
        Berechnet aus Startdatum, Kündigungen und Preisänderungen deiner Abos.
      </AppText>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: {
    paddingHorizontal: screenPadding,
    paddingBottom: 32,
    width: '100%',
    maxWidth: 640,
    alignSelf: 'center',
  },
  section: {
    marginTop: 16,
  },
  gap: {
    gap: 16,
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
  h2: {
    marginBottom: 8,
  },
  hero: {
    padding: 18,
    gap: 6,
    borderRadius: radius.hero,
    borderWidth: 1,
    borderColor: colors.accentBorder,
    backgroundColor: colors.accentSurface,
  },
  panel: {
    padding: 16,
    gap: 12,
    borderRadius: radius.card,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  panelHead: {
    flexDirection: 'row',
    alignItems: 'baseline',
    justifyContent: 'space-between',
    gap: 8,
  },
  historyHead: {
    gap: 2,
  },
  warn: {
    flexDirection: 'row',
    gap: 10,
    padding: 14,
    borderRadius: radius.card,
    borderWidth: 1,
    borderColor: colors.warnBorder,
    backgroundColor: colors.warnSurface,
  },
  list: {
    borderRadius: radius.card,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    paddingHorizontal: 14,
  },
  row: {
    minHeight: 52,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  divider: {
    borderBottomWidth: 1,
    borderBottomColor: colors.divider,
  },
  date: {
    width: 48,
  },
  link: {
    minHeight: 64,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 14,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  pressed: {
    opacity: 0.8,
  },
});
