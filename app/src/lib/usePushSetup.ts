import { router } from 'expo-router';
import * as Notifications from 'expo-notifications';
import { useEffect } from 'react';

import { useAuth } from './auth';
import { pushSupported, registerForPush, urlFromNotification } from './push';

/**
 * Inside the signed-in area: register the device for push and open the
 * subscription when a reminder is tapped (also when the app was closed).
 */
export function usePushSetup() {
  const { api, user } = useAuth();
  const verified = !!user?.email_verified;

  useEffect(() => {
    if (!pushSupported || !verified) return;
    void registerForPush(api);
  }, [api, verified]);

  useEffect(() => {
    if (!pushSupported) return;

    let handled = false;
    Notifications.getLastNotificationResponseAsync()
      .then((response) => {
        const url = urlFromNotification(response);
        if (url && !handled) {
          handled = true;
          router.push(url as never);
        }
      })
      .catch(() => {});

    const subscription = Notifications.addNotificationResponseReceivedListener((response) => {
      const url = urlFromNotification(response);
      if (url) router.push(url as never);
    });
    return () => subscription.remove();
  }, []);
}
