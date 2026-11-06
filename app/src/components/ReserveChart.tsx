import { StyleSheet, View } from 'react-native';

import { formatCents, formatMonthName, formatMonthShort } from '@/lib/format';
import type { Reserve } from '@/lib/types';
import { colors, fonts } from '@/theme';

import { AppText } from './AppText';

const UP = 100;
const DOWN = 50;

/** Balance of the reserve over the next 12 months: above the line covered, below in the minus. */
export function ReserveChart({ months }: { months: Reserve['months'] }) {
  const maxPos = Math.max(1, ...months.map((m) => m.balance_cents));
  const maxNeg = Math.max(1, ...months.map((m) => -m.balance_cents));
  const lowest = months.reduce((a, b) => (b.balance_cents < a.balance_cents ? b : a), months[0]);
  const last = months[months.length - 1];

  const label = lowest && last
    ? `Stand der Rücklage über 12 Monate. Tiefster Stand ${formatCents(lowest.balance_cents)} im ${formatMonthName(lowest.month)}, am Ende ${formatCents(last.balance_cents)}.`
    : '';

  return (
    <View>
      <View style={styles.chart} accessibilityRole="image" accessibilityLabel={label}>
        {months.map((m) => (
          <View key={m.month} style={styles.col}>
            <View style={styles.up}>
              {m.balance_cents > 0 && (
                <View style={[styles.barUp, { height: Math.max(2, (m.balance_cents / maxPos) * UP) }]} />
              )}
            </View>
            <View style={styles.down}>
              {m.balance_cents < 0 && (
                <View style={[styles.barDown, { height: Math.max(3, (-m.balance_cents / maxNeg) * DOWN) }]} />
              )}
            </View>
          </View>
        ))}
      </View>
      <View style={styles.labels} importantForAccessibility="no-hide-descendants">
        {months.map((m) => (
          <View key={m.month} style={[styles.labelBox, m.payments_cents > 0 && styles.labelPay]}>
            <AppText variant="tab" color={m.payments_cents > 0 ? 'text' : 'textMuted'} style={m.payments_cents > 0 && styles.bold}>
              {formatMonthShort(m.month).charAt(0)}
            </AppText>
          </View>
        ))}
      </View>
      <View style={styles.legend} importantForAccessibility="no-hide-descendants">
        <Legend swatch={styles.swatchUp} label="gedeckt" />
        <Legend swatch={styles.swatchDown} label="im Minus" />
        <Legend swatch={styles.swatchPay} label="Abbuchung" />
      </View>
    </View>
  );
}

function Legend({ swatch, label }: { swatch: object; label: string }) {
  return (
    <View style={styles.legendItem}>
      <View style={[styles.swatch, swatch]} />
      <AppText variant="caption" color="textMuted">
        {label}
      </AppText>
    </View>
  );
}

const styles = StyleSheet.create({
  chart: {
    flexDirection: 'row',
    gap: 5,
  },
  col: {
    flex: 1,
  },
  up: {
    height: UP,
    justifyContent: 'flex-end',
    borderBottomWidth: 1,
    borderBottomColor: colors.borderStrong,
  },
  down: {
    height: DOWN,
  },
  barUp: {
    width: '100%',
    borderTopLeftRadius: 4,
    borderTopRightRadius: 4,
    backgroundColor: colors.accent,
  },
  barDown: {
    width: '100%',
    borderBottomLeftRadius: 4,
    borderBottomRightRadius: 4,
    backgroundColor: colors.danger,
  },
  labels: {
    flexDirection: 'row',
    gap: 5,
    marginTop: 4,
  },
  labelBox: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: 2,
    borderRadius: 6,
    borderWidth: 1.5,
    borderColor: 'transparent',
  },
  labelPay: {
    borderColor: colors.textMuted,
  },
  bold: {
    fontFamily: fonts.bodyBold,
  },
  legend: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 14,
    marginTop: 12,
  },
  legendItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  swatch: {
    width: 12,
    height: 12,
    borderRadius: 4,
  },
  swatchUp: {
    backgroundColor: colors.accent,
  },
  swatchDown: {
    backgroundColor: colors.danger,
  },
  swatchPay: {
    borderWidth: 1.5,
    borderColor: colors.textMuted,
  },
});
