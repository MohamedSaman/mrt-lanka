<?php

namespace App\Livewire\Admin;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\Cheque;
use Livewire\Component;
use Exception;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\WithDynamicLayout;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Carbon\Carbon;

#[Title('Manage Customer')]
class ManageCustomer extends Component
{
    use WithDynamicLayout;
    use WithPagination, WithFileUploads;

    public $name;
    public $contactNumber;
    public $address;
    public $email;
    public $customerType;
    public $businessName;
    public $openingBalance = 0;
    public $overpaidAmount = 0;
    public $showMoreInfo = false;

    public $editCustomerId;
    public $editName;
    public $editContactNumber;
    public $editAddress;
    public $editEmail;
    public $editCustomerType;
    public $editBusinessName;
    public $editOpeningBalance = 0;
    public $editOverpaidAmount = 0;
    public $showEditMoreInfo = false;

    public $deleteId;
    public $showEditModal = false;
    public $showCreateModal = false;
    public $showDeleteModal = false;
    public $showViewModal = false;
    public $viewCustomerDetail = [];
    public $viewCustomerSales = [];
    public $viewCustomerPayments = [];
    public $viewCustomerDues = [];
    public $viewCustomerCheques = [];
    public $viewCustomerLedger = [];
    public $viewCustomerId;
    public $ledgerStartDate = '';
    public $ledgerEndDate = '';
    public $activeTab = 'overview';
    public $perPage = 10;
    public $search = '';
    public $showImportModal = false;
    public $importFile;

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function render()
    {
        $customers = Customer::when($this->search, function ($query) {
            return $query->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('phone', 'like', '%' . $this->search . '%')
                ->orWhere('email', 'like', '%' . $this->search . '%')
                ->orWhere('business_name', 'like', '%' . $this->search . '%')
                ->orWhere('address', 'like', '%' . $this->search . '%');
        })
            ->latest()
            ->paginate($this->perPage);

        return view('livewire.admin.manage-customer', [
            'customers' => $customers,
        ])->layout($this->layout);
    }

    /** ----------------------------
     * Create Customer
     * ---------------------------- */
    public function createCustomer()
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function resetForm()
    {
        $this->reset([
            'name',
            'contactNumber',
            'address',
            'email',
            'customerType',
            'businessName',
            'openingBalance',
            'overpaidAmount',
            'showMoreInfo',
            'editCustomerId',
            'editName',
            'editContactNumber',
            'editAddress',
            'editEmail',
            'editCustomerType',
            'editBusinessName',
            'editOpeningBalance',
            'editOverpaidAmount',
            'showEditMoreInfo',
        ]);
        $this->resetErrorBag();
    }

