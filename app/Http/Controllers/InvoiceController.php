<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateBalanceJob;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    protected $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    public function index()
    {
        // The controller is not concerned with the tenant_id.
        $invoices = $this->invoiceService->getInvoices();


        // Dispatching jobs for user with ID 123
        UpdateBalanceJob::dispatch(123, 10)->onQueue('user_balance_123');
        UpdateBalanceJob::dispatch(123, -20)->onQueue('user_balance_123');

        // These two jobs for user 123 will always be processed in order.
// This job for user 456 goes to a different queue
        UpdateBalanceJob::dispatch(456, 50)->onQueue('user_balance_456');

        return response(['invoices' => $invoices]);
    }

}