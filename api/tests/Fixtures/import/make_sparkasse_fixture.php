<?php

// Generates a synthetic Sparkasse CSV-CAMT V2 export (Windows-1252, ";" separated, quoted).
// All names, IBANs and references are fake.
$out = $argv[1];
$h = ['Auftragskonto', 'Buchungstag', 'Valutadatum', 'Buchungstext', 'Verwendungszweck', 'Glaeubiger ID', 'Mandatsreferenz', 'Kundenreferenz (End-to-End)', 'Sammlerreferenz', 'Lastschrift Ursprungsbetrag', 'Auslagenersatz Ruecklastschrift', 'Beguenstigter/Zahlungspflichtiger', 'Kontonummer/IBAN', 'BIC (SWIFT-Code)', 'Betrag', 'Waehrung', 'Info'];
$rows = [];
$acc = 'DE00999999990000000000';
$add = function ($date, $text, $purpose, $cid, $mref, $payee, $iban, $amount) use (&$rows, $acc) {
    $d = date('d.m.y', strtotime($date));
    $rows[] = [$acc, $d, $d, $text, $purpose, $cid, $mref, 'NOTPROVIDED', '', '', '', $payee, $iban, 'TESTDEFFXXX', number_format($amount, 2, ',', '.'), 'EUR', 'Umsatz gebucht'];
};
for ($m = 1; $m <= 10; $m++) {
    $mm = sprintf('%02d', $m);
    $add("2026-$mm-15", 'FOLGELASTSCHRIFT', "Spotify P0$m 2026 Premium", 'SE12ZZZ00000000001', 'SPOT-4711', 'Spotify AB', 'SE0000000000000000000001', -10.99);
    $add("2026-$mm-03", 'FOLGELASTSCHRIFT', "10471283$m PP.4711.PP . Netflix International B.V., Ihr Einkauf bei Netflix International B.V.", 'LU96ZZZ0000000000000000058', 'PAYPAL-MANDAT-1', 'PayPal (Europe) S.a r.l. et Cie, S.C.A.', 'LU00000000000000000001', -13.99);
    $add("2026-$mm-08", 'KARTENZAHLUNG', "2026-$mm-08T10:00 Debitk.1 VISA Debit", '', '', 'DISNEY PLUS', '', -9.99);
    $add("2026-$mm-01", 'FOLGELASTSCHRIFT', "Mitgliedsbeitrag $mm/2026 Vertrag 998877", 'DE98ZZZ09999999999', 'FITX-123456', 'FitX Deutschland GmbH', 'DE00000000000000000002', $m >= 9 ? -32.90 : -29.90);
    // groceries: weekly, very different amounts → must not be detected
    foreach ([2, 9, 16, 23] as $i => $day) {
        $add(sprintf('2026-%s-%02d', $mm, $day), 'KARTENZAHLUNG', "REWE SAGT DANKE $m$i", '', '', 'REWE Markt GmbH', '', -(17.35 + (($m * 7 + $i * 13) % 60)));
    }
    $add("2026-$mm-28", 'GUTSCHRIFT', "Lohn/Gehalt $mm/2026", '', '', 'Arbeitgeber GmbH', 'DE00000000000000000003', 2500.00);
}
foreach (['2026-01-15', '2026-04-15', '2026-07-15', '2026-10-15'] as $d) {
    $add($d, 'FOLGELASTSCHRIFT', 'Rundfunkbeitrag Beitragsnummer 123456789', 'DE48ZZZ00000000015', 'RBN-123456789', 'Rundfunk ARD, ZDF, Deutschlandradio Beitragsservice Köln', 'DE00000000000000000004', -55.08);
}
$add('2026-02-01', 'ERSTLASTSCHRIFT', 'Hausratversicherung Vers.-Nr. 77-123456 Jahresbeitrag für 2026', 'DE11ZZZ00000000077', 'HR-77123456', 'Allianz Versicherungs-AG', 'DE00000000000000000005', -89.40);
// PayPal one-off purchases (same PayPal mandate!) → must not be detected
$add('2026-03-12', 'FOLGELASTSCHRIFT', '1047999 PP.4711.PP . Zalando SE, Ihr Einkauf bei Zalando SE', 'LU96ZZZ0000000000000000058', 'PAYPAL-MANDAT-1', 'PayPal (Europe) S.a r.l. et Cie, S.C.A.', 'LU00000000000000000001', -59.95);
$add('2026-07-30', 'FOLGELASTSCHRIFT', '1048111 PP.4711.PP . Zalando SE, Ihr Einkauf bei Zalando SE', 'LU96ZZZ0000000000000000058', 'PAYPAL-MANDAT-1', 'PayPal (Europe) S.a r.l. et Cie, S.C.A.', 'LU00000000000000000001', -129.90);
$add('2026-05-20', 'BARGELDAUSZAHLUNG', 'GA 1234 Sparkasse', '', '', 'Sparkasse Musterstadt', '', -100.00);
usort($rows, fn ($a, $b) => strtotime(DateTime::createFromFormat('d.m.y', $b[1])->format('Y-m-d')) <=> strtotime(DateTime::createFromFormat('d.m.y', $a[1])->format('Y-m-d')));
$fh = fopen('php://memory', 'w+');
fputcsv($fh, $h, ';', '"', '');
foreach ($rows as $r) {
    fputcsv($fh, $r, ';', '"', '');
}
rewind($fh);
$csv = stream_get_contents($fh);
// Sparkasse quotes every field
$csv = preg_replace_callback('/^.*$/m', fn ($m) => $m[0] === '' ? '' : implode(';', array_map(fn ($v) => '"'.str_replace('"', '""', $v).'"', str_getcsv($m[0], ';', '"', ''))), $csv);
file_put_contents($out, mb_convert_encoding(str_replace("\n", "\r\n", $csv), 'Windows-1252', 'UTF-8'));
echo count($rows)." rows\n";
