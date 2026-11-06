// Android: write to the cache and open the share sheet (print, save, mail as attachment …).
import { File, Paths } from 'expo-file-system';
import * as Sharing from 'expo-sharing';

export async function saveFile(data: ArrayBuffer, fileName: string, mimeType: string): Promise<void> {
  const file = new File(Paths.cache, fileName);
  if (file.exists) file.delete();
  file.create();
  file.write(new Uint8Array(data));
  await Sharing.shareAsync(file.uri, { mimeType, dialogTitle: fileName, UTI: 'com.adobe.pdf' });
}
