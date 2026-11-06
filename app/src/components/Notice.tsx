import { CircleCheck, CircleAlert, Info, type LucideIcon } from 'lucide-react-native';
import type { ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';

import { colors, radius } from '@/theme';

import { AppText } from './AppText';

type Tone = 'info' | 'success' | 'danger';

type Props = {
  tone?: Tone;
  title?: string;
  children: ReactNode;
  action?: ReactNode;
};

const TONES: Record<Tone, { icon: LucideIcon; bg: string; border: string; fg: keyof typeof colors }> = {
  info: { icon: Info, bg: colors.accentSurface, border: colors.accentBorder, fg: 'accentText' },
  success: { icon: CircleCheck, bg: colors.successSurface, border: colors.successBorder, fg: 'success' },
  danger: { icon: CircleAlert, bg: colors.dangerSurface, border: colors.dangerBorder, fg: 'danger' },
};

/**
 * Inline message (errors, confirmations). Deliberately no orange tone:
 * orange is reserved for deadlines and price increases (WarnCard, later).
 */
export function Notice({ tone = 'info', title, children, action }: Props) {
  const t = TONES[tone];
  const Icon = t.icon;

  return (
    <View
      accessibilityRole={tone === 'danger' ? 'alert' : 'summary'}
      accessibilityLiveRegion="polite"
      style={[styles.box, { backgroundColor: t.bg, borderColor: t.border }]}
    >
      <Icon size={18} strokeWidth={2} color={colors[t.fg]} />
      <View style={styles.body}>
        {title ? (
          <AppText variant="rowTitle" color={t.fg}>
            {title}
          </AppText>
        ) : null}
        <AppText variant="body" color="text">
          {children}
        </AppText>
        {action}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  box: {
    flexDirection: 'row',
    gap: 10,
    padding: 14,
    borderRadius: radius.iconButton,
    borderWidth: 1,
  },
  body: {
    flex: 1,
    gap: 4,
  },
});