    public function closeModal()
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->showDeleteModal = false;
        $this->showViewModal = false;
        $this->viewCustomerId = null;
        $this->ledgerStartDate = '';
        $this->ledgerEndDate = '';
        $this->resetForm();
    }

    /** ----------------------------
     * View Customer Details
     * ---------------------------- */
    public function viewDetails($id)
    {
        $customer = Customer::find($id);
        if (!$customer) {
            $this->js("Swal.fire('Error!', 'Customer Not Found', 'error')");
            return;
        }

        $this->activeTab = 'overview';


        // Sales
        $sales = Sale::where('customer_id', $customer->id)
            ->with(['items', 'payments', 'returns'])
            ->orderBy('created_at', 'desc')
            ->get();

        $this->viewCustomerSales = $sales->map(function ($sale) {
            // Calculate paid amount using total minus due
            $paidAmount = ($sale->total_amount ?? 0) - ($sale->due_amount ?? 0);
            $isReturned = $sale->delivery_status === 'cancelled'
                || str_contains(strtolower((string) ($sale->notes ?? '')), 'invoice fully returned by delivery man')
                || str_contains(strtolower((string) ($sale->notes ?? '')), 'returned by delivery man');

            return [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'sale_id' => $sale->sale_id,
                'total_amount' => $sale->total_amount ?? 0,
                'due_amount' => $sale->due_amount ?? 0,
                'payment_status' => $sale->payment_status,
                'status' => $sale->status,
                'delivery_status' => $sale->delivery_status,
                'payment_type' => $sale->payment_type,
                'created_at' => $sale->created_at ? $sale->created_at->format('M d, Y h:i A') : '-',
                'items_count' => $sale->items->count(),
                'paid_amount' => $paidAmount,
                'is_returned' => $isReturned,
            ];
        })->toArray();

        // Payments
        $payments = Payment::where('customer_id', $customer->id)
            ->with(['sale'])
            ->orderBy('created_at', 'desc')
            ->get();

        $this->viewCustomerPayments = $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'amount' => $payment->amount ?? 0,
                'payment_method' => $payment->payment_method,
                'payment_reference' => $payment->payment_reference,
                'payment_date' => $payment->payment_date ? $payment->payment_date->format('M d, Y') : '-',
                'status' => $payment->status,
                'invoice_number' => $payment->sale ? $payment->sale->invoice_number : '-',
                'notes' => $payment->notes,
                'created_at' => $payment->created_at ? $payment->created_at->format('M d, Y h:i A') : '-',
            ];
        })->toArray();

        // Dues (sales with pending payment)
        $this->viewCustomerDues = $sales->filter(function ($sale) {
            $isReturned = $sale->delivery_status === 'cancelled'
                || str_contains(strtolower((string) ($sale->notes ?? '')), 'invoice fully returned by delivery man')
                || str_contains(strtolower((string) ($sale->notes ?? '')), 'returned by delivery man');
            return !$isReturned && ($sale->due_amount ?? 0) > 0;
        })->map(function ($sale) {
            // Calculate paid amount using total minus due
            $paidAmount = ($sale->total_amount ?? 0) - ($sale->due_amount ?? 0);

            return [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'total_amount' => $sale->total_amount ?? 0,
                'due_amount' => $sale->due_amount ?? 0,
                'paid_amount' => $paidAmount,
                'status' => $sale->status,
                'payment_status' => $sale->payment_status,
                'created_at' => $sale->created_at ? $sale->created_at->format('M d, Y h:i A') : '-',
            ];
        })->values()->toArray();

        // Cheques
        $cheques = Cheque::where('customer_id', $customer->id)
            ->with(['payment'])
            ->orderBy('created_at', 'desc')
            ->get();

        $this->viewCustomerCheques = $cheques->map(function ($cheque) {
            return [
                'id' => $cheque->id,
                'cheque_number' => $cheque->cheque_number,
                'cheque_date' => $cheque->cheque_date,
                'bank_name' => $cheque->bank_name,
                'cheque_amount' => $cheque->cheque_amount ?? 0,
                'status' => $cheque->status,
            ];
        })->toArray();

        // Set current viewing customer and reset date filters
        $this->viewCustomerId = $customer->id;
        $this->ledgerStartDate = '';
        $this->ledgerEndDate = '';

        // Ledger - compute initial unfiltered ledger
        $ledgerData = $this->getCustomerLedgerData($customer);
        $this->viewCustomerLedger = $ledgerData['entries'];

        // Sync customer balance accurately using central CustomerBalanceService
        \App\Services\CustomerBalanceService::syncCustomerBalance($customer);
        $customer->refresh();

        $openingBalance = floatval($customer->opening_balance ?? 0);
        $openingBalancePaid = floatval($customer->opening_balance_paid ?? 0);
        $dynamicOverpaid = floatval($customer->overpaid_amount ?? 0);
        $returnedCheques = Cheque::where('customer_id', $customer->id)->where('status', 'return')->get();
        $returnedChequesAmount = floatval($returnedCheques->sum('cheque_amount'));
        $returnedChequesCount = $returnedCheques->count();

        // Basic info
        $this->viewCustomerDetail = [
            'id' => $customer->id,
            'name' => $customer->name,
            'business_name' => $customer->business_name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'type' => $customer->type,
            'address' => $customer->address,
            'opening_balance' => $openingBalance,
            'opening_balance_paid' => $openingBalancePaid,
            'due_amount' => floatval($customer->due_amount ?? 0),
            'returned_cheque_amount' => $returnedChequesAmount,
            'returned_cheque_count' => $returnedChequesCount,
            'overpaid_amount' => $dynamicOverpaid,
            'total_due' => floatval($customer->total_due ?? 0),
            'created_at' => $customer->created_at,
            'updated_at' => $customer->updated_at,
        ];

        $this->showViewModal = true;
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function updatedLedgerStartDate()
    {
        $this->refreshLedger();
    }

    public function updatedLedgerEndDate()
    {
        $this->refreshLedger();
    }

    public function resetLedgerFilter()
    {
        $this->ledgerStartDate = '';
        $this->ledgerEndDate = '';
        $this->refreshLedger();
    }

    public function refreshLedger()
    {
        if (!$this->viewCustomerId) return;
        $customer = Customer::find($this->viewCustomerId);
        if (!$customer) return;

        $ledgerData = $this->getCustomerLedgerData($customer, $this->ledgerStartDate, $this->ledgerEndDate);
        $this->viewCustomerLedger = $ledgerData['entries'];
    }

    /**
     * Build customer ledger data with chronological ordering and date range support.
     */
    public function getCustomerLedgerData($customer, $startDate = null, $endDate = null)
    {
        if (is_numeric($customer)) {
            $customer = Customer::find($customer);
        }

        if (!$customer) {
            return [
                'entries' => [],
                'total_debit' => 0,
                'total_credit' => 0,
                'closing_balance' => 0,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        }

        $allEntries = collect();

        // 1. Initial opening balance
        if (($customer->opening_balance ?? 0) > 0) {
            $allEntries->push([
                'date' => $customer->created_at ? $customer->created_at->format('M d, Y h:i A') : '-',
                'timestamp' => $customer->created_at ? $customer->created_at->timestamp : 0,
                'description' => 'Opening Balance',
                'reference' => '-',
                'debit' => floatval($customer->opening_balance),
                'credit' => 0,
                'type' => 'opening',
                'is_bf' => false,
            ]);
        }

        // 2. Sales
        $sales = Sale::where('customer_id', $customer->id)
            ->with(['returns'])
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($sales as $sale) {
            $isReturned = $sale->delivery_status === 'cancelled'
                || str_contains(strtolower((string) ($sale->notes ?? '')), 'invoice fully returned by delivery man')
                || str_contains(strtolower((string) ($sale->notes ?? '')), 'returned by delivery man');

            $returnsSum = $sale->returns ? floatval($sale->returns->sum('total_amount')) : 0;
            $originalSaleTotal = floatval($sale->total_amount ?? 0) + $returnsSum;

            if ($isReturned && $returnsSum == 0) {
                $allEntries->push([
                    'date' => $sale->created_at ? $sale->created_at->format('M d, Y h:i A') : '-',
                    'timestamp' => $sale->created_at ? $sale->created_at->timestamp : 0,
                    'description' => 'Sale Invoice',
                    'reference' => $sale->invoice_number ?? $sale->sale_id,
                    'debit' => $originalSaleTotal,
                    'credit' => 0,
                    'type' => 'sale',
                    'is_bf' => false,
                ]);
                $allEntries->push([
                    'date' => $sale->updated_at ? $sale->updated_at->format('M d, Y h:i A') : '-',
                    'timestamp' => $sale->updated_at ? $sale->updated_at->timestamp : 0,
                    'description' => 'Sale Invoice Returned',
                    'reference' => $sale->invoice_number ?? $sale->sale_id,
                    'debit' => 0,
                    'credit' => $originalSaleTotal,
                    'type' => 'sale_return',
                    'is_bf' => false,
                ]);
            } else {
                $allEntries->push([
                    'date' => $sale->created_at ? $sale->created_at->format('M d, Y h:i A') : '-',
                    'timestamp' => $sale->created_at ? $sale->created_at->timestamp : 0,
                    'description' => 'Sale Invoice',
                    'reference' => $sale->invoice_number ?? $sale->sale_id,
                    'debit' => $originalSaleTotal,
                    'credit' => 0,
                    'type' => 'sale',
                    'is_bf' => false,
                ]);
            }
        }

        // 3. Payments
        $payments = Payment::where('customer_id', $customer->id)
            ->with(['sale', 'cheque'])
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($payments as $payment) {
            if (!in_array($payment->status, ['paid', 'approved', 'completed', 'return'])) continue;
            
            $paymentTimestamp = $payment->payment_date ? $payment->payment_date->timestamp : ($payment->created_at ? $payment->created_at->timestamp : 0);
            $cheque = $payment->cheque ?? Cheque::where('payment_id', $payment->id)->first();
            $ref = $cheque ? "Cheque #{$cheque->cheque_number}" : ($payment->payment_reference ?? ($payment->sale ? $payment->sale->invoice_number : '-'));

            $allEntries->push([
                'date' => $payment->payment_date ? $payment->payment_date->format('M d, Y h:i A') : ($payment->created_at ? $payment->created_at->format('M d, Y h:i A') : '-'),
                'timestamp' => $paymentTimestamp,
                'description' => 'Payment Received (' . ucfirst($payment->payment_method ?? 'N/A') . ')',
                'reference' => $ref,
                'debit' => 0,
                'credit' => floatval($payment->amount ?? 0),
                'type' => 'payment',
                'is_bf' => false,
            ]);
        }

        // 4. Returned cheques
        $returnedCheques = Cheque::where('customer_id', $customer->id)->where('status', 'return')->get();
        foreach ($returnedCheques as $rc) {
            $chequeTimestamp = $rc->cheque_date ? Carbon::parse($rc->cheque_date)->timestamp : ($rc->created_at ? $rc->created_at->timestamp : 0);
            $chequeDateStr = $rc->cheque_date ? Carbon::parse($rc->cheque_date)->format('M d, Y h:i A') : ($rc->created_at ? $rc->created_at->format('M d, Y h:i A') : '-');
            $bank = $rc->bank_name ? " - {$rc->bank_name}" : '';

            $allEntries->push([
                'date' => $chequeDateStr,
                'timestamp' => $chequeTimestamp,
                'description' => "Cheque Returned (Cheque #{$rc->cheque_number}{$bank})",
                'reference' => "Cheque #{$rc->cheque_number}",
                'debit' => floatval($rc->cheque_amount ?? 0),
                'credit' => 0,
                'type' => 'cheque_return',
                'is_bf' => false,
            ]);
        }

        // 5. Product returns
        $returnsProducts = \App\Models\ReturnsProduct::whereHas('sale', function ($query) use ($customer) {
            $query->where('customer_id', $customer->id);
        })->where('total_amount', '>', 0)->with(['sale'])->get();

        foreach ($returnsProducts as $return) {
            $allEntries->push([
                'date' => $return->created_at ? $return->created_at->format('M d, Y h:i A') : '-',
                'timestamp' => $return->created_at ? $return->created_at->timestamp : 0,
                'description' => 'Product Return',
                'reference' => $return->sale ? $return->sale->invoice_number : '-',
                'debit' => 0,
                'credit' => floatval($return->total_amount ?? 0),
                'type' => 'product_return',
                'is_bf' => false,
            ]);
        }

        // Sort chronologically
        $sorted = $allEntries->sortBy('timestamp')->values();

        $startTs = !empty($startDate) ? Carbon::parse($startDate)->startOfDay()->timestamp : null;
        $endTs = !empty($endDate) ? Carbon::parse($endDate)->endOfDay()->timestamp : null;

        $finalEntries = collect();

        if ($startTs !== null) {
            // Calculate brought forward balance from all entries before start date
            $priorDebit = 0;
            $priorCredit = 0;

            foreach ($sorted as $item) {
                if ($item['timestamp'] < $startTs) {
                    $priorDebit += $item['debit'];
                    $priorCredit += $item['credit'];
                }
            }

            $priorNet = $priorDebit - $priorCredit;

            $finalEntries->push([
                'date' => Carbon::parse($startDate)->format('M d, Y'),
                'timestamp' => $startTs,
                'description' => 'Balance B/F (Brought Forward)',
                'reference' => 'Opening as of ' . Carbon::parse($startDate)->format('d/m/Y'),
                'debit' => $priorNet > 0 ? $priorNet : 0,
                'credit' => $priorNet < 0 ? abs($priorNet) : 0,
                'type' => 'opening',
                'is_bf' => true,
            ]);

            foreach ($sorted as $item) {
                if ($item['timestamp'] >= $startTs && ($endTs === null || $item['timestamp'] <= $endTs)) {
                    $finalEntries->push($item);
                }
            }
        } else {
            foreach ($sorted as $item) {
                if ($endTs === null || $item['timestamp'] <= $endTs) {
                    $finalEntries->push($item);
                }
            }
        }

        // Calculate totals and running balance
        $runningBalance = 0;
        $totalDebit = 0;
        $totalCredit = 0;

        $processedEntries = [];
        foreach ($finalEntries as $entry) {
            $runningBalance += ($entry['debit'] - $entry['credit']);
            $totalDebit += $entry['debit'];
            $totalCredit += $entry['credit'];
            $entry['balance'] = $runningBalance;
            $processedEntries[] = $entry;
        }

        return [
            'entries' => $processedEntries,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'closing_balance' => $runningBalance,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    /**
     * Export customer ledger as A4 PDF download.
     */
    public function exportLedgerPdf($customerId = null)
    {
        $id = $customerId ?: $this->viewCustomerId;
        if (!$id) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Customer not found.']);
            return;
        }

        $customer = Customer::find($id);
        if (!$customer) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Customer not found.']);
            return;
        }

        // Only use date filters if exporting for currently viewed customer
        $startDate = ($customerId && $customerId !== $this->viewCustomerId) ? null : $this->ledgerStartDate;
        $endDate = ($customerId && $customerId !== $this->viewCustomerId) ? null : $this->ledgerEndDate;

        $ledgerData = $this->getCustomerLedgerData($customer, $startDate, $endDate);

        try {
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

            return response()->streamDownload(
                function () use ($pdf) {
                    echo $pdf->output();
                },
                $filename
            );
        } catch (\Exception $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Failed to export PDF: ' . $e->getMessage()]);
        }
    }

    public function saveCustomer()
    {
        $this->validate([
            'name' => 'required',
            'customerType' => 'required',
            'contactNumber' => 'nullable',
            'address' => 'nullable',
            'email' => 'nullable|email|unique:customers,email',
            'businessName' => 'nullable',
            'openingBalance' => 'nullable|numeric|min:0',
            'overpaidAmount' => 'nullable|numeric|min:0',
        ]);

        try {
            $openingBalance = floatval($this->openingBalance ?? 0);
            $overpaidAmount = floatval($this->overpaidAmount ?? 0);
            $totalDue = $openingBalance - $overpaidAmount;

            Customer::create([
                'name' => $this->name,
                'phone' => $this->contactNumber,
                'address' => $this->address,
                'email' => $this->email,
                'type' => $this->customerType,
                'business_name' => $this->businessName,
                'user_id' => Auth::id(),
                'opening_balance' => $openingBalance,
                'due_amount' => 0,
                'total_due' => $totalDue,
                'overpaid_amount' => $overpaidAmount,
            ]);

            $this->js("Swal.fire('Success!', 'Customer Created Successfully', 'success')");
            $this->closeModal();
        } catch (Exception $e) {
            $this->js("Swal.fire('Error!', '" . $e->getMessage() . "', 'error')");
        }
    }

    /** ----------------------------
     * Edit Customer
     * ---------------------------- */
    public function editCustomer($id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            $this->js("Swal.fire('Error!', 'Customer not found.', 'error')");
            return;
        }

        $this->editCustomerId = $customer->id;
        $this->editName = $customer->name;
        $this->editContactNumber = $customer->phone;
        $this->editBusinessName = $customer->business_name;
        $this->editCustomerType = $customer->type;
        $this->editAddress = $customer->address;
        $this->editEmail = $customer->email;
        $this->editOpeningBalance = $customer->opening_balance ?? 0;
        $this->editOverpaidAmount = $customer->overpaid_amount ?? 0;
        $this->showEditMoreInfo = ($this->editOpeningBalance > 0 || $this->editOverpaidAmount > 0);

        $this->showEditModal = true;
    }

    public function updateCustomer()
    {
        $this->validate([
            'editName' => 'required',
            'editCustomerType' => 'required',
            'editBusinessName' => 'nullable',
            'editContactNumber' => 'nullable',
            'editAddress' => 'nullable',
            'editEmail' => 'nullable|email|unique:customers,email,' . $this->editCustomerId,
            'editOpeningBalance' => 'nullable|numeric|min:0',
            'editOverpaidAmount' => 'nullable|numeric|min:0',
        ]);

        try {
            $customer = Customer::find($this->editCustomerId);
            if (!$customer) {
                $this->js("Swal.fire('Error!', 'Customer not found.', 'error')");
                return;
            }

            $openingBalance = floatval($this->editOpeningBalance ?? 0);
            $overpaidAmount = floatval($this->editOverpaidAmount ?? 0);
            $dueAmount = floatval($customer->due_amount ?? 0);
            $totalDue = $openingBalance + $dueAmount - $overpaidAmount;

            $customer->update([
                'name' => $this->editName,
                'phone' => $this->editContactNumber,
                'business_name' => $this->editBusinessName,
                'type' => $this->editCustomerType,
                'address' => $this->editAddress,
                'email' => $this->editEmail,
                'opening_balance' => $openingBalance,
                'total_due' => $totalDue,
                'overpaid_amount' => $overpaidAmount,
            ]);

            $this->js("Swal.fire('Success!', 'Customer Updated Successfully', 'success')");
            $this->closeModal();
        } catch (Exception $e) {
            $this->js("Swal.fire('Error!', '" . $e->getMessage() . "', 'error')");
        }
    }

    /** ----------------------------
     * Delete Customer
     * ---------------------------- */
    public function confirmDelete($id)
    {
        $this->deleteId = $id;
        $this->showDeleteModal = true;
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->deleteId = null;
    }

    public function deleteCustomer()
    {
        try {
            Customer::where('id', $this->deleteId)->delete();
            $this->js("Swal.fire('Success!', 'Customer deleted successfully.', 'success')");
            $this->dispatch('refreshPage');
            $this->cancelDelete();
        } catch (Exception $e) {
            $this->js("Swal.fire('Error!', '" . $e->getMessage() . "', 'error')");
        }
    }

    /** ----------------------------
     * Import Customers from Excel
     * ---------------------------- */
    public function openImportModal()
    {
        $this->showImportModal = true;
    }

    public function closeImportModal()
    {
        $this->showImportModal = false;
        $this->importFile = null;
    }

    public function downloadTemplate()
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $headers = ['Customer Name', 'Contact Number', 'Email', 'Business Name', 'Customer Type', 'Address'];
            $sheet->fromArray([$headers], null, 'A1');

            // Style headers
            $headerStyle = $sheet->getStyle('A1:F1');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $headerStyle->getFill()->getStartColor()->setARGB('FF4361EE');
            $headerStyle->getFont()->getColor()->setARGB('FFFFFFFF');

            // Add sample data
            $sampleData = [
                ['Ahmed Hameed', '0771234567, 0772345678', 'ahmed@example.com', 'Ahmed Trading', 'retail', 'Colombo'],
                ['Lakshmi Enterprises', '0701234567 / 0702345678', 'info@lakshmi.com', 'Lakshmi Trading', 'wholesale', 'Kandy'],
            ];
            $sheet->fromArray($sampleData, null, 'A2');

            // Auto adjust columns
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $filename = 'customer_import_template_' . date('Y-m-d') . '.xlsx';

            $response = new StreamedResponse(function () use ($spreadsheet) {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            });

            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->headers->set('Cache-Control', 'no-cache');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');

            return $response;
        } catch (Exception $e) {
            $this->js("Swal.fire('Error!', 'Failed to download template: " . addslashes($e->getMessage()) . "', 'error')");
        }
    }

    public function importCustomers()
    {
        $this->validate([
            'importFile' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $file = $this->importFile->getRealPath();
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $spreadsheet = $reader->load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray();

            $imported = 0;
            $skipped = 0;

            // Skip header row
            foreach (array_slice($data, 1) as $row) {
                if (empty($row[0])) continue; // Skip empty rows

                $name = $row[0] ?? null;
                $phone = $row[1] ?? null;
                $email = $row[2] ?? null;
                $businessName = $row[3] ?? null;
                $type = strtolower($row[4] ?? 'retail');
                $address = $row[5] ?? null;

                if (!$name) {
                    $skipped++;
                    continue;
                }

                // Validate type
                if (!in_array($type, ['retail', 'wholesale'])) {
                    $type = 'retail';
                }

                // Check if customer already exists
                $exists = Customer::where('name', $name)
                    ->where('phone', $phone)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Create customer
                Customer::create([
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'business_name' => $businessName,
                    'type' => $type,
                    'address' => $address,
                    'user_id' => Auth::id(),
                ]);

                $imported++;
            }

            $this->js("Swal.fire('Success!', 'Imported: $imported | Skipped: $skipped', 'success')");
            $this->closeImportModal();
        } catch (Exception $e) {
            $this->js("Swal.fire('Error!', '" . addslashes($e->getMessage()) . "', 'error')");
        }
    }
}
