import { router, useLocalSearchParams } from 'expo-router';
import { StyleSheet, View } from 'react-native';

import { ErrorState, Screen, ScreenHeader, Skeleton } from '@/components';
import { formFromSubscription } from '@/features/subscriptionForm/model';
import { SubscriptionForm } from '@/features/subscriptionForm/SubscriptionForm';
import { useAuth } from '@/lib/auth';
import { useSubscription } from '@/lib/queries';
import type { Resource, Subscription } from '@/lib/types';
import { invalidate } from '@/lib/useQuery';
import { screenPadding } from '@/theme';

export default function EditSubscriptionScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { api } = useAuth();
  const query = useSubscription(id);

  return (
    <Screen bare>
      <View style={styles.header}>
        <ScreenHeader title="Abo bearbeiten" nav="close" />
      </View>
      {query.data ? (
        <SubscriptionForm
          key={query.data.id}
          initial={formFromSubscription(query.data)}
          submitLabel="Änderungen speichern"
          onSubmit={async (payload) => {
            await api.patch<Resource<Subscription>>(`subscriptions/${id}`, payload);
            invalidate('subscriptions', 'dashboard', 'calendar', 'stats');
            if (router.canGoBack()) router.back();
            else router.replace(`/subscription/${id}`);
          }}
        />
      ) : query.error ? (
        <View style={styles.padded}>
          <ErrorState message={query.error.message} onRetry={query.refetch} retrying={query.refreshing} />
        </View>
      ) : (
        <View style={styles.padded}>
          <Skeleton height={300} />
        </View>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  header: {
    paddingHorizontal: screenPadding - 4,
  },
  padded: {
    paddingHorizontal: screenPadding,
    marginTop: 16,
  },
});
