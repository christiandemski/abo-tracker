import { Pressable, StyleSheet, View } from 'react-native';

import { colors } from '@/theme';

import { AppText } from './AppText';
import { CategoryTile } from './CategoryTile';
import { StatusBadge } from './StatusBadge';

type Props = {
  name: string;
  meta: string;
  amount: string;
  /** Second line under the amount, e.g. "/ Monat" */
  amountMeta?: string;
  categoryColor?: string | null;
  status?: 'active' | 'cancelled' | 'paused' | 'finished';
  /** Text of the status badge if it differs from the default */
  statusLabel?: string;
  last?: boolean;
  onPress?: () => void;
};

export function SubscriptionRow({
  name,
  meta,
  amount,
  amountMeta,
  categoryColor,
  status = 'active',
  statusLabel,
  last,
  onPress,
}: Props) {
  const inactive = status !== 'active';

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${name}, ${amount}${amountMeta ? ' ' + amountMeta : ''}, ${meta}`}
      onPress={onPress}
      disabled={!onPress}
      style={({ pressed }) => [styles.row, !last && styles.divider, pressed && styles.pressed]}
    >
      <View style={[styles.inner, inactive && styles.dimmed]}>
        <CategoryTile name={name} color={categoryColor} />
        <View style={styles.text}>
          <View style={styles.titleLine}>
            <AppText variant="rowTitle" numberOfLines={1} style={styles.name}>
              {name}
            </AppText>
            {inactive && <StatusBadge status={status} label={statusLabel} />}
          </View>
          <AppText variant="caption" color="textMuted" numberOfLines={1}>
            {meta}
          </AppText>
        </View>
        <View style={styles.amount}>
          <AppText variant="rowTitle" amount>
            {amount}
          </AppText>
          {amountMeta ? (
            <AppText variant="tab" color="textMuted">
              {amountMeta}
            </AppText>
          ) : null}
        </View>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: {
    minHeight: 64,
    justifyContent: 'center',
    paddingVertical: 10,
  },
  divider: {
    borderBottomWidth: 1,
    borderBottomColor: colors.divider,
  },
  pressed: {
    opacity: 0.7,
  },
  inner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  dimmed: {
    opacity: 0.6,
  },
  text: {
    flex: 1,
    minWidth: 0,
    gap: 2,
  },
  titleLine: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  name: {
    flexShrink: 1,
  },
  amount: {
    alignItems: 'flex-end',
  },
});
