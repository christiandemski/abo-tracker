import { StyleSheet, View } from 'react-native';

import { colors } from '@/theme';

/** Segments 4 px high: done = accent, current = accentProgress, open = borderStrong. */
export function StepProgress({ step, total }: { step: number; total: number }) {
  return (
    <View
      accessibilityRole="progressbar"
      accessibilityLabel={`Schritt ${step} von ${total}`}
      accessibilityValue={{ min: 1, max: total, now: step }}
      style={styles.row}
    >
      {Array.from({ length: total }, (_, i) => (
        <View
          key={i}
          style={[
            styles.segment,
            { backgroundColor: i + 1 < step ? colors.accent : i + 1 === step ? colors.accentProgress : colors.borderStrong },
          ]}
        />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    gap: 6,
  },
  segment: {
    flex: 1,
    height: 4,
    borderRadius: 99,
  },
});
