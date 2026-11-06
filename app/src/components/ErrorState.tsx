import { RotateCw } from 'lucide-react-native';
import { StyleSheet, View } from 'react-native';

import { Notice } from './Notice';
import { SecondaryButton } from './Button';

type Props = {
  message: string;
  onRetry: () => void;
  retrying?: boolean;
};

/** Error as a card with "Wiederholen" (DESIGN.md: error states). */
export function ErrorState({ message, onRetry, retrying }: Props) {
  return (
    <View style={styles.wrapper}>
      <Notice tone="danger" title="Das hat nicht geklappt">
        {message}
      </Notice>
      <SecondaryButton label="Wiederholen" icon={RotateCw} onPress={onRetry} loading={retrying} />
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    gap: 10,
  },
});
