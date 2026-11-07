import { StyleSheet, View } from 'react-native';

import { colors } from '@/theme';

import { AppText } from './AppText';

const LABELS = {
  active: 'Aktiv',
  cancelled: 'Gekündigt',
  paused: 'Pausiert',
  finished: 'Abbezahlt',
} as const;

/** label overrides the default text, e.g. "Beendet" for a savings plan */
export function StatusBadge({ status, label }: { status: keyof typeof LABELS; label?: string }) {
  return (
    <View style={styles.badge}>
      <AppText variant="tab" color="textMuted">
        {label ?? LABELS[status]}
      </AppText>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 99,
    borderWidth: 1,
    borderColor: colors.borderStrong,
    backgroundColor: colors.surface,
  },
});
