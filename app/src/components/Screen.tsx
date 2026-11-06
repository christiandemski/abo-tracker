import type { ReactNode } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, View, type ViewStyle } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { colors, screenPadding } from '@/theme';

type Props = {
  children: ReactNode;
  /** Narrow centered column for auth forms on wide (desktop) screens */
  narrow?: boolean;
  scroll?: boolean;
  /** No padding and no scroll container: the screen lays out edge-to-edge itself */
  bare?: boolean;
  contentStyle?: ViewStyle;
};

export function Screen({ children, narrow, scroll = true, bare, contentStyle }: Props) {
  const content = (
    <View style={[styles.content, narrow && styles.narrow, contentStyle]}>{children}</View>
  );

  return (
    <SafeAreaView style={styles.root} edges={['top', 'left', 'right']}>
      <KeyboardAvoidingView style={styles.root} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        {bare ? (
          children
        ) : scroll ? (
          <ScrollView
            style={styles.root}
            contentContainerStyle={styles.scrollContent}
            keyboardShouldPersistTaps="handled"
          >
            {content}
          </ScrollView>
        ) : (
          content
        )}
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: colors.bg,
  },
  scrollContent: {
    flexGrow: 1,
  },
  content: {
    flexGrow: 1,
    width: '100%',
    paddingHorizontal: screenPadding,
    paddingBottom: 24,
  },
  narrow: {
    maxWidth: 420,
    alignSelf: 'center',
  },
});
