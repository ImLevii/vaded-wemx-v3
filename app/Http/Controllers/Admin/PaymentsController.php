<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;

class PaymentsController extends Controller
{
    public function index()
    {
        return view('admin::payments.index');
    }

    public function create()
    {
        return view('admin::payments.create');
    }

    public function edit(Payment $payment)
    {
        return view('admin::payments.edit', compact('payment'));
    }

    public function downloadInvoicePdf(Payment $payment)
    {
        $payment->load(['user', 'gatewayConfig', 'taxDetails']);

        $pdf = Pdf::loadView('invoices::payment-invoice', [
            'payment' => $payment,
        ]);

        return $pdf->download('invoice-'.($payment->invoice_id ?: $payment->id).'.pdf');
    }
}
