<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExpensePaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Upi = 'upi';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Other = 'other';

    public function getLabel(): string
    {
        return __('app.payment_methods.'.$this->value);
    }
}
