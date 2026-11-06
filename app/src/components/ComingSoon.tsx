import type { LucideIcon } from 'lucide-react-native';
import { StyleSheet, View } from 'react-native';

import { colors } from '@/theme';

import { AppText } from './AppText';
import { Card } from './Card';

type Props = {
  icon: LucideIcon;
  title: string;
  text: string;
};

/** Placeholder for areas that are built in later milestones (keeps navigation free of dead ends). */
export function ComingSoon({ icon: Icon, title, text }: Props) {
  return (
    <Card style={styles.card}>
      <View style={styles.icon}>
        <Icon size={22} strokeWidth={1.8} color={colors.accent} />
      </View>
      <AppText variant="h2">{title}</AppText>
      <AppText variant="body" color="textMuted">
        {text}
      </AppText>
    </Card>
  );
}

const styles = StyleSheet.create({
  card: {
    marginTop: 16,
    alignItems: 'flex-start',
  },
  icon: {
    width: 44,
    height: 44,
    borderRadius: 14,
    backgroundColor: colors.accentSurface,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
