import { useEffect, useState } from 'react';
import { Animated, StyleSheet, View, type DimensionValue } from 'react-native';

import { colors, radius } from '@/theme';

type Props = {
  height: number;
  width?: DimensionValue;
  rounded?: number;
};

/** Loading placeholder in `surface`, gently pulsing. */
export function Skeleton({ height, width = '100%', rounded = radius.card }: Props) {
  const [opacity] = useState(() => new Animated.Value(0.6));

  useEffect(() => {
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(opacity, { toValue: 1, duration: 700, useNativeDriver: true }),
        Animated.timing(opacity, { toValue: 0.6, duration: 700, useNativeDriver: true }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [opacity]);

  return (
    <Animated.View
      accessibilityLabel="Wird geladen"
      style={[styles.block, { height, width, borderRadius: rounded, opacity }]}
    />
  );
}

export function SkeletonList({ rows = 3 }: { rows?: number }) {
  return (
    <View style={styles.list}>
      {Array.from({ length: rows }, (_, i) => (
        <Skeleton key={i} height={56} rounded={14} />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  block: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
  },
  list: {
    gap: 10,
  },
});
