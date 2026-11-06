import { CirclePlus } from 'lucide-react-native';

import { Screen } from '@/components';
import { ComingSoon } from '@/components/ComingSoon';
import { ScreenHeader } from '@/components/ScreenHeader';

export default function AddSubscriptionScreen() {
  return (
    <Screen>
      <ScreenHeader title="Abo hinzufügen" nav="close" />
      <ComingSoon
        icon={CirclePlus}
        title="Kommt als Nächstes"
        text="Hier legst du bald Abos an: mit Vorlagen für bekannte Dienste, Rhythmus, Kategorie und Kündigungsfrist."
      />
    </Screen>
  );
}
