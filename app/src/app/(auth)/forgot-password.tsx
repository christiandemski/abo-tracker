import { router } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

import { AuthHeader, Notice, PrimaryButton, Screen, SecondaryButton, TextField } from '@/components';
import { useAuth } from '@/lib/auth';
import { useForm } from '@/lib/useForm';

export default function ForgotPasswordScreen() {
  const { requestPasswordReset } = useAuth();
  const form = useForm({ email: '' });
  const [sent, setSent] = useState<string | null>(null);

  const onSubmit = () =>
    form.submit(async ({ email }) => {
      setSent(await requestPasswordReset(email.trim()));
    });

  return (
    <Screen narrow>
      <AuthHeader
        title="Passwort vergessen"
        subtitle="Gib deine E-Mail-Adresse ein. Wir schicken dir einen Link für ein neues Passwort."
        back
      />

      <View style={styles.form}>
        {sent ? (
          <>
            <Notice tone="success" title="Schau in dein Postfach">
              {sent}
            </Notice>
            <SecondaryButton label="Zur Anmeldung" onPress={() => router.replace('/login')} />
          </>
        ) : (
          <>
            {form.formError && <Notice tone="danger">{form.formError}</Notice>}
            <TextField
              label="E-Mail"
              value={form.values.email}
              onChangeText={form.set('email')}
              error={form.errors.email}
              placeholder="du@beispiel.de"
              autoCapitalize="none"
              autoComplete="email"
              keyboardType="email-address"
              textContentType="emailAddress"
              returnKeyType="send"
              onSubmitEditing={onSubmit}
            />
            <PrimaryButton
              label="Link schicken"
              onPress={onSubmit}
              disabled={form.values.email.trim() === ''}
              loading={form.submitting}
            />
          </>
        )}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  form: {
    marginTop: 16,
    gap: 14,
  },
});
