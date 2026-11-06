import type { LucideIcon } from 'lucide-react-native';
import { ActivityIndicator, Pressable, StyleSheet, View, type PressableProps } from 'react-native';

import { colors, radius, size } from '@/theme';

import { AppText } from './AppText';

type Variant = 'primary' | 'secondary' | 'warn';

type Props = Omit<PressableProps, 'children' | 'style'> & {
  label: string;
  variant?: Variant;
  icon?: LucideIcon;
  loading?: boolean;
};

export function Button({ label, variant = 'primary', icon: Icon, loading, disabled, ...rest }: Props) {
  const inactive = disabled || loading;
  const v = variants[variant];
  const textColor = inactive && variant !== 'secondary' ? 'textMuted' : v.text;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled: !!inactive, busy: !!loading }}
      disabled={inactive}
      {...rest}
      style={({ pressed, hovered }: { pressed: boolean; hovered?: boolean }) => [
        styles.base,
        v.container,
        inactive && variant !== 'secondary' && styles.disabled,
        inactive && variant === 'secondary' && styles.secondaryDisabled,
        (pressed || hovered) && !inactive && styles.pressed,
      ]}
    >
      <View style={styles.content}>
        {loading ? (
          <ActivityIndicator color={colors[textColor]} />
        ) : (
          <>
            {Icon && <Icon size={18} strokeWidth={2} color={colors[textColor]} />}
            <AppText variant="rowTitle" color={textColor}>
              {label}
            </AppText>
          </>
        )}
      </View>
    </Pressable>
  );
}

export function PrimaryButton(props: Omit<Props, 'variant'>) {
  return <Button {...props} variant="primary" />;
}

export function SecondaryButton(props: Omit<Props, 'variant'>) {
  return <Button {...props} variant="secondary" />;
}

export function WarnButton(props: Omit<Props, 'variant'>) {
  return <Button {...props} variant="warn" />;
}

const variants = {
  primary: {
    container: { height: size.buttonPrimary, borderRadius: 16, backgroundColor: colors.accent },
    text: 'onAccent',
  },
  secondary: {
    container: {
      height: size.buttonSecondary,
      borderRadius: radius.iconButton,
      backgroundColor: 'transparent',
      borderWidth: 1,
      borderColor: colors.borderStrong,
    },
    text: 'text',
  },
  warn: {
    container: { height: size.buttonPrimary, borderRadius: 16, backgroundColor: colors.warn },
    text: 'onWarn',
  },
} as const;

const styles = StyleSheet.create({
  base: {
    alignSelf: 'stretch',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 16,
  },
  content: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  disabled: {
    backgroundColor: colors.disabled,
  },
  secondaryDisabled: {
    opacity: 0.5,
  },
  pressed: {
    opacity: 0.85,
  },
});
