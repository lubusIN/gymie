<?php

use App\Filament\Resources\Enquiries\Pages\CreateEnquiry;

it('does not offer to create another enquiry', function (): void {
    expect((new CreateEnquiry)->canCreateAnother())->toBeFalse();
});
