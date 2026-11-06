import { ChartLine } from 'lucide-react-native';

import { Screen } from '@/components';
import { ComingSoon } from '@/components/ComingSoon';
import { ScreenHeader } from '@/components/ScreenHeader';

export default function StatsScreen() {
  return (
    <Screen>
      <ScreenHeader title="Statistik" />
      <ComingSoon
        icon={ChartLine}
        title="Rücklage und Verlauf"
        text="Hier erfährst du bald, wie viel du monatlich für Jahresabos zurücklegen solltest und wie sich deine Fixkosten entwickeln."
      />
    </Screen>
  );
}
