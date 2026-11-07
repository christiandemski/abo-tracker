import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { AuthHeader, Notice, PrimaryButton, Screen, SecondaryButton, TextField } from '@/components';
import { ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { useForm } from '@/lib/useForm';
import { colors } from '@/theme';

type InvitationState =
  | { kind: 'checking' }
  | { kind: 'valid'; email: string }
  | { kind: 'invalid'; message: string };

const MIN_PASSWORD = 12;

/** Opened from the invitation mail: https://kosten.codekorb.de/register?token=… */
export default function RegisterScreen() {
  const { token } = useLocalSearchParams<{ token?: string }>();
  const { checkInvitation, register } = useAuth();
  const [invitation, setInvitation] = useState<InvitationState>(
    token ? { kind: 'checking' } : { kind: 'invalid', message: 'Der Link ist unvollständig. Öffne ihn direkt aus der Einladungsmail.' },
  );
  const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });
  const setEmail = form.set('email');

  useEffect(() => {
    if (!token) return;
    let cancelled = false;
    checkInvitation(token)
      .then(({ email }) => {
        if (cancelled) return;
        setInvitation({ kind: 'valid', email });
        setEmail(email);
      })
      .catch((error: unknown) => {
        if (cancelled) return;
        const message =
          error instanceof ApiError && error.status === 404
            ? 'Die Einladung ist ungültig oder abgelaufen. Lass dir bitte einen neuen Link schicken.'
            : error instanceof Error
              ? error.message
              : 'Die Einladung konnte nicht geprüft werden.';
        setInvitation({ kind: 'invalid', message });
      });
    return () => {
      cancelled = true;
    };
    // setEmail is recreated every render; only the token matters here.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token, checkInvitation]);

  const { name, email, password, password_confirmation } = form.values;
  const tooShort = password.length > 0 && password.length < MIN_PASSWORD;
  const mismatch = password_confirmation.length > 0 && password !== password_confirmation;
  const canSubmit =
    name.trim() !== '' && email.trim() !== '' && password.length >= MIN_PASSWORD && password === password_confirmation;

  const onSubmit = () =>
    form.submit((v) => register({ ...v, name: v.name.trim(), email: v.email.trim(), token: token ?? '' }));

  return (
    <Screen narrow>
      <AuthHeader title="Konto anlegen" subtitle="Schön, dass du dabei bist." back />

      {invitation.kind === 'checking' && (
        <View style={styles.center}>
          <ActivityIndicator color={colors.accent} />
        </View>
      )}

      {invitation.kind === 'invalid' && (
        <View style={styles.form}>
          <Notice tone="danger" title="Einladung nicht gültig">
            {invitation.message}
          </Notice>
          <SecondaryButton label="Zur Anmeldung" onPress={() => router.replace('/login')} />
        </View>
      )}

      {invitation.kind === 'valid' && (
        <View style={styles.form}>
          {form.formError && <Notice tone="danger">{form.formError}</Notice>}

          <TextField
            label="Name"
            value={name}
            onChangeText={form.set('name')}
            error={form.errors.name}
            placeholder="Wie sollen wir dich nennen?"
            autoComplete="name"
            textContentType="name"
          />
          <TextField
            label="E-Mail"
            value={email}
            onChangeText={setEmail}
            error={form.errors.email}
            hint={
              email.trim().toLowerCase() !== invitation.email
                ? 'Andere Adresse als in der Einladung: Du bekommst eine Mail zum Bestätigen.'
                : undefined
            }
            autoCapitalize="none"
            autoComplete="email"
            keyboardType="email-address"
            textContentType="emailAddress"
          />
          <TextField
            label="Passwort"
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
            error={form.errors.password_confirmation ?? (mismatch ? 'Die Passwörter stimmen nicht überein.' : undefined)}
            secure
            autoComplete="new-password"
            textContentType="newPassword"
            returnKeyType="go"
            onSubmitEditing={canSubmit ? onSubmit : undefined}
          />

          <PrimaryButton label="Konto anlegen" onPress={onSubmit} disabled={!canSubmit} loading={form.submitting} />
        </View>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  form: {
    marginTop: 16,
    gap: 14,
  },
  center: {
    paddingVertical: 48,
    alignItems: 'center',
  },
});
