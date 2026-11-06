import { FileUp } from 'lucide-react-native';

import { Screen } from '@/components';
import { ComingSoon } from '@/components/ComingSoon';
import { ScreenHeader } from '@/components/ScreenHeader';

export default function ImportScreen() {
  return (
    <Screen>
      <ScreenHeader title="CSV importieren" nav="close" />
      <ComingSoon
        icon={FileUp}
        title="Kontoauszug einlesen"
        text="Bald kannst du hier einen CSV-Export deiner Sparkasse hochladen. Wiederkehrende Zahlungen werden erkannt, die Datei wird danach sofort gelöscht."
      />
    </Screen>
  );
}
