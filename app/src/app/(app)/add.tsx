import { router } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

import { Screen, ScreenHeader } from '@/components';
import { emptyForm } from '@/features/subscriptionForm/model';
import { SubscriptionForm } from '@/features/subscriptionForm/SubscriptionForm';
import { useAuth } from '@/lib/auth';
import type { Resource, Subscription } from '@/lib/types';
import { invalidate } from '@/lib/useQuery';
import { screenPadding } from '@/theme';

export default function AddSubscriptionScreen() {
  const { api } = useAuth();
  const [initial] = useState(() => emptyForm());

  return (
    <Screen bare>
      <View style={styles.header}>
        <ScreenHeader title="Abo hinzufügen" nav="close" />
      </View>
      <SubscriptionForm
        initial={initial}
        withTemplates
        submitLabel="Abo speichern"
        onSubmit={async (payload) => {
          const { data } = await api.post<Resource<Subscription>>('subscriptions', payload);
          invalidate('subscriptions', 'dashboard');
          router.replace(`/subscription/${data.id}`);
        }}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  header: {
    paddingHorizontal: screenPadding - 4,
  },
});
