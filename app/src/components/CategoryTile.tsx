import { StyleSheet, View } from 'react-native';

import { categoryColors, colors, fonts } from '@/theme';

import { AppText } from './AppText';

const SIZES = {
  list: { box: 42, radius: 13, font: 17 },
  detail: { box: 56, radius: 18, font: 24 },
  hero: { box: 72, radius: 22, font: 30 },
  small: { box: 30, radius: 10, font: 14 },
} as const;

type Props = {
  name: string;
  /** Category color from the API; falls back to "Sonstiges" */
  color?: string | null;
  size?: keyof typeof SIZES;
};

/**
 * Initial on the category color. Decorative only: the category name is always shown next to it.
 */
export function CategoryTile({ name, color, size = 'list' }: Props) {
  const s = SIZES[size];

  return (
    <View
      accessible={false}
      importantForAccessibility="no-hide-descendants"
      style={[
        styles.tile,
        { width: s.box, height: s.box, borderRadius: s.radius, backgroundColor: color ?? categoryColors.other },
      ]}
    >
      <AppText style={[styles.initial, { fontSize: s.font }]} color="onAccent">
        {name.trim().charAt(0).toUpperCase() || '?'}
      </AppText>
    </View>
  );
}

const styles = StyleSheet.create({
  tile: {
    alignItems: 'center',
    justifyContent: 'center',
    flexShrink: 0,
  },
  initial: {
    fontFamily: fonts.displayBold,
    color: colors.onAccent,
  },
});
