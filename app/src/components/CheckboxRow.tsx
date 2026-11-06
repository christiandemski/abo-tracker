import { Check } from 'lucide-react-native';
import { Pressable, StyleSheet, View } from 'react-native';

import { colors, radius } from '@/theme';

import { AppText } from './AppText';

type Props = {
  title: string;
  /** Line under the title, e.g. "10× gefunden · Streaming" */
  tag: string;
  /** Orange tag: only for "Rhythmus prüfen" / price change (action required) */
  tagWarn?: boolean;
  amount: string;
  amountMeta?: string;
  checked: boolean;
  disabled?: boolean;
  onToggle: () => void;
};

/** Whole row is the touch target (min. 64 px), DESIGN.md "Checkbox-Row". */
export function CheckboxRow({ title, tag, tagWarn, amount, amountMeta, checked, disabled, onToggle }: Props) {
  const on = checked && !disabled;

  return (
    <Pressable
      accessibilityRole="checkbox"
      accessibilityState={{ checked: on, disabled }}
      accessibilityLabel={`${title}, ${amount}${amountMeta ? ' ' + amountMeta : ''}, ${tag}`}
      disabled={disabled}
      onPress={onToggle}
      style={[styles.row, on ? styles.rowOn : styles.rowOff, disabled && styles.disabled]}
    >
      <View style={[styles.box, on ? styles.boxOn : styles.boxOff]}>
        {on && <Check size={14} strokeWidth={3} color={colors.onAccent} />}
      </View>
      <View style={styles.text}>
        <AppText variant="rowTitle" numberOfLines={1}>
          {title}
        </AppText>
        <AppText variant="caption" color={tagWarn ? 'warn' : 'textMuted'} numberOfLines={1}>
          {tag}
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
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: {
    minHeight: 64,
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderRadius: 16,
    borderWidth: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  rowOn: {
    backgroundColor: colors.surfaceSelected,
    borderColor: colors.borderSelected,
  },
  rowOff: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
  },
  disabled: {
    opacity: 0.6,
  },
  box: {
    width: 24,
    height: 24,
    borderRadius: radius.segment - 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  boxOn: {
    backgroundColor: colors.accent,
  },
  boxOff: {
    borderWidth: 2,
    borderColor: colors.checkboxOff,
  },
  text: {
    flex: 1,
    minWidth: 0,
    gap: 2,
  },
  amount: {
    alignItems: 'flex-end',
  },
});
