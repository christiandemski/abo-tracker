import { Platform, useWindowDimensions } from 'react-native';

/** Web from ~1024 px: sidebar instead of the tab bar, desktop overview. */
export const DESKTOP_MIN_WIDTH = 1024;

export function useIsDesktop(): boolean {
  const { width } = useWindowDimensions();
  return Platform.OS === 'web' && width >= DESKTOP_MIN_WIDTH;
}
