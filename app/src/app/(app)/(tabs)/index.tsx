import { router } from 'expo-router';
import { CirclePlus, FileUp, Settings } from 'lucide-react-native';
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

import { AppText, Card, IconButton, Notice, PrimaryButton, Screen, SecondaryButton, TextLink } from '@/components';
import { ScreenHeader } from '@/components/ScreenHeader';
import { useAuth } from '@/lib/auth';
import { formatLongDate } from '@/lib/format';
import type { MessageResponse } from '@/lib/types';
import { fonts } from '@/theme';

export default function OverviewScreen() {
  const { user } = useAuth();

  return (
    <Screen>
      <ScreenHeader
        overline={formatLongDate(new Date())}
        title="Übersicht"
        right={
          <IconButton icon={Settings} accessibilityLabel="Einstellungen" onPress={() => router.push('/settings')} />
        }
      />

      {user && !user.email_verified && <VerifyEmailNotice />}

      <Card size="hero" style={styles.onboarding}>
        <AppText variant="small" color="textMuted" style={styles.label}>
          Fixkosten pro Monat
        </AppText>
        <AppText variant="h1">Noch keine Abos</AppText>
        <AppText variant="body" color="textMuted">
          Leg dein erstes Abo an oder importier einen Kontoauszug. Dann siehst du hier, was dich jeden Monat fest
          kostet und wann du kündigen kannst.
        </AppText>
        <View style={styles.actions}>
          <PrimaryButton label="Erstes Abo anlegen" icon={CirclePlus} onPress={() => router.push('/add')} />
          <SecondaryButton label="CSV importieren" icon={FileUp} onPress={() => router.push('/import')} />
        </View>
      </Card>
    </Screen>
  );
}

function VerifyEmailNotice() {
  const { api, refreshUser } = useAuth();
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function resend() {
    setBusy(true);
    try {
      const res = await api.post<MessageResponse>('auth/email/verification-notification');
      setMessage(res.message);
      await refreshUser();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Das hat nicht geklappt.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={styles.notice}>
      <Notice
        tone="info"
        title="Bitte bestätige deine E-Mail-Adresse"
        action={
          message ? (
            <AppText variant="small" color="textMuted">
              {message}
            </AppText>
          ) : (
            <TextLink label={busy ? 'Wird gesendet …' : 'Mail noch mal schicken'} onPress={busy ? () => {} : resend} />
          )
        }
      >
        Sonst kommen später keine Erinnerungen an Kündigungsfristen bei dir an.
      </Notice>
    </View>
  );
}

const styles = StyleSheet.create({
  notice: {
    marginTop: 16,
  },
  onboarding: {
    marginTop: 16,
  },
  label: {
    fontFamily: fonts.bodyMedium,
  },
  actions: {
    gap: 10,
    marginTop: 4,
  },
});
