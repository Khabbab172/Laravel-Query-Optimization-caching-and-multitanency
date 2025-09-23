<?php

namespace App\Http\Controllers;

use App\Services\InvoiceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    protected  $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService =  $invoiceService;
    }

    public function index()
    {
        // The controller is not concerned with the tenant_id.
        $invoices = $this->invoiceService->getInvoices();

        return response( ['invoices' => $invoices]);
    }

}