import { router } from 'expo-router';
import { Bell, ChevronRight, LogOut } from 'lucide-react-native';
import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import { AppText, Card, Screen, SecondaryButton } from '@/components';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useAuth } from '@/lib/auth';
import { colors, fonts } from '@/theme';

export default function SettingsScreen() {
  const { user, signOut } = useAuth();
  const [busy, setBusy] = useState(false);

  return (
    <Screen>
      <ScreenHeader title="Einstellungen" nav="back" />

      <Card style={styles.section}>
        <AppText variant="small" color="textMuted">
          Konto
        </AppText>
        <Row label="Name" value={user?.name ?? ''} />
        <Row label="E-Mail" value={user?.email ?? ''} />
        <Row label="Status" value={user?.email_verified ? 'Adresse bestätigt' : 'Adresse noch nicht bestätigt'} last />
      </Card>

      <Pressable
        accessibilityRole="button"
        onPress={() => router.push('/reminders')}
        style={({ pressed }) => [styles.section, styles.link, pressed && styles.pressed]}
      >
        <Bell size={20} strokeWidth={1.8} color={colors.textMuted} />
        <View style={styles.linkText}>
          <AppText variant="body" style={styles.medium}>
            Erinnerungen
          </AppText>
          <AppText variant="caption" color="textMuted">
            Push, E-Mail und Zeitpunkte
          </AppText>
        </View>
        <ChevronRight size={20} strokeWidth={1.8} color={colors.textMuted} />
      </Pressable>

      <View style={styles.section}>
        <SecondaryButton
          label="Abmelden"
          icon={LogOut}
          loading={busy}
          onPress={async () => {
            setBusy(true);
            await signOut();
          }}
        />
      </View>
    </Screen>
  );
}

function Row({ label, value, last }: { label: string; value: string; last?: boolean }) {
  return (
    <View style={[styles.row, !last && styles.divider]}>
      <AppText variant="body" color="textMuted">
        {label}
      </AppText>
      <AppText variant="body" style={styles.value} numberOfLines={1}>
        {value}
      </AppText>
    </View>
  );
}

const styles = StyleSheet.create({
  section: {
    marginTop: 22,
  },
  link: {
    minHeight: 64,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 14,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  linkText: {
    flex: 1,
    gap: 2,
  },
  medium: {
    fontFamily: fonts.bodyMedium,
  },
  pressed: {
    opacity: 0.8,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 12,
    minHeight: 44,
  },
  divider: {
    borderBottomWidth: 1,
    borderBottomColor: colors.divider,
  },
  value: {
    flexShrink: 1,
    textAlign: 'right',
  },
});
