import AsyncStorage from '@react-native-async-storage/async-storage';
import { router } from 'expo-router';
import { ClipboardCheck, X } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import { useAuth } from '@/lib/auth';
import type { Resource, UsageCheckState } from '@/lib/types';
import { useQuery } from '@/lib/useQuery';
import { colors } from '@/theme';

import { AppText } from './AppText';
import { TextLink } from './TextLink';

const DISMISS_KEY = 'cost-tracker.check-dismissed-at';
const DISMISS_DAYS = 30;

/** Overview hint: shown when the last usage check is more than 3 months ago (dismissible). */
export function UsageCheckCard() {
  const { api } = useAuth();
  const check = useQuery('usage-check', async () => (await api.get<Resource<UsageCheckState>>('usage-check/next')).data);
  const [dismissed, setDismissed] = useState<boolean | null>(null);

  useEffect(() => {
    AsyncStorage.getItem(DISMISS_KEY)
      .then((value) => {
        const at = value ? Date.parse(value) : NaN;
        setDismissed(!Number.isNaN(at) && Date.now() - at < DISMISS_DAYS * 24 * 3600 * 1000);
      })
      .catch(() => setDismissed(false));
  }, []);

  if (!check.data?.due || dismissed !== false) return null;

  function dismiss() {
    setDismissed(true);
    void AsyncStorage.setItem(DISMISS_KEY, new Date().toISOString()).catch(() => {});
  }

  return (
    <View style={styles.card}>
      <ClipboardCheck size={22} strokeWidth={1.8} color={colors.accent} />
      <View style={styles.text}>
        <AppText variant="rowTitle">Lohnt sich das noch?</AppText>
        <AppText variant="small" color="textMuted">
          Geh deine Abos in einer Minute durch und finde, was du kaum nutzt.
        </AppText>
        <TextLink label="Quartals-Check starten" onPress={() => router.push('/check')} />
      </View>
      <Pressable accessibilityRole="button" accessibilityLabel="Hinweis ausblenden" onPress={dismiss} hitSlop={8} style={styles.close}>
        <X size={18} strokeWidth={2} color={colors.textMuted} />
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    flexDirection: 'row',
    gap: 12,
    padding: 16,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: colors.accentBorder,
    backgroundColor: colors.accentSurface,
  },
  text: {
    flex: 1,
    gap: 2,
  },
  close: {
    width: 44,
    height: 44,
    marginTop: -12,
    marginRight: -12,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
