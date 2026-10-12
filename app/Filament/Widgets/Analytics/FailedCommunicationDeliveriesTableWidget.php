<?php

namespace App\Filament\Widgets\Analytics;

use App\Enums\CommunicationDeliveryStatus;
use App\Filament\Resources\CommunicationDeliveries\CommunicationDeliveryResource;
use App\Models\CommunicationDelivery;
use App\Support\Filament\CommunicationRelatedRecord;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Show communication failures that still require staff attention. */
class FailedCommunicationDeliveriesTableWidget extends TableWidget
{
    protected static ?int $sort = -38;

    protected int|string|array $columnSpan = 'full';

    /** Only show the widget to authorized users when unresolved failures exist. */
    public static function canView(): bool
    {
        return (auth()->user()?->can('viewAny', CommunicationDelivery::class) ?? false)
            && CommunicationDelivery::query()
                ->where('status', CommunicationDeliveryStatus::Failed)
                ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('app.communication.failed_deliveries'))
            ->paginated(false)
            ->query(fn (): Builder => CommunicationDelivery::query()
                ->with(['template', 'related'])
                ->where('status', CommunicationDeliveryStatus::Failed)
                ->latest('failed_at')
                ->limit(5))
            ->columns([
                TextColumn::make('template.name')
                    ->label(__('app.communication.fields.template')),
                TextColumn::make('recipient')
                    ->label(__('app.communication.fields.recipient')),
                TextColumn::make('related')
                    ->label(__('app.communication.fields.related'))
                    ->state(fn (CommunicationDelivery $record): string => CommunicationRelatedRecord::label($record))
                    ->url(fn (CommunicationDelivery $record): ?string => CommunicationRelatedRecord::url($record)),
                TextColumn::make('failure_reason')
                    ->label(__('app.communication.fields.failure_reason'))
                    ->limit(50)
                    ->tooltip(fn (CommunicationDelivery $record): ?string => $record->failure_reason),
                TextColumn::make('failed_at')
                    ->label(__('app.communication.fields.failed_at'))
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('view_delivery_history')
                    ->label(__('app.communication.view_delivery_history'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(CommunicationDeliveryResource::getUrl()),
            ])
            ->recordUrl(null);
    }
}
