import { router, useLocalSearchParams } from 'expo-router';
import { StyleSheet, View } from 'react-native';

import { AuthHeader, Notice, PrimaryButton, Screen, TextField, TextLink } from '@/components';
import { useAuth } from '@/lib/auth';
import { useForm } from '@/lib/useForm';

export default function LoginScreen() {
  const { signIn } = useAuth();
  const { verified, reset } = useLocalSearchParams<{ verified?: string; reset?: string }>();
  const form = useForm({ email: '', password: '' });

  const canSubmit = form.values.email.trim() !== '' && form.values.password !== '';
  const onSubmit = () => form.submit(({ email, password }) => signIn(email.trim(), password));

  return (
    <Screen narrow>
      <AuthHeader title="Abo-Tracker" subtitle="Melde dich an, um deine Abos und Fristen zu sehen." />

      <View style={styles.form}>
        {verified === '1' && (
          <Notice tone="success" title="Adresse bestätigt">
            Du kannst dich jetzt anmelden.
          </Notice>
        )}
        {reset === '1' && (
          <Notice tone="success" title="Passwort geändert">
            Melde dich mit deinem neuen Passwort an.
          </Notice>
        )}
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
          returnKeyType="next"
        />
        <TextField
          label="Passwort"
          value={form.values.password}
          onChangeText={form.set('password')}
          error={form.errors.password}
          secure
          autoComplete="current-password"
          textContentType="password"
          returnKeyType="go"
          onSubmitEditing={canSubmit ? onSubmit : undefined}
        />

        <PrimaryButton label="Anmelden" onPress={onSubmit} disabled={!canSubmit} loading={form.submitting} />
        <TextLink label="Passwort vergessen?" onPress={() => router.push('/forgot-password')} />
      </View>

      <View style={styles.footer}>
        <Notice tone="info">
          Ein Konto bekommst du nur per Einladung. Frag die Person, die den Abo-Tracker betreibt.
        </Notice>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  form: {
    marginTop: 16,
    gap: 14,
  },
  footer: {
    marginTop: 'auto',
    paddingTop: 24,
  },
});
