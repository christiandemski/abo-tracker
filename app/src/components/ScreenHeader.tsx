import { router } from 'expo-router';
import { ChevronLeft, X } from 'lucide-react-native';
import type { ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';

import { AppText } from './AppText';
import { IconButton } from './IconButton';

type Props = {
  title: string;
  /** Small line above the title (e.g. today's date on the overview) */
  overline?: string;
  /** back = sub page, close = modal; omitted on tab screens */
  nav?: 'back' | 'close';
  right?: ReactNode;
};

function leave() {
  if (router.canGoBack()) router.back();
  else router.replace('/');
}

export function ScreenHeader({ title, overline, nav, right }: Props) {
  const sub = nav !== undefined;

  return (
    <View style={[styles.header, sub && styles.subHeader]}>
      {nav === 'back' && <IconButton icon={ChevronLeft} accessibilityLabel="Zurück" onPress={leave} />}
      {nav === 'close' && <IconButton icon={X} accessibilityLabel="Schließen" onPress={leave} />}
      <View style={styles.titles}>
        {overline ? (
          <AppText variant="small" color="textMuted">
            {overline}
          </AppText>
        ) : null}
        <AppText variant={sub ? 'h1Sub' : 'h1'} accessibilityRole="header">
          {title}
        </AppText>
      </View>
      {right}
    </View>
  );
}

const styles = StyleSheet.create({
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingTop: 24,
    paddingBottom: 4,
  },
  subHeader: {
    paddingTop: 20,
  },
  titles: {
    flex: 1,
    gap: 2,
  },
});
