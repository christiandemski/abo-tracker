import { router } from 'expo-router';
import { ChevronLeft } from 'lucide-react-native';
import { StyleSheet, View } from 'react-native';

import { AppText } from './AppText';
import { IconButton } from './IconButton';

type Props = {
  title: string;
  subtitle?: string;
  /** Show a back button (falls back to login if there is no history, e.g. opened from a mail link). */
  back?: boolean;
};

export function AuthHeader({ title, subtitle, back }: Props) {
  return (
    <View style={styles.wrapper}>
      {back && (
        <IconButton
          icon={ChevronLeft}
          accessibilityLabel="Zurück"
          onPress={() => (router.canGoBack() ? router.back() : router.replace('/login'))}
        />
      )}
      <View style={styles.titles}>
        <AppText variant="h1" accessibilityRole="header">
          {title}
        </AppText>
        {subtitle ? (
          <AppText variant="body" color="textMuted">
            {subtitle}
          </AppText>
        ) : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    paddingTop: 24,
    paddingBottom: 8,
    gap: 16,
  },
  titles: {
    gap: 6,
  },
});
