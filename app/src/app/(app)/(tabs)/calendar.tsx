import { Calendar } from 'lucide-react-native';

import { Screen } from '@/components';
import { ComingSoon } from '@/components/ComingSoon';
import { ScreenHeader } from '@/components/ScreenHeader';

export default function CalendarScreen() {
  return (
    <Screen>
      <ScreenHeader title="Kalender" />
      <ComingSoon
        icon={Calendar}
        title="Monatsansicht"
        text="Hier siehst du bald, an welchen Tagen im Monat welche Abbuchungen anstehen."
      />
    </Screen>
  );
}
