import { StyleSheet, View, type ViewProps } from 'react-native';

import { colors, radius } from '@/theme';

type Props = ViewProps & {
  /** hero = large overview cards (radius 24, padding 18) */
  size?: 'default' | 'hero';
  tone?: 'default' | 'accent' | 'warn';
};

export function Card({ size = 'default', tone = 'default', style, ...rest }: Props) {
  return <View {...rest} style={[styles.base, styles[size], toneStyles[tone], style]} />;
}

const styles = StyleSheet.create({
  base: {
    borderWidth: 1,
    gap: 12,
  },
  default: {
    borderRadius: radius.card,
    padding: 16,
  },
  hero: {
    borderRadius: radius.hero,
    padding: 18,
    gap: 14,
  },
});

const toneStyles = StyleSheet.create({
  default: { backgroundColor: colors.surface, borderColor: colors.border },
  accent: { backgroundColor: colors.accentSurface, borderColor: colors.accentBorder },
  // Orange only for action required (deadlines, price increases)
  warn: { backgroundColor: colors.warnSurface, borderColor: colors.warnBorder },
});
