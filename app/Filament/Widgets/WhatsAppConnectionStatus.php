<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WhatsAppConnectionStatus extends BaseWidget
{
    protected function getStats(): array
    {
        $wabaId = config('services.whatsapp.waba_id');
        $phoneId = config('services.whatsapp.phone_number_id');

        $wabaMasked = str_pad(substr($wabaId, -4), strlen($wabaId), '*', STR_PAD_LEFT);
        $phoneMasked = str_pad(substr($phoneId, -4), strlen($phoneId), '*', STR_PAD_LEFT);

        return [
            Stat::make('WhatsApp Business Platform', 'Connected')
                ->description('Status')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('WABA ID', $wabaMasked)
                ->description('WhatsApp Business Account'),
            Stat::make('Phone Number ID', $phoneMasked)
                ->description('Sender Number'),
        ];
    }
}
