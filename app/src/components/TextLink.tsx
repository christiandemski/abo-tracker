import { Pressable, StyleSheet } from 'react-native';

import { fonts } from '@/theme';

import { AppText, type TextVariant } from './AppText';

type Props = {
  label: string;
  onPress: () => void;
  variant?: TextVariant;
};

/** Inline text action in accent color, padded to a 44 px touch target. */
export function TextLink({ label, onPress, variant = 'small' }: Props) {
  return (
    <Pressable accessibilityRole="link" onPress={onPress} hitSlop={8} style={styles.touch}>
      {({ hovered }: { pressed: boolean; hovered?: boolean }) => (
        <AppText variant={variant} color={hovered ? 'accentHover' : 'accent'} style={styles.label}>
          {label}
        </AppText>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  touch: {
    minHeight: 44,
    justifyContent: 'center',
    alignSelf: 'flex-start',
  },
  label: {
    fontFamily: fonts.bodyMedium,
  },
});
