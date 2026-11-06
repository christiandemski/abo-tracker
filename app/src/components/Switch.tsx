import { Pressable, StyleSheet, View } from 'react-native';

import { colors } from '@/theme';

type Props = {
  value: boolean;
  onValueChange: (value: boolean) => void;
  accessibilityLabel: string;
  disabled?: boolean;
};

/** 48 × 28 track, 22 px knob; touch target padded to 44 px height. */
export function Switch({ value, onValueChange, accessibilityLabel, disabled }: Props) {
  return (
    <Pressable
      accessibilityRole="switch"
      accessibilityLabel={accessibilityLabel}
      accessibilityState={{ checked: value, disabled }}
      disabled={disabled}
      onPress={() => onValueChange(!value)}
      hitSlop={8}
      style={[styles.touch, disabled && styles.disabled]}
    >
      <View style={[styles.track, { backgroundColor: value ? colors.accent : colors.switchOff }]}>
        <View
          style={[
            styles.knob,
            value ? styles.knobOn : styles.knobOff,
            { backgroundColor: value ? colors.onAccent : colors.textMuted },
          ]}
        />
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  touch: {
    height: 44,
    justifyContent: 'center',
  },
  disabled: {
    opacity: 0.5,
  },
  track: {
    width: 48,
    height: 28,
    borderRadius: 99,
  },
  knob: {
    position: 'absolute',
    top: 3,
    width: 22,
    height: 22,
    borderRadius: 99,
  },
  knobOn: { right: 3 },
  knobOff: { left: 3 },
});
