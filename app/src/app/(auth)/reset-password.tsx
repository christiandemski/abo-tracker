import { router, useLocalSearchParams } from 'expo-router';
import { StyleSheet, View } from 'react-native';

import { AuthHeader, Notice, PrimaryButton, Screen, SecondaryButton, TextField } from '@/components';
import { useAuth } from '@/lib/auth';
import { useForm } from '@/lib/useForm';

const MIN_PASSWORD = 12;

/** Opened from the reset mail: https://kosten.codekorb.de/reset-password?token=…&email=… */
export default function ResetPasswordScreen() {
  const { token, email } = useLocalSearchParams<{ token?: string; email?: string }>();
  const { resetPassword } = useAuth();
  const form = useForm({ password: '', password_confirmation: '' });

  const { password, password_confirmation } = form.values;
  const tooShort = password.length > 0 && password.length < MIN_PASSWORD;
  const mismatch = password_confirmation.length > 0 && password !== password_confirmation;
  const canSubmit = password.length >= MIN_PASSWORD && password === password_confirmation;

  const onSubmit = () =>
    form.submit(async (v) => {
      await resetPassword({ ...v, token: token ?? '', email: email ?? '' });
      router.replace({ pathname: '/login', params: { reset: '1' } });
    });

  if (!token || !email) {
    return (
      <Screen narrow>
        <AuthHeader title="Neues Passwort" back />
        <View style={styles.form}>
          <Notice tone="danger" title="Link unvollständig">
            Öffne den Link direkt aus der Mail oder fordere einen neuen an.
          </Notice>
          <SecondaryButton label="Neuen Link anfordern" onPress={() => router.replace('/forgot-password')} />
        </View>
      </Screen>
    );
  }

  return (
    <Screen narrow>
      <AuthHeader title="Neues Passwort" subtitle={`Für ${email}`} back />

      <View style={styles.form}>
        {form.formError && (
          <Notice tone="danger" title="Das hat nicht geklappt">
            {form.formError}
          </Notice>
        )}
        <TextField
          label="Neues Passwort"
          value={password}
          onChangeText={form.set('password')}
          error={form.errors.password ?? (tooShort ? `Mindestens ${MIN_PASSWORD} Zeichen.` : undefined)}
          hint={`Mindestens ${MIN_PASSWORD} Zeichen.`}
          secure
          autoComplete="new-password"
          textContentType="newPassword"
        />
        <TextField
          label="Passwort wiederholen"
          value={password_confirmation}
          onChangeText={form.set('password_confirmation')}
          error={mismatch ? 'Die Passwörter stimmen nicht überein.' : undefined}
          secure
          autoComplete="new-password"
          textContentType="newPassword"
          returnKeyType="go"
          onSubmitEditing={canSubmit ? onSubmit : undefined}
        />
        <PrimaryButton label="Passwort speichern" onPress={onSubmit} disabled={!canSubmit} loading={form.submitting} />
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
