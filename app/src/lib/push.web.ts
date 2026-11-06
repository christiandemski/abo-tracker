// Web: no push notifications – reminders arrive by mail.
import type { ApiClient } from './api';

export const CHANNEL_ID = 'reminders';
export const pushSupported = false;

export type PushStatus = 'unsupported' | 'denied' | 'unconfigured' | 'registered' | 'error';

export async function registerForPush(_api: ApiClient, _options: { ask?: boolean } = {}): Promise<PushStatus> {
  return 'unsupported';
}

export async function unregisterPush(_api: ApiClient): Promise<void> {}

export function urlFromNotification(_response: unknown): string | null {
  return null;
}
