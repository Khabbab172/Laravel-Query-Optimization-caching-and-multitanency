<?php

namespace App\Services;

use App\Models\Invoice;

class InvoiceService
{
    // A service method
    public function getInvoices()
    {
        // The Eloquent model will automatically apply the tenant scope.
        return Invoice::all();
    }

}