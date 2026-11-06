import { List } from 'lucide-react-native';

import { Screen } from '@/components';
import { ComingSoon } from '@/components/ComingSoon';
import { ScreenHeader } from '@/components/ScreenHeader';

export default function SubscriptionsScreen() {
  return (
    <Screen>
      <ScreenHeader title="Abos" />
      <ComingSoon
        icon={List}
        title="Deine Abos im Überblick"
        text="Hier findest du bald alle Abos mit Suche, Filter nach Kategorie und Sortierung nach Preis oder nächster Abbuchung."
      />
    </Screen>
  );
}
