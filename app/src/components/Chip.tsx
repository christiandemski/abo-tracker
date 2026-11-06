import { Pressable, StyleSheet, View } from 'react-native';

import { colors, fonts } from '@/theme';

import { AppText } from './AppText';

type Props = {
  label: string;
  selected: boolean;
  onPress: () => void;
  /** Optional category color dot – always next to the text label, never alone */
  dotColor?: string | null;
  /** radio inside a radiogroup (single choice), otherwise a toggle button */
  role?: 'radio' | 'button';
};

/** Selectable pill, 44 px high touch target. */
export function Chip({ label, selected, onPress, dotColor, role = 'radio' }: Props) {
  return (
    <Pressable
      accessibilityRole={role}
      accessibilityState={role === 'radio' ? { checked: selected } : { selected }}
      onPress={onPress}
      style={({ pressed }) => [styles.chip, selected && styles.selected, pressed && styles.pressed]}
    >
      {dotColor ? <View style={[styles.dot, { backgroundColor: dotColor }]} /> : null}
      <AppText variant="small" color={selected ? 'text' : 'textMuted'} style={selected && styles.selectedLabel}>
        {label}
      </AppText>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  chip: {
    minHeight: 44,
    paddingHorizontal: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.borderStrong,
    backgroundColor: colors.surface,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  selected: {
    backgroundColor: colors.surfaceSelected,
    borderColor: colors.accent,
  },
  pressed: {
    opacity: 0.8,
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 3,
  },
  selectedLabel: {
    fontFamily: fonts.bodySemi,
  },
});
