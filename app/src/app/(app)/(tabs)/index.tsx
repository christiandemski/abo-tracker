import { router } from 'expo-router';
import { CirclePlus, FileUp, Settings } from 'lucide-react-native';
import { useState } from 'react';
import { RefreshControl, ScrollView, StyleSheet, View } from 'react-native';

import {
  AppText,
  Card,
  ErrorState,
  HeroTotal,
  IconButton,
  Notice,
  PrimaryButton,
  Screen,
  ScreenHeader,
  SecondaryButton,
  SectionHeader,
  Skeleton,
  SkeletonList,
  SubscriptionRow,
  TextLink,
  UpcomingCard,
} from '@/components';
import { useAuth } from '@/lib/auth';
import { formatCents, formatLongDate } from '@/lib/format';
import { useDashboard } from '@/lib/queries';
import type { Dashboard, MessageResponse } from '@/lib/types';
import { colors, fonts, screenPadding } from '@/theme';

type Period = 'month' | 'year';

export default function OverviewScreen() {
  const { user } = useAuth();
  const [period, setPeriod] = useState<Period>('month');
  const dashboard = useDashboard(period);
  const verified = !!user?.email_verified;

  return (
    <Screen bare>
      <ScrollView
        contentContainerStyle={styles.scroll}
        refreshControl={
          verified ? (
            <RefreshControl refreshing={dashboard.refreshing && !dashboard.loading} onRefresh={dashboard.refetch} tintColor={colors.accent} />
          ) : undefined
        }
      >
        <View style={styles.padded}>
          <ScreenHeader
            overline={formatLongDate(new Date())}
            title="Übersicht"
            right={<IconButton icon={Settings} accessibilityLabel="Einstellungen" onPress={() => router.push('/settings')} />}
          />
        </View>

        {!verified ? (
          <View style={styles.padded}>
            <VerifyEmailNotice />
          </View>
        ) : dashboard.loading ? (
          <View style={[styles.padded, styles.section]}>
            <Skeleton height={230} rounded={24} />
            <View style={styles.gap} />
            <SkeletonList rows={3} />
          </View>
        ) : dashboard.data ? (
          <>
            {dashboard.stale && (
              <View style={[styles.padded, styles.section]}>
                <Notice tone="info">Keine Verbindung. Du siehst den letzten bekannten Stand.</Notice>
              </View>
            )}
            {dashboard.data.subscription_count === 0 && dashboard.data.inactive_count === 0 ? (
              <View style={[styles.padded, styles.section]}>
                <EmptyState />
              </View>
            ) : (
              <OverviewContent dashboard={dashboard.data} period={period} onPeriodChange={setPeriod} />
            )}
          </>
        ) : dashboard.error ? (
          <View style={[styles.padded, styles.section]}>
            <ErrorState message={dashboard.error.message} onRetry={dashboard.refetch} retrying={dashboard.refreshing} />
          </View>
        ) : null}
      </ScrollView>
    </Screen>
  );
}

function OverviewContent({ dashboard, period, onPeriodChange }: { dashboard: Dashboard; period: Period; onPeriodChange: (p: Period) => void }) {
  const perLabel = period === 'month' ? '/ Monat' : '/ Jahr';

  return (
    <>
      <View style={[styles.padded, styles.section]}>
        <HeroTotal dashboard={dashboard} period={period} onPeriodChange={onPeriodChange} />
      </View>

      {dashboard.upcoming.length > 0 && (
        <View style={styles.sectionLarge}>
          <View style={styles.padded}>
            <SectionHeader title="Demnächst" action={{ label: 'Kalender', onPress: () => router.push('/calendar') }} />
          </View>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.upcoming}>
            {dashboard.upcoming.map((item) => (
              <UpcomingCard
                key={`${item.type}-${item.subscription.id}`}
                item={item}
                onPress={() => router.push(`/subscription/${item.subscription.id}`)}
              />
            ))}
          </ScrollView>
        </View>
      )}

      {dashboard.biggest.length > 0 && (
        <View style={[styles.padded, styles.sectionLarge]}>
          <SectionHeader
            title="Größte Posten"
            action={{ label: `Alle ${dashboard.subscription_count}`, onPress: () => router.push('/subscriptions') }}
          />
          {dashboard.biggest.map(({ subscription: s, period_cents }, index) => (
            <SubscriptionRow
              key={s.id}
              name={s.name}
              meta={[s.category?.name, s.rhythm_label].filter(Boolean).join(' · ')}
              amount={formatCents(period_cents)}
              amountMeta={perLabel}
              categoryColor={s.category?.color}
              last={index === dashboard.biggest.length - 1}
              onPress={() => router.push(`/subscription/${s.id}`)}
            />
          ))}
        </View>
      )}
    </>
  );
}

function EmptyState() {
  return (
    <Card size="hero">
      <AppText variant="small" color="textMuted" style={styles.label}>
        Fixkosten pro Monat
      </AppText>
      <AppText variant="h1">Noch keine Abos</AppText>
      <AppText variant="body" color="textMuted">
        Leg dein erstes Abo an oder importier einen Kontoauszug. Dann siehst du hier, was dich jeden Monat fest kostet
        und wann du kündigen kannst.
      </AppText>
      <View style={styles.actions}>
        <PrimaryButton label="Erstes Abo anlegen" icon={CirclePlus} onPress={() => router.push('/add')} />
        <SecondaryButton label="CSV importieren" icon={FileUp} onPress={() => router.push('/import')} />
      </View>
    </Card>
  );
}

function VerifyEmailNotice() {
  const { api, refreshUser } = useAuth();
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function resend() {
    setBusy(true);
    try {
      const res = await api.post<MessageResponse>('auth/email/verification-notification');
      setMessage(res.message);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Das hat nicht geklappt.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={styles.section}>
      <Notice
        tone="info"
        title="Bitte bestätige deine E-Mail-Adresse"
        action={
          <View style={styles.noticeActions}>
            {message ? (
              <AppText variant="small" color="textMuted">
                {message}
              </AppText>
            ) : (
              <TextLink label={busy ? 'Wird gesendet …' : 'Mail noch mal schicken'} onPress={busy ? () => {} : resend} />
            )}
            <TextLink label="Ich habe bestätigt" onPress={() => void refreshUser()} />
          </View>
        }
      >
        Wir haben dir einen Link geschickt. Danach kannst du deine Abos anlegen, und Erinnerungen an Kündigungsfristen
        kommen bei dir an.
      </Notice>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: {
    paddingBottom: 32,
  },
  padded: {
    paddingHorizontal: screenPadding,
  },
  section: {
    marginTop: 16,
  },
  sectionLarge: {
    marginTop: 22,
  },
  gap: {
    height: 16,
  },
  upcoming: {
    gap: 10,
    paddingHorizontal: screenPadding,
  },
  label: {
    fontFamily: fonts.bodyMedium,
  },
  actions: {
    gap: 10,
    marginTop: 4,
  },
  noticeActions: {
    gap: 0,
  },
});
