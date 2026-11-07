// Abo-Tracker – Design-Tokens (Dark Mode)
// Vorlage für app/src/theme.ts. Werte aus docs/DESIGN.md bzw. dem Canvas „Abo-Tracker Design“.

export const colors = {
  bg: '#0E1014',
  surface: '#171A21',
  surfaceBar: '#12151B',
  surfaceSelected: '#171D2E',
  border: '#22262F',
  borderStrong: '#2A2F3A',
  borderSelected: '#3A4878',
  divider: '#1E222A',

  text: '#F2F3F5',
  textMuted: '#A0A7B4',

  accent: '#8EA8FF',
  accentHover: '#B4C5FF',
  onAccent: '#0E1014',
  accentSurface: '#1A2033',
  accentBorder: '#34406A',
  accentText: '#B4C5FF',
  accentProgress: '#4A5A8F',

  // Nur für Handlungsbedarf: Fristen, Preiserhöhungen, „Rhythmus prüfen“
  warn: '#FFB25C',
  onWarn: '#1A1208',
  warnSurface: '#2A2013',
  warnBorder: '#6B4A1F',
  warnText: '#D9CFBF',

  success: '#5EE6B0',
  successSurface: '#15302A',
  successBorder: '#2B5A4D',

  danger: '#FF8A9A',
  dangerSurface: '#2E161B',
  dangerBorder: '#6B2F3A',

  switchOff: '#3A404D',
  checkboxOff: '#4A5161',
} as const;

// Kategoriefarben – Schlüssel entsprechen den Standardkategorien (Seeder)
export const categoryColors = {
  telecom: '#8EA8FF', // Telefon & Internet
  streaming: '#B5E36B', // Streaming
  sport: '#4FD1C5', // Sport
  software: '#C9A7FF', // Software & Cloud
  insurance: '#F48FB1', // Versicherung
  mobility: '#6B7385', // Mobilität
  loans: '#7DD3FC', // Kredite & Raten
  savings: '#86EFAC', // Sparen & Vorsorge
  other: '#9AA3B5', // Sonstiges
} as const;

export const fonts = {
  display: 'SpaceGrotesk_600SemiBold',
  displayBold: 'SpaceGrotesk_700Bold',
  body: 'Inter_400Regular',
  bodyMedium: 'Inter_500Medium',
  bodySemi: 'Inter_600SemiBold',
  bodyBold: 'Inter_700Bold',
} as const;

export const type = {
  display: { fontFamily: fonts.display, fontSize: 46, letterSpacing: -1.4, lineHeight: 48 },
  displayM: { fontFamily: fonts.display, fontSize: 34, letterSpacing: -0.7 },
  h1: { fontFamily: fonts.display, fontSize: 28, letterSpacing: -0.56 },
  h1Sub: { fontFamily: fonts.display, fontSize: 22, letterSpacing: -0.44 },
  h2: { fontFamily: fonts.bodySemi, fontSize: 16 },
  rowTitle: { fontFamily: fonts.bodySemi, fontSize: 15 },
  body: { fontFamily: fonts.body, fontSize: 14, lineHeight: 20 },
  small: { fontFamily: fonts.body, fontSize: 13 },
  caption: { fontFamily: fonts.bodyMedium, fontSize: 12 },
  tab: { fontFamily: fonts.bodyMedium, fontSize: 11 },
} as const;

// Für alle Beträge: fontVariant: ['tabular-nums']
export const amount = { fontVariant: ['tabular-nums'] as const };

export const space = { xs: 4, s: 8, m: 12, l: 16, xl: 20, section: 22, xxl: 24 } as const;
export const screenPadding = 20;

export const radius = { segment: 9, input: 12, iconButton: 14, card: 18, hero: 24, pill: 99 } as const;

export const size = {
  touch: 44,
  buttonPrimary: 52,
  buttonSecondary: 44,
  tabBar: 84,
  fab: 52,
  tileList: 42,
  tileDetail: 56,
  tileHero: 72,
} as const;

export const theme = { colors, categoryColors, fonts, type, amount, space, screenPadding, radius, size };
export default theme;
