import type { LucideIcon } from 'lucide-react-native';
import type { ReactNode } from 'react';
import { Pressable, StyleSheet, View, type PressableProps } from 'react-native';

import { colors, radius, size } from '@/theme';

type Props = Omit<PressableProps, 'children' | 'style'> & {
  icon: LucideIcon;
  /** Required: icon-only buttons need a spoken label. */
  accessibilityLabel: string;
  badge?: ReactNode;
};

export function IconButton({ icon: Icon, badge, ...rest }: Props) {
  return (
    <Pressable
      accessibilityRole="button"
      hitSlop={4}
      {...rest}
      style={({ pressed }) => [styles.button, pressed && styles.pressed]}
    >
      <Icon size={20} strokeWidth={1.8} color={colors.text} />
      {badge ? <View style={styles.badge}>{badge}</View> : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  button: {
    width: size.touch,
    height: size.touch,
    borderRadius: radius.iconButton,
    borderWidth: 1,
    borderColor: colors.borderStrong,
    backgroundColor: colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
  },
  pressed: {
    opacity: 0.8,
  },
  badge: {
    position: 'absolute',
    top: 9,
    right: 10,
  },
});
