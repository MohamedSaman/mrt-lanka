<?php

namespace App\Services;

use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerBalanceService
{
    /**
     * Synchronize and recalculate the exact balance for a customer.
     * Allocates approved payments chronologically to opening balance, then to sales.
     *
     * @param  int|Customer  $customer
     * @return Customer|null
     */
    public static function syncCustomerBalance($customer)
    {
        if (is_numeric($customer)) {
            $customer = Customer::find($customer);
        }

        if (! $customer) {
            return null;
        }

        try {
            DB::beginTransaction();

            $openingBalance = floatval($customer->opening_balance ?? 0);
            $openingBalancePaid = 0;

            // Get all sales for this customer, ordered chronologically
            $sales = Sale::where('customer_id', $customer->id)
                ->with('returns')
                ->orderBy('created_at', 'asc')
                ->get();

            $salesData = [];
            foreach ($sales as $sale) {
                $isReturned = $sale->delivery_status === 'cancelled'
                    || str_contains(strtolower((string) ($sale->notes ?? '')), 'invoice fully returned by delivery man')
                    || str_contains(strtolower((string) ($sale->notes ?? '')), 'returned by delivery man');

                $returnsSum = $sale->returns ? floatval($sale->returns->sum('total_amount')) : 0;
                $netAmount = $isReturned ? 0 : max(0, floatval($sale->total_amount));

                $salesData[$sale->id] = [
                    'sale' => $sale,
                    'net_amount' => $netAmount,
                    'paid_amount' => 0,
                    'due_amount' => $netAmount,
                    'payment_status' => $netAmount > 0 ? 'pending' : 'paid',
                ];
            }

            // Get all approved/completed payments for this customer
            $payments = Payment::where('customer_id', $customer->id)
                ->whereIn('status', ['paid', 'approved', 'completed'])
                ->orderBy('payment_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

            $totalOverpaid = 0;

            // Fetch any explicit payment_allocations for this customer's payments
            $customerPaymentIds = $payments->pluck('id')->toArray();
            $allAllocations = ! empty($customerPaymentIds)
                ? DB::table('payment_allocations')->whereIn('payment_id', $customerPaymentIds)->get()->groupBy('payment_id')
                : collect();

            foreach ($payments as $payment) {
                $remainingPayment = floatval($payment->amount);
                if ($remainingPayment <= 0) {
                    continue;
                }

                // Priority 1: Direct Sale Payments (e.g. POS StoreBilling payments with sale_id)
                // This payment belongs strictly to this particular bill!
                if ($payment->sale_id && isset($salesData[$payment->sale_id])) {
                    $targetSale = &$salesData[$payment->sale_id];
                    $allocationAmount = min($remainingPayment, $targetSale['due_amount']);
                    $targetSale['paid_amount'] += $allocationAmount;
                    $targetSale['due_amount'] = max(0, $targetSale['due_amount'] - $allocationAmount);
                    $targetSale['payment_status'] = $targetSale['due_amount'] <= 0.01 ? 'paid' : ($targetSale['paid_amount'] > 0 ? 'partial' : 'pending');
                    $remainingPayment -= $allocationAmount;

                    // Excess on this specific sale goes to overpaid credit (does not pay old debts)
                    if ($remainingPayment > 0) {
                        $totalOverpaid += $remainingPayment;
                        $remainingPayment = 0;
                    }
                    unset($targetSale);

                    continue;
                }

                // Priority 2: Explicit allocations from payment_allocations table (e.g. AddCustomerReceipt)
                if ($allAllocations->has($payment->id)) {
                    $paymentAllocations = $allAllocations->get($payment->id);
                    foreach ($paymentAllocations as $alloc) {
                        $allocAmount = floatval($alloc->allocated_amount);
                        if ($allocAmount <= 0) {
                            continue;
                        }

                        if ($alloc->sale_id === null) {
                            // Explicitly allocated to opening balance
                            $obDue = max(0, $openingBalance - $openingBalancePaid);
                            $applied = min($allocAmount, $obDue);
                            $openingBalancePaid += $applied;
                            $excess = $allocAmount - $applied;
                            if ($excess > 0) {
                                $totalOverpaid += $excess;
                            }
                        } elseif (isset($salesData[$alloc->sale_id])) {
                            // Explicitly allocated to this specific sale
                            $targetSale = &$salesData[$alloc->sale_id];
                            $applied = min($allocAmount, $targetSale['due_amount']);
                            $targetSale['paid_amount'] += $applied;
                            $targetSale['due_amount'] = max(0, $targetSale['due_amount'] - $applied);
                            $targetSale['payment_status'] = $targetSale['due_amount'] <= 0.01 ? 'paid' : ($targetSale['paid_amount'] > 0 ? 'partial' : 'pending');
                            $excess = $allocAmount - $applied;
                            if ($excess > 0) {
                                $totalOverpaid += $excess;
                            }
                            unset($targetSale);
                        }
                    }

                    continue;
                }

                // Priority 3: General Unallocated Payments (sale_id is null and no allocations)
                // 1. Allocate to opening balance first
                if ($remainingPayment > 0 && $openingBalancePaid < $openingBalance) {
                    $obDue = $openingBalance - $openingBalancePaid;
                    if ($remainingPayment >= $obDue) {
                        $openingBalancePaid = $openingBalance;
                        $remainingPayment -= $obDue;
                    } else {
                        $openingBalancePaid += $remainingPayment;
                        $remainingPayment = 0;
                    }
                }

                // 2. Allocate to sales chronologically
                foreach ($salesData as $saleId => &$data) {
                    if ($remainingPayment <= 0) {
                        break;
                    }

                    if ($data['due_amount'] > 0) {
                        if ($remainingPayment >= $data['due_amount']) {
                            $allocationAmount = $data['due_amount'];
                            $data['paid_amount'] += $allocationAmount;
                            $data['due_amount'] = 0;
                            $data['payment_status'] = 'paid';
                            $remainingPayment -= $allocationAmount;
                        } else {
                            $allocationAmount = $remainingPayment;
                            $data['paid_amount'] += $allocationAmount;
                            $data['due_amount'] = max(0, $data['due_amount'] - $allocationAmount);
                            $data['payment_status'] = 'partial';
                            $remainingPayment = 0;
                        }
                    }
                }
                unset($data);

                // 3. Excess unallocated payment becomes overpaid credit
                if ($remainingPayment > 0) {
                    $totalOverpaid += $remainingPayment;
                }
            }

            // Priority 4: Allocate any unallocated / excess payments (e.g. from returns on paid invoices or overpayments)
            // to any remaining unpaid debts chronologically
            if ($totalOverpaid > 0) {
                // 1. First to opening balance if still unpaid
                if ($openingBalancePaid < $openingBalance) {
                    $obDue = $openingBalance - $openingBalancePaid;
                    $applied = min($totalOverpaid, $obDue);
                    $openingBalancePaid += $applied;
                    $totalOverpaid -= $applied;
                }

                // 2. Then to sales chronologically
                if ($totalOverpaid > 0) {
                    foreach ($salesData as $saleId => &$data) {
                        if ($totalOverpaid <= 0) {
                            break;
                        }

                        if ($data['due_amount'] > 0) {
                            $applied = min($totalOverpaid, $data['due_amount']);
                            $data['paid_amount'] += $applied;
                            $data['due_amount'] = max(0, $data['due_amount'] - $applied);
                            $data['payment_status'] = $data['due_amount'] <= 0.01 ? 'paid' : ($data['paid_amount'] > 0 ? 'partial' : 'pending');
                            $totalOverpaid -= $applied;
                        }
                    }
                    unset($data);
                }
            }

            // Save updated sale amounts
            $totalSalesDue = 0;
            foreach ($salesData as $saleId => $data) {
                $sale = $data['sale'];
                $dueAmount = round($data['due_amount'], 2);
                $sale->due_amount = max(0, $dueAmount);
                $sale->payment_status = $data['payment_status'];
                $sale->payment_type = $dueAmount <= 0.01 ? 'full' : 'partial';
                $sale->save();

                $totalSalesDue += $sale->due_amount;
            }

            // Calculate returned cheques amount
            $returnedChequesAmount = floatval(
                Cheque::where('customer_id', $customer->id)
                    ->where('status', 'return')
                    ->sum('cheque_amount')
            );

            // Update customer record
            $customer->opening_balance_paid = round($openingBalancePaid, 2);
            $customer->due_amount = round($totalSalesDue, 2);
            $customer->overpaid_amount = round($totalOverpaid, 2);

            $obDue = max(0, $openingBalance - $openingBalancePaid);
            $totalDue = $obDue + $totalSalesDue - $totalOverpaid;
            $customer->total_due = round($totalDue, 2);

            $customer->save();

            DB::commit();

            return $customer;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error syncing customer balance: '.$e->getMessage(), [
                'customer_id' => $customer->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
