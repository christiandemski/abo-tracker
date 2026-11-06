import { TriangleAlert } from 'lucide-react-native';
import { Pressable, StyleSheet, View } from 'react-native';

import { formatCents, formatShortDate, formatWeekdayDate } from '@/lib/format';
import type { UpcomingItem } from '@/lib/types';
import { colors, fonts, radius } from '@/theme';

import { AppText } from './AppText';

type Props = {
  item: UpcomingItem;
  onPress: () => void;
};

/**
 * "Demnächst" card. Deadlines are orange (action required), charges neutral.
 */
export function UpcomingCard({ item, onPress }: Props) {
  const { subscription: s } = item;
  const deadline = item.type === 'deadline';
  const amount = formatCents(s.amount_cents);

  const meta = deadline
    ? `${amount}${s.term_end_date ? ` · verlängert ${formatShortDate(s.term_end_date)}` : ''}`
    : `${amount} · ${s.rhythm_label}`;
  const label = deadline ? `Kündbar bis ${formatShortDate(item.date)}` : formatWeekdayDate(item.date);

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${s.name}: ${label}, ${meta}`}
      onPress={onPress}
      style={({ pressed }) => [styles.card, deadline ? styles.deadline : styles.charge, pressed && styles.pressed]}
    >
      <View style={styles.label}>
        {deadline && <TriangleAlert size={14} strokeWidth={2} color={colors.warn} />}
        <AppText variant="caption" color={deadline ? 'warn' : 'textMuted'} style={styles.labelText}>
          {label}
        </AppText>
      </View>
      <AppText variant="body" numberOfLines={2} style={styles.name}>
        {s.name}
      </AppText>
      <AppText variant="caption" color={deadline ? 'warnTextMuted' : 'textMuted'} amount numberOfLines={2}>
        {meta}
      </AppText>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: {
    width: 158,
    padding: 14,
    gap: 8,
    borderRadius: radius.card,
    borderWidth: 1,
  },
  deadline: {
    backgroundColor: colors.warnSurface,
    borderColor: colors.warnBorder,
  },
  charge: {
    width: 140,
    backgroundColor: colors.surface,
    borderColor: colors.border,
  },
  pressed: {
    opacity: 0.8,
  },
  label: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  labelText: {
    fontFamily: fonts.bodySemi,
  },
  name: {
    fontFamily: fonts.bodySemi,
  },
});
