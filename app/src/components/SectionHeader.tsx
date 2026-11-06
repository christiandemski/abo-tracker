import { StyleSheet, View } from 'react-native';

import { AppText } from './AppText';
import { TextLink } from './TextLink';

type Props = {
  title: string;
  action?: { label: string; onPress: () => void };
};

export function SectionHeader({ title, action }: Props) {
  return (
    <View style={styles.row}>
      <AppText variant="h2" accessibilityRole="header">
        {title}
      </AppText>
      {action && <TextLink label={action.label} onPress={action.onPress} />}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    minHeight: 44,
  },
});
