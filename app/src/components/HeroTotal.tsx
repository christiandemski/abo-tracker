import { StyleSheet, View } from 'react-native';

import { formatCents } from '@/lib/format';
import type { Dashboard } from '@/lib/types';
import { categoryColors, colors, type as typeStyles } from '@/theme';

import { AppText } from './AppText';
import { Card } from './Card';
import { Segmented } from './Segmented';

type Period = 'month' | 'year';

const PERIODS = [
  { value: 'month', label: 'Monat' },
  { value: 'year', label: 'Jahr' },
] as const;

type Props = {
  dashboard: Dashboard;
  period: Period;
  onPeriodChange: (period: Period) => void;
};

export function HeroTotal({ dashboard, period, onPeriodChange }: Props) {
  const { total_cents, subscription_count, converted_count, categories } = dashboard;
  const subtitle = [
    subscription_count === 1 ? '1 Abo' : `${subscription_count} Abos`,
    converted_count > 0 && period === 'month'
      ? `${converted_count} ${converted_count === 1 ? 'längeres' : 'längere'} auf Monat umgerechnet`
      : null,
  ]
    .filter(Boolean)
    .join(' · ');

  return (
    <Card size="hero" accessibilityRole="summary">
      <View style={styles.top}>
        <AppText variant="small" color="textMuted" style={styles.label}>
          {period === 'month' ? 'Fixkosten pro Monat' : 'Fixkosten pro Jahr'}
        </AppText>
        <Segmented options={PERIODS} value={period} onChange={onPeriodChange} accessibilityLabel="Zeitraum" />
      </View>

      <View style={styles.totals}>
        <AppText variant="display" amount accessibilityLabel={`${formatCents(total_cents)} ${period === 'month' ? 'pro Monat' : 'pro Jahr'}`}>
          {formatCents(total_cents)}
        </AppText>
        <AppText variant="small" color="textMuted">
          {subtitle}
        </AppText>
      </View>

      {categories.length > 0 && (
        <>
          <View style={styles.bar} accessible={false} importantForAccessibility="no-hide-descendants">
            {categories.map((c) => (
              <View
                key={c.category?.id ?? 'none'}
                style={{
                  flexGrow: Math.max(c.share, 0.01),
                  flexBasis: 0,
                  backgroundColor: c.category?.color ?? categoryColors.other,
                }}
              />
            ))}
          </View>

          <View style={styles.legend}>
            {categories.map((c) => (
              <View key={c.category?.id ?? 'none'} style={styles.legendItem}>
                <View style={[styles.dot, { backgroundColor: c.category?.color ?? categoryColors.other }]} />
                <View style={styles.legendText}>
                  <AppText variant="tab" color="textMuted" numberOfLines={1}>
                    {c.label}
                  </AppText>
                  <AppText variant="small" amount style={styles.legendAmount}>
                    {formatCents(c.total_cents)}
                  </AppText>
                </View>
              </View>
            ))}
          </View>
        </>
      )}
    </Card>
  );
}

const styles = StyleSheet.create({
  top: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
  },
  label: {
    fontFamily: typeStyles.caption.fontFamily,
  },
  totals: {
    gap: 4,
  },
  bar: {
    flexDirection: 'row',
    gap: 3,
    height: 10,
    borderRadius: 99,
    overflow: 'hidden',
    backgroundColor: colors.bg,
  },
  legend: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    rowGap: 10,
  },
  legendItem: {
    width: '33.33%',
    flexDirection: 'row',
    gap: 7,
    paddingRight: 8,
  },
  dot: {
    width: 8,
    height: 8,
    marginTop: 4,
    borderRadius: 3,
  },
  legendText: {
    flex: 1,
    minWidth: 0,
  },
  legendAmount: {
    fontFamily: typeStyles.rowTitle.fontFamily,
  },
});
