import { Bell, CalendarClock, Mail } from 'lucide-react-native';
import { useState, type ReactNode } from 'react';
import { StyleSheet, View, type ViewStyle } from 'react-native';

import { AppText, ErrorState, Notice, Screen, ScreenHeader, SecondaryButton, Skeleton, Switch } from '@/components';
import { Chip } from '@/components/Chip';
import { useAuth } from '@/lib/auth';
import { pushSupported, registerForPush, type PushStatus } from '@/lib/push';
import type { ReminderSettings, Resource } from '@/lib/types';
import { useQuery } from '@/lib/useQuery';
import { colors, fonts, radius } from '@/theme';

function offsetLabel(days: number): string {
  return days === 1 ? '1 Tag vorher' : `${days} Tage vorher`;
}

export default function RemindersScreen() {
  const { api } = useAuth();
  const query = useQuery('settings:reminders', async () => (await api.get<Resource<ReminderSettings>>('settings/reminders')).data);

  return (
    <Screen>
      <ScreenHeader title="Erinnerungen" nav="back" />
      {query.data ? (
        <RemindersForm initial={query.data} />
      ) : query.error ? (
        <View style={styles.section}>
          <ErrorState message={query.error.message} onRetry={query.refetch} retrying={query.refreshing} />
        </View>
      ) : (
        <View style={styles.section}>
          <Skeleton height={220} />
        </View>
      )}
    </Screen>
  );
}

