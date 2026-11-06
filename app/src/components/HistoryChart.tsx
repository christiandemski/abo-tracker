import { StyleSheet, View } from 'react-native';

import { formatCents, formatMonthShort, formatMonthYear } from '@/lib/format';
import type { History } from '@/lib/types';
import { colors } from '@/theme';

import { AppText } from './AppText';

type Props = {
  history: History;
  height?: number;
};

/** Monthly fixed costs as bars; the current month is highlighted. */
export function HistoryChart({ history, height = 140 }: Props) {
  const months = history.months;
  const max = Math.max(1, ...months.map((m) => m.total_cents));
  const first = months[0];
  const last = months[months.length - 1];
  const label = first && last
    ? `Fixkosten pro Monat: ${formatCents(first.total_cents)} im ${formatMonthYear(first.month)}, ${formatCents(last.total_cents)} im ${formatMonthYear(last.month)}`
    : 'Kein Verlauf';

  return (
    <View>
      <View style={[styles.bars, { height }]} accessibilityRole="image" accessibilityLabel={label}>
        {months.map((m, i) => {
          const current = i === months.length - 1;
          return (
            <View key={m.month} style={styles.col}>
              <View
                style={[
                  styles.bar,
                  { height: `${Math.max(2, (m.total_cents / max) * 100)}%`, backgroundColor: current ? colors.accent : colors.barMuted },
                ]}
              />
            </View>
          );
        })}
      </View>
      <View style={styles.labels} importantForAccessibility="no-hide-descendants">
        {months.map((m, i) => (
          <AppText
            key={m.month}
            variant="tab"
            color={i === months.length - 1 ? 'accent' : 'textMuted'}
            style={styles.label}
            numberOfLines={1}
          >
            {formatMonthShort(m.month)}
          </AppText>
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  bars: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: 6,
  },
  col: {
    flex: 1,
    height: '100%',
    justifyContent: 'flex-end',
    alignItems: 'center',
  },
  bar: {
    width: '100%',
    maxWidth: 28,
    borderTopLeftRadius: 6,
    borderTopRightRadius: 6,
    borderBottomLeftRadius: 2,
    borderBottomRightRadius: 2,
  },
  labels: {
    flexDirection: 'row',
    gap: 6,
    marginTop: 6,
  },
  label: {
    flex: 1,
    textAlign: 'center',
  },
});
