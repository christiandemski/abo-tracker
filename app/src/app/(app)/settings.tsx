import { LogOut } from 'lucide-react-native';
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

import { AppText, Card, Screen, SecondaryButton } from '@/components';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useAuth } from '@/lib/auth';
import { colors } from '@/theme';

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