function RemindersForm({ initial }: { initial: ReminderSettings }) {
  const { api, user } = useAuth();
  const [settings, setSettings] = useState(initial);
  const [error, setError] = useState<string | null>(null);
  const [pushStatus, setPushStatus] = useState<PushStatus | null>(null);
  const [registering, setRegistering] = useState(false);

  async function save(next: ReminderSettings) {
    const previous = settings;
    setSettings(next);
    setError(null);
    try {
      const { data } = await api.put<Resource<ReminderSettings>>('settings/reminders', {
        offsets_days: next.offsets_days,
        push: next.push,
        email: next.email,
        charge_reminders: next.charge_reminders,
      });
      setSettings(data);
    } catch (e) {
      setSettings(previous);
      setError(e instanceof Error ? e.message : 'Das hat nicht geklappt.');
    }
  }

  function toggleOffset(days: number) {
    const on = settings.offsets_days.includes(days);
    if (on && settings.offsets_days.length === 1) {
      setError('Mindestens ein Zeitpunkt muss gewählt sein.');
      return;
    }
    const offsets = on ? settings.offsets_days.filter((d) => d !== days) : [...settings.offsets_days, days];
    void save({ ...settings, offsets_days: offsets.sort((a, b) => b - a) });
  }

  async function retryPush() {
    setRegistering(true);
    const status = await registerForPush(api);
    setPushStatus(status);
    setRegistering(false);
    if (status === 'registered') {
      const { data } = await api.get<Resource<ReminderSettings>>('settings/reminders');
      setSettings(data);
    }
  }

  const pushMeta = !pushSupported
    ? 'Im Browser gibt es keine Push-Nachrichten. Nutze dafür die Android-App.'
    : settings.push_devices > 0
      ? settings.push_devices === 1
        ? 'Auf 1 Gerät aktiv'
        : `Auf ${settings.push_devices} Geräten aktiv`
      : 'Auf diesem Gerät noch nicht eingerichtet';

  return (
    <View>
      {error && (
        <View style={styles.section}>
          <Notice tone="danger">{error}</Notice>
        </View>
      )}

      <AppText variant="h2" accessibilityRole="header" style={styles.heading}>
        Wie?
      </AppText>
      <View style={styles.card}>
        <Row icon={<Bell size={20} strokeWidth={1.8} color={colors.textMuted} />} label="Push-Nachricht" meta={pushMeta} divider>
          <Switch
            value={settings.push && pushSupported}
            disabled={!pushSupported}
            accessibilityLabel="Push-Nachrichten"
            onValueChange={(push) => void save({ ...settings, push })}
          />
        </Row>
        <Row icon={<Mail size={20} strokeWidth={1.8} color={colors.textMuted} />} label="E-Mail" meta={user?.email ?? ''}>
          <Switch value={settings.email} accessibilityLabel="E-Mail-Erinnerungen" onValueChange={(email) => void save({ ...settings, email })} />
        </Row>
      </View>

      {pushSupported && settings.push && settings.push_devices === 0 && (
        <View style={styles.section}>
          <Notice
            tone="info"
            title={pushStatus === 'denied' ? 'Benachrichtigungen sind aus' : 'Push einrichten'}
            action={<SecondaryButton label="Jetzt einrichten" loading={registering} onPress={retryPush} />}
          >
            {pushStatus === 'denied'
              ? 'Erlaube Benachrichtigungen für den Cost-Tracker in den Android-Einstellungen und tipp dann noch mal hier.'
              : pushStatus === 'unconfigured'
                ? 'Push ist für diese App-Version noch nicht eingerichtet. Mails kommen trotzdem an.'
                : 'Damit Erinnerungen auf diesem Gerät ankommen, braucht die App deine Erlaubnis.'}
          </Notice>
        </View>
      )}

      {!settings.push && !settings.email && (
        <View style={styles.section}>
          <Notice tone="info">Push und E-Mail sind aus. Du bekommst gerade keine Erinnerungen.</Notice>
        </View>
      )}

      <AppText variant="h2" accessibilityRole="header" style={styles.heading}>
        Wann?
      </AppText>
      <AppText variant="small" color="textMuted" style={styles.sub}>
        Vor dem Ende der Kündigungsfrist. Gilt für alle Abos mit Laufzeit, bei denen die Erinnerung an ist.
      </AppText>
      <View style={styles.chips}>
        {settings.available_offsets.map((days) => (
          <Chip
            key={days}
            role="button"
            label={offsetLabel(days)}
            selected={settings.offsets_days.includes(days)}
            onPress={() => toggleOffset(days)}
          />
        ))}
      </View>

      <View style={[styles.card, styles.section]}>
        <Row
          icon={<CalendarClock size={20} strokeWidth={1.8} color={colors.textMuted} />}
          label="Abbuchung morgen"
          meta="Für vierteljährliche und jährliche Abos, damit dich die Abbuchung nicht überrascht."
        >
          <Switch
            value={settings.charge_reminders}
            accessibilityLabel="An Abbuchung am nächsten Tag erinnern"
            onValueChange={(charge_reminders) => void save({ ...settings, charge_reminders })}
          />
        </Row>
      </View>

      <AppText variant="caption" color="textMuted" style={styles.footnote}>
        Erinnerungen kommen morgens gegen 7 Uhr. Push-Nachrichten zeigen keine Beträge.
      </AppText>
    </View>
  );
}

function Row({
  icon,
  label,
  meta,
  children,
  divider,
}: {
  icon: ReactNode;
  label: string;
  meta?: string;
  children: ReactNode;
  divider?: boolean;
}) {
  const style: ViewStyle[] = [styles.row];
  if (divider) style.push(styles.divider);
  return (
    <View style={style}>
      {icon}
      <View style={styles.rowText}>
        <AppText variant="body" style={styles.medium}>
          {label}
        </AppText>
        {meta ? (
          <AppText variant="caption" color="textMuted">
            {meta}
          </AppText>
        ) : null}
      </View>
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  section: {
    marginTop: 16,
  },
  heading: {
    marginTop: 22,
    marginBottom: 10,
  },
  sub: {
    marginTop: -4,
    marginBottom: 10,
  },
  card: {
    borderRadius: 20,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    overflow: 'hidden',
  },
  row: {
    minHeight: 64,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  divider: {
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  rowText: {
    flex: 1,
    gap: 2,
  },
  medium: {
    fontFamily: fonts.bodyMedium,
  },
  chips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  footnote: {
    marginTop: 16,
    borderRadius: radius.input,
  },
});
