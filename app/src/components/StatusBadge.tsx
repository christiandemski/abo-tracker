import { StyleSheet, View } from 'react-native';

import { colors } from '@/theme';

import { AppText } from './AppText';

const LABELS = {
  active: 'Aktiv',
  cancelled: 'Gekündigt',
  paused: 'Pausiert',
} as const;

export function StatusBadge({ status }: { status: keyof typeof LABELS }) {
  return (
    <View style={styles.badge}>
      <AppText variant="tab" color="textMuted">
        {LABELS[status]}
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
