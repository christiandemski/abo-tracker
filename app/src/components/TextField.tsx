import { Eye, EyeOff } from 'lucide-react-native';
import { useState, type ComponentProps } from 'react';
import { Platform, Pressable, StyleSheet, TextInput, View, type TextInputProps } from 'react-native';

import { colors, fonts, radius } from '@/theme';

import { AppText } from './AppText';

type Props = Omit<TextInputProps, 'style' | 'secureTextEntry'> & {
  ref?: ComponentProps<typeof TextInput>['ref'];
  label: string;
  error?: string;
  hint?: string;
  /** Password field with show/hide toggle */
  secure?: boolean;
};

export function TextField({ ref, label, error, hint, secure, onFocus, onBlur, ...rest }: Props) {
  const [focused, setFocused] = useState(false);
  const [revealed, setRevealed] = useState(false);

  return (
    <View style={styles.wrapper}>
      <AppText variant="caption" color="textMuted">
        {label}
      </AppText>
      <View
        style={[
          styles.field,
          focused && styles.focused,
          !!error && styles.invalid,
        ]}
      >
        <TextInput
          ref={ref}
          accessibilityLabel={label}
          placeholderTextColor={colors.placeholder}
          selectionColor={colors.accent}
          cursorColor={colors.accent}
          secureTextEntry={secure && !revealed}
          onFocus={(e) => {
            setFocused(true);
            onFocus?.(e);
          }}
          onBlur={(e) => {
            setFocused(false);
            onBlur?.(e);
          }}
          {...rest}
          style={styles.input}
        />
        {secure && (
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={revealed ? 'Passwort verbergen' : 'Passwort anzeigen'}
            onPress={() => setRevealed((v) => !v)}
            style={styles.reveal}
          >
            {revealed ? (
              <EyeOff size={18} strokeWidth={2} color={colors.textMuted} />
            ) : (
              <Eye size={18} strokeWidth={2} color={colors.textMuted} />
            )}
          </Pressable>
        )}
      </View>
      {error ? (
        <AppText variant="caption" color="danger" accessibilityLiveRegion="polite">
          {error}
        </AppText>
      ) : hint ? (
        <AppText variant="caption" color="textMuted">
          {hint}
        </AppText>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    gap: 6,
  },
  field: {
    minHeight: 46,
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: radius.input,
    borderWidth: 1,
    borderColor: colors.borderStrong,
    backgroundColor: colors.surface,
  },
  focused: {
    borderColor: colors.accent,
  },
  invalid: {
    borderColor: colors.dangerBorder,
  },
  input: {
    flex: 1,
    minHeight: 44,
    paddingHorizontal: 14,
    color: colors.text,
    fontFamily: fonts.body,
    fontSize: 15,
    ...Platform.select({ web: { outlineStyle: 'none' } as object }),
  },
  reveal: {
    width: 44,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
