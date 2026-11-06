import { StyleSheet, Text, type TextProps, type TextStyle } from 'react-native';

import { amount as amountStyle, colors, type as typeStyles } from '@/theme';

export type TextVariant = keyof typeof typeStyles;

type Props = TextProps & {
  variant?: TextVariant;
  color?: keyof typeof colors;
  /** Amounts: tabular numbers so columns line up. */
  amount?: boolean;
};

export function AppText({ variant = 'body', color = 'text', amount, style, ...rest }: Props) {
  return (
    <Text
      {...rest}
      style={[
        styles.base,
        typeStyles[variant],
        { color: colors[color] },
        amount && (amountStyle as TextStyle),
        style,
      ]}
    />
  );
}

const styles = StyleSheet.create({
  base: {
    includeFontPadding: false,
  },
});
