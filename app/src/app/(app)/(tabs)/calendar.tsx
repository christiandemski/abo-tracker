import { router } from 'expo-router';
import { ChevronLeft, ChevronRight } from 'lucide-react-native';
import { useMemo, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';

import { AppText, ErrorState, IconButton, Notice, Screen, Skeleton, TextLink } from '@/components';
import { useAuth } from '@/lib/auth';
import {
  currentMonth,
  formatCents,
  formatEuroRounded,
  formatMonthName,
  formatMonthYear,
  formatShortDate,
  formatWeekdayDayMonth,
  mondayIndex,
  shiftMonth,
} from '@/lib/format';
import { useCalendar } from '@/lib/queries';
import type { CalendarDay, CalendarItem, CalendarMonth } from '@/lib/types';
import { colors, fonts, radius, screenPadding } from '@/theme';

const WEEKDAYS = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

const LEVEL_BG = { 0: colors.surfaceBar, 1: colors.dayLevel1, 2: colors.dayLevel2, 3: colors.dayLevel3 } as const;

const LEGEND = [
  { level: 1, label: 'unter 30 €' },
  { level: 2, label: '30–100 €' },
  { level: 3, label: 'über 100 €' },
] as const;

export default function CalendarScreen() {
  const { user } = useAuth();
  const [month, setMonth] = useState(currentMonth);
  const [selected, setSelected] = useState<string | null>(null);
  const calendar = useCalendar(month);
  const isCurrent = month === currentMonth();

  function go(delta: number) {
    setMonth((m) => shiftMonth(m, delta));
    setSelected(null);
  }

  return (
    <Screen bare>
      <ScrollView
        contentContainerStyle={styles.scroll}
        refreshControl={
          user?.email_verified ? (
            <RefreshControl refreshing={calendar.refreshing && !calendar.loading} onRefresh={calendar.refetch} tintColor={colors.accent} />
          ) : undefined
        }
      >
        <View style={styles.header}>
          <IconButton icon={ChevronLeft} accessibilityLabel="Vorheriger Monat" onPress={() => go(-1)} />
          <View style={styles.headerTitle}>
            <AppText variant="h1Sub" accessibilityRole="header" accessibilityLiveRegion="polite">
              {formatMonthYear(month)}
            </AppText>
            {!isCurrent && (
              <TextLink
                label="Zurück zu heute"
                onPress={() => {
                  setMonth(currentMonth());
                  setSelected(null);
                }}
              />
            )}
          </View>
          <IconButton icon={ChevronRight} accessibilityLabel="Nächster Monat" onPress={() => go(1)} />
        </View>

        {!user?.email_verified ? (
          <Notice tone="info" title="Bitte bestätige zuerst deine E-Mail-Adresse">
            Danach siehst du hier, an welchen Tagen welche Abbuchungen anstehen.
          </Notice>
        ) : calendar.data && calendar.data.month === month ? (
          <MonthView data={calendar.data} isCurrent={isCurrent} selected={selected} onSelect={setSelected} stale={calendar.stale} />
        ) : calendar.error ? (
          <ErrorState message={calendar.error.message} onRetry={calendar.refetch} retrying={calendar.refreshing} />
        ) : (
          <View style={styles.gap}>
            <Skeleton height={76} rounded={18} />
            <Skeleton height={330} rounded={18} />
          </View>
        )}
      </ScrollView>
    </Screen>
  );
}

function MonthView({
  data,
  isCurrent,
  selected,
  onSelect,
  stale,
}: {
  data: CalendarMonth;
  isCurrent: boolean;
  selected: string | null;
  onSelect: (date: string) => void;
  stale: boolean;
}) {
  const byDate = useMemo(() => new Map(data.days.map((d) => [d.date, d])), [data.days]);
  const lead = mondayIndex(data.first_day);
  const monthName = formatMonthName(data.month);

  // Default selection: today in the current month, else the first day with a charge
  const active = selected ?? (isCurrent ? data.today : (data.days[0]?.date ?? data.first_day));
  const activeDay = byDate.get(active);

  const cells: (string | null)[] = [
    ...Array.from({ length: lead }, () => null),
    ...Array.from({ length: data.days_in_month }, (_, i) => `${data.month}-${String(i + 1).padStart(2, '0')}`),
  ];

  return (
    <View style={styles.gap}>
      {stale && <Notice tone="info">Keine Verbindung. Du siehst den letzten bekannten Stand.</Notice>}

      <View style={styles.summary} accessibilityLabel="Monatssumme">
        <View style={styles.summaryCol}>
          <AppText variant="small" color="textMuted">
            Abbuchungen gesamt
          </AppText>
          <AppText variant="h1Sub" amount>
            {formatCents(data.total_cents)}
          </AppText>
        </View>
        {isCurrent && (
          <View style={[styles.summaryCol, styles.summaryRight]}>
            <AppText variant="small" color="textMuted">
              Noch offen ab heute
            </AppText>
            <AppText variant="h1Sub" amount color="accentText">
              {formatCents(data.open_cents)}
            </AppText>
          </View>
        )}
      </View>

      <View>
        <View style={styles.weekdays} importantForAccessibility="no-hide-descendants">
          {WEEKDAYS.map((w) => (
            <AppText key={w} variant="caption" color="textMuted" style={styles.weekday}>
              {w}
            </AppText>
          ))}
        </View>
        <View style={styles.grid} accessibilityLabel={`Tage im ${monthName}`}>
          {cells.map((date, i) =>
            date === null ? (
              <View key={`e${i}`} style={styles.cell} />
            ) : (
              <DayCell
                key={date}
                date={date}
                day={byDate.get(date)}
                monthName={monthName}
                today={data.today}
                selected={date === active}
                onPress={() => onSelect(date)}
              />
            ),
          )}
        </View>
        <View style={styles.legend} importantForAccessibility="no-hide-descendants">
          {LEGEND.map((l) => (
            <View key={l.level} style={styles.legendItem}>
              <View style={[styles.legendDot, { backgroundColor: LEVEL_BG[l.level] }]} />
              <AppText variant="caption" color="textMuted">
                {l.label}
              </AppText>
            </View>
          ))}
        </View>
      </View>

      <DayDetail date={active} day={activeDay} past={active < data.today} />
    </View>
  );
}

function DayCell({
  date,
  day,
  monthName,
  today,
  selected,
  onPress,
}: {
  date: string;
  day: CalendarDay | undefined;
  monthName: string;
  today: string;
  selected: boolean;
  onPress: () => void;
}) {
  const n = Number(date.slice(8));
  const isToday = date === today;
  const past = date < today;
  const label = `${n}. ${monthName}, ${day ? formatCents(day.total_cents) : 'keine Abbuchung'}${isToday ? ', heute' : ''}`;

  return (
    <View style={styles.cell}>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={label}
        accessibilityState={{ selected }}
        onPress={onPress}
        style={({ pressed }) => [
          styles.day,
          { backgroundColor: LEVEL_BG[day?.level ?? 0] },
          isToday && styles.today,
          selected && styles.selected,
          past && !selected && styles.past,
          pressed && styles.pressed,
        ]}
      >
        <AppText variant="small" color={isToday ? 'accent' : 'text'} style={isToday ? styles.bold : styles.medium}>
          {n}
        </AppText>
        <AppText variant="tab" amount color={day?.level === 3 ? 'text' : 'dayAmount'} style={styles.dayAmount} numberOfLines={1}>
          {day ? formatEuroRounded(day.total_cents) : ''}
        </AppText>
      </Pressable>
    </View>
  );
}

function DayDetail({ date, day, past }: { date: string; day: CalendarDay | undefined; past: boolean }) {
  return (
    <View style={styles.detail} accessibilityLiveRegion="polite">
      <View style={styles.detailHead}>
        <AppText variant="h2" accessibilityRole="header">
          {formatWeekdayDayMonth(date)}
          {past && day ? ' · abgebucht' : ''}
        </AppText>
        {day && (
          <AppText variant="rowTitle" amount>
            {formatCents(day.total_cents)}
          </AppText>
        )}
      </View>
      {!day ? (
        <AppText variant="body" color="textMuted">
          Keine Abbuchung an diesem Tag.
        </AppText>
      ) : (
        <View style={styles.items}>
          {day.items.map((item, i) => (
            <ItemRow key={`${item.subscription_id}-${i}`} item={item} last={i === day.items.length - 1} />
          ))}
        </View>
      )}
    </View>
  );
}

function ItemRow({ item, last }: { item: CalendarItem; last: boolean }) {
  const meta = [item.rhythm_label];
  if (item.status === 'cancelled') meta.push('gekündigt');
  else if (item.cancel_by_date) meta.push(`kündbar bis ${formatShortDate(item.cancel_by_date)}`);
  else if (item.category) meta.push(item.category.name);

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${item.name}, ${formatCents(item.amount_cents)}, ${meta.join(', ')}`}
      onPress={() => router.push(`/subscription/${item.subscription_id}`)}
      style={({ pressed }) => [styles.item, !last && styles.divider, pressed && styles.pressed]}
    >
      <View style={[styles.dot, { backgroundColor: item.category?.color ?? colors.textMuted }]} />
      <View style={styles.itemText}>
        <AppText variant="body" style={styles.medium} numberOfLines={1}>
          {item.name}
        </AppText>
        <AppText variant="caption" color="textMuted" numberOfLines={1}>
          {meta.join(' · ')}
        </AppText>
      </View>
      <AppText variant="body" amount style={styles.medium}>
        {formatCents(item.amount_cents)}
      </AppText>
    </Pressable>
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
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingTop: 20,
    paddingBottom: 12,
  },
  headerTitle: {
    flex: 1,
    alignItems: 'center',
  },
  gap: {
    gap: 16,
  },
  summary: {
    flexDirection: 'row',
    gap: 12,
    padding: 16,
    borderRadius: radius.card,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  summaryCol: {
    flex: 1,
    gap: 4,
  },
  summaryRight: {
    alignItems: 'flex-end',
  },
  weekdays: {
    flexDirection: 'row',
    marginBottom: 6,
  },
  weekday: {
    width: `${100 / 7}%`,
    textAlign: 'center',
  },
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
  },
  cell: {
    width: `${100 / 7}%`,
    padding: 2.5,
  },
  day: {
    height: 52,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: 'transparent',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 2,
  },
  today: {
    borderWidth: 1.5,
    borderColor: colors.accent,
  },
  selected: {
    borderWidth: 2,
    borderColor: colors.text,
  },
  past: {
    opacity: 0.55,
  },
  pressed: {
    opacity: 0.8,
  },
  dayAmount: {
    fontFamily: fonts.bodySemi,
    fontSize: 10,
    minHeight: 12,
  },
  bold: {
    fontFamily: fonts.bodyBold,
  },
  medium: {
    fontFamily: fonts.bodyMedium,
  },
  legend: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 14,
    marginTop: 10,
  },
  legendItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  legendDot: {
    width: 12,
    height: 12,
    borderRadius: 4,
  },
  detail: {
    gap: 8,
  },
  detailHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    minHeight: 44,
  },
  items: {
    borderRadius: radius.card,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    paddingHorizontal: 14,
  },
  item: {
    minHeight: 60,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  divider: {
    borderBottomWidth: 1,
    borderBottomColor: colors.divider,
  },
  dot: {
    width: 10,
    height: 10,
    borderRadius: 3,
  },
  itemText: {
    flex: 1,
    gap: 2,
  },
});
