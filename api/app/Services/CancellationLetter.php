<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Cancellation letter with placeholders for missing data ("[Kundennummer]").
 * The letter to the provider is formal (Sie); the app itself speaks informally.
 */
class CancellationLetter
{
    public function __construct(private readonly BillingCalculator $billing) {}

    /**
     * Expected end of the contract if cancelled now: day before the next renewal
     * (with minimum term) or day before the next charge.
     */
    public function expectedEnd(Subscription $s): ?CarbonImmutable
    {
        if ($s->hasTerm() && $s->term_end_date) {
            return $s->term_end_date->subDay();
        }

        return $s->next_charge_date?->subDay();
    }

    /**
     * @return array{sender: string, subject: string, body: string, text: string, expected_end: ?string, recipient_email: ?string, missing: list<string>}
     */
    public function build(Subscription $s, User $user): array
    {
        $missing = [];
        $placeholder = function (?string $value, string $label, string $key) use (&$missing): string {
            if ($value === null || trim($value) === '') {
                $missing[] = $key;

                return "[{$label}]";
            }

            return trim($value);
        };

        $name = $placeholder($user->name, 'Dein Name', 'name');
        $street = $placeholder($user->address_street, 'Straße und Hausnummer', 'address');
        $city = $placeholder(trim(($user->address_zip ?? '').' '.($user->address_city ?? '')) ?: null, 'PLZ Ort', 'address');
        $customer = $placeholder($s->customer_number, 'Kundennummer', 'customer_number');

        $end = $this->expectedEnd($s);
        $endText = $end ? ', voraussichtlich zum '.$end->format('d.m.Y') : '';

        $sender = "{$name}\n{$street}\n{$city}";
        $subject = 'Kündigung meines Vertrags „'.$s->name.'“';
        $body = implode("\n\n", [
            'Sehr geehrte Damen und Herren,',
            "hiermit kündige ich meinen Vertrag „{$s->name}“ (Kundennummer: {$customer}) fristgerecht zum nächstmöglichen Zeitpunkt{$endText}.",
            'Bitte bestätigen Sie mir die Kündigung und das Vertragsende schriftlich. Einer Verlängerung oder einem Angebot zur Vertragsfortsetzung widerspreche ich vorsorglich.',
            "Mit freundlichen Grüßen\n{$name}",
        ]);

        $date = CarbonImmutable::today()->format('d.m.Y');
        $text = "{$sender}\n\n{$date}\n\n{$subject}\n\n{$body}";

        return [
            'sender' => $sender,
            'subject' => $subject,
            'body' => $body,
            'text' => $text,
            'expected_end' => $end?->toDateString(),
            'recipient_email' => $s->provider_email,
            'missing' => array_values(array_unique($missing)),
        ];
    }

    /** Renders the (possibly edited) letter text as A4 PDF. */
    public function pdf(string $text, string $title): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pdf.cancellation', ['text' => $text, 'title' => $title])->render(), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
