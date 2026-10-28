<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Quotation;
use App\Models\Customer;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PrintController extends Controller
{
    public function downloadCustomerLedger(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        $startDate = $request->query('from');
        $endDate = $request->query('to');

        $manageCustomer = new \App\Livewire\Admin\ManageCustomer();
        $ledgerData = $manageCustomer->getCustomerLedgerData($customer, $startDate, $endDate);

        $pdf = Pdf::loadView('admin.customer.ledger-pdf', [
            'customer' => $customer,
            'ledger' => $ledgerData['entries'],
            'totalDebit' => $ledgerData['total_debit'],
            'totalCredit' => $ledgerData['total_credit'],
            'closingBalance' => $ledgerData['closing_balance'],
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('dpi', 150);
        $pdf->setOption('defaultFont', 'sans-serif');

        $datePart = $startDate && $endDate 
            ? Carbon::parse($startDate)->format('Ymd') . '-to-' . Carbon::parse($endDate)->format('Ymd')
            : now()->format('Ymd');
        $filename = 'customer-ledger-' . Str::slug($customer->name) . '-' . $datePart . '.pdf';

        return $pdf->download($filename);
    }
    public function printSale(Request $request, $id)
    {
        // Load sale with all necessary relationships including returns
        $sale = Sale::with(['customer', 'items.product', 'payments', 'returns' => function ($q) {
            $q->with('product');
        }])->findOrFail($id);

        $showDueDetails = $request->query('show_due') == 1 || session('show_due_details', false);

        // Return the print view
        return view('components.sale-receipt-print', compact('sale', 'showDueDetails'));
    }

    public function downloadSale(Request $request, $id)
    {
        // Load sale with all necessary relationships including returns
        $sale = Sale::with(['customer', 'items.product', 'payments', 'returns' => function ($q) {
            $q->with('product');
        }])->findOrFail($id);

        $showDueDetails = $request->query('show_due') == 1 || session('show_due_details', false);

        // Generate PDF
        $pdf = PDF::loadView('admin.sales.invoice', compact('sale', 'showDueDetails'));
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('dpi', 150);
        $pdf->setOption('defaultFont', 'sans-serif');

        return $pdf->download('invoice-' . $sale->invoice_number . '.pdf');
    }

    public function printQuotation($id)
    {
        // Load quotation with all necessary relationships
        $quotation = Quotation::findOrFail($id);

        // Decode items if stored as JSON
        if (is_string($quotation->items)) {
            $quotation->items = json_decode($quotation->items, true);
        }

        // Return the print view
        return view('admin.quotations.print', compact('quotation'));
    }
}
