import { Pressable, StyleSheet, View } from 'react-native';

import { colors, radius, size } from '@/theme';

import { AppText } from './AppText';

type Option<T extends string> = { value: T; label: string };

type Props<T extends string> = {
  options: readonly Option<T>[];
  value: T;
  onChange: (value: T) => void;
  accessibilityLabel: string;
  /** Stretch segments to the full width (e.g. rhythm picker) */
  stretch?: boolean;
};

export function Segmented<T extends string>({ options, value, onChange, accessibilityLabel, stretch }: Props<T>) {
  return (
    <View accessibilityRole="radiogroup" accessibilityLabel={accessibilityLabel} style={[styles.group, stretch && styles.stretch]}>
      {options.map((option) => {
        const active = option.value === value;
        return (
          <Pressable
            key={option.value}
            accessibilityRole="radio"
            accessibilityState={{ checked: active }}
            onPress={() => onChange(option.value)}
            style={[styles.segment, stretch && styles.segmentStretch, active && styles.active]}
          >
            <AppText variant="small" color={active ? 'onAccent' : 'textMuted'} style={styles.label}>
              {option.label}
            </AppText>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  group: {
    flexDirection: 'row',
    alignSelf: 'flex-start',
    padding: 3,
    gap: 2,
    borderRadius: radius.input,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.bg,
  },
  stretch: {
    alignSelf: 'stretch',
  },
  segment: {
    height: size.touch,
    minWidth: 68,
    paddingHorizontal: 12,
    borderRadius: radius.segment,
    alignItems: 'center',
    justifyContent: 'center',
  },
  segmentStretch: {
    flex: 1,
  },
  active: {
    backgroundColor: colors.accent,
  },
  label: {
    fontWeight: '600',
  },
});
