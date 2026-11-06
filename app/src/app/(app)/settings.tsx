import { router } from 'expo-router';
import { Bell, ChevronRight, LogOut } from 'lucide-react-native';
import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import { AppText, Card, Notice, Screen, SecondaryButton, TextField } from '@/components';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useAuth } from '@/lib/auth';
import { useForm } from '@/lib/useForm';
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

      <SenderForm />

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

/** Sender for cancellation letters, stored on the account. */
function SenderForm() {
  const { user, api, refreshUser } = useAuth();
  const form = useForm({
    name: user?.name ?? '',
    address_street: user?.address_street ?? '',
    address_zip: user?.address_zip ?? '',
    address_city: user?.address_city ?? '',
  });
  const [saved, setSaved] = useState(false);

  const onSave = () =>
    form.submit(async (v) => {
      setSaved(false);
      await api.patch('me', {
        name: v.name.trim(),
        address_street: v.address_street.trim() || null,
        address_zip: v.address_zip.trim() || null,
        address_city: v.address_city.trim() || null,
      });
      await refreshUser();
      setSaved(true);
    });

  return (
    <Card style={styles.section}>
      <AppText variant="small" color="textMuted">
        Absender für Kündigungsschreiben
      </AppText>
      {form.formError && <Notice tone="danger">{form.formError}</Notice>}
      {saved && <Notice tone="success">Gespeichert.</Notice>}
      <TextField label="Name" value={form.values.name} onChangeText={form.set('name')} error={form.errors.name} autoComplete="name" />
      <TextField
        label="Straße und Hausnummer"
        value={form.values.address_street}
        onChangeText={form.set('address_street')}
        error={form.errors.address_street}
        autoComplete="street-address"
      />
      <View style={styles.twoCols}>
        <View style={styles.zip}>
          <TextField
            label="PLZ"
            value={form.values.address_zip}
            onChangeText={form.set('address_zip')}
            error={form.errors.address_zip}
            keyboardType="number-pad"
            autoComplete="postal-code"
          />
        </View>
        <View style={styles.flex}>
          <TextField label="Ort" value={form.values.address_city} onChangeText={form.set('address_city')} error={form.errors.address_city} />
        </View>
      </View>
      <SecondaryButton label="Speichern" onPress={onSave} loading={form.submitting} />
    </Card>
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
  twoCols: {
    flexDirection: 'row',
    gap: 10,
  },
  zip: {
    width: 96,
  },
  flex: {
    flex: 1,
  },
  value: {
    flexShrink: 1,
    textAlign: 'right',
  },
});
