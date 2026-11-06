import type { ConfigContext, ExpoConfig } from 'expo/config';

// Production API. Override locally with EXPO_PUBLIC_API_URL (see .env.example).
const DEFAULT_API_URL = 'https://abo.codekorb.de/api/v1';
const APP_HOST = 'abo.codekorb.de';

export default ({ config }: ConfigContext): ExpoConfig => ({
  ...config,
  name: 'Abo-Tracker',
  slug: 'abo-tracker',
  version: '1.0.0',
  platforms: ['android', 'web'],
  orientation: 'portrait',
  icon: './assets/images/icon.png',
  scheme: 'abotracker',
  userInterfaceStyle: 'dark',
  backgroundColor: '#0E1014',
  android: {
    package: 'de.codekorb.abo',
    adaptiveIcon: {
      backgroundColor: '#0E1014',
      foregroundImage: './assets/images/android-icon-foreground.png',
      backgroundImage: './assets/images/android-icon-background.png',
      monochromeImage: './assets/images/android-icon-monochrome.png',
    },
    predictiveBackGestureEnabled: false,
    // App Links: invitation and password-reset mails open directly in the app.
    // Requires /.well-known/assetlinks.json on the server (ANDROID_SHA256_CERT_FINGERPRINTS).
    intentFilters: [
      {
        action: 'VIEW',
        autoVerify: true,
        data: [
          { scheme: 'https', host: APP_HOST, pathPrefix: '/register' },
          { scheme: 'https', host: APP_HOST, pathPrefix: '/reset-password' },
          { scheme: 'https', host: APP_HOST, pathPrefix: '/login' },
        ],
        category: ['BROWSABLE', 'DEFAULT'],
      },
    ],
  },
  web: {
    // Single-page export, served by Laravel with a fallback to index.html
    output: 'single',
    favicon: './assets/images/favicon.png',
    bundler: 'metro',
  },
  plugins: [
    'expo-router',
    'expo-secure-store',
    'expo-font',
    [
      'expo-notifications',
      {
        color: '#8EA8FF',
        defaultChannel: 'reminders',
      },
    ],
    [
      'expo-splash-screen',
      {
        backgroundColor: '#0E1014',
        image: './assets/images/splash-icon.png',
        imageWidth: 76,
      },
    ],
  ],
  experiments: {
    typedRoutes: true,
    reactCompiler: true,
  },
  extra: {
    apiUrl: process.env.EXPO_PUBLIC_API_URL ?? DEFAULT_API_URL,
    // Set by `eas init`; needed for Expo push tokens.
    eas: process.env.EAS_PROJECT_ID ? { projectId: process.env.EAS_PROJECT_ID } : undefined,
  },
});
