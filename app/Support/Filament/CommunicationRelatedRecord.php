<?php

namespace App\Support\Filament;

use App\Models\CommunicationDelivery;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\Member;
use Illuminate\Database\Eloquent\Model;

final class CommunicationRelatedRecord
{
    private function __construct() {}

    /** Return a human-readable label for a related business record. */
    public static function label(CommunicationDelivery $delivery): string
    {
        $related = $delivery->related;

        return match (true) {
            $related instanceof Invoice => $related->number,
            $related instanceof Member, $related instanceof Enquiry => $related->name,
            $related instanceof Model => class_basename($related).' #'.$related->getKey(),
            default => __('app.placeholders.na'),
        };
    }

    /** Return the Filament URL for supported related business records. */
    public static function url(CommunicationDelivery $delivery): ?string
    {
        return match (true) {
            $delivery->related instanceof Invoice => route('filament.admin.resources.invoices.view', $delivery->related),
            $delivery->related instanceof Member => route('filament.admin.resources.members.view', $delivery->related),
            $delivery->related instanceof Enquiry => route('filament.admin.resources.enquiries.view', $delivery->related),
            default => null,
        };
    }
}
