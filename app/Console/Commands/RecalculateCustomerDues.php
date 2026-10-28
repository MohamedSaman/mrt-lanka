<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class RecalculateCustomerDues extends Command
{
    protected $signature = 'customer:recalculate-dues';
    protected $description = 'Recalculate customer due amounts and payment allocations chronologically';

    public function handle()
    {
        $this->info('Starting customer dues recalculation...');

        $customers = Customer::all();

        foreach ($customers as $customer) {
            $this->info("Processing Customer: {$customer->name} (ID: {$customer->id})");

            try {
                $syncedCustomer = \App\Services\CustomerBalanceService::syncCustomerBalance($customer);
                if ($syncedCustomer) {
                    $this->info("  -> OB Paid: {$syncedCustomer->opening_balance_paid}/{$syncedCustomer->opening_balance} | Sales Due: {$syncedCustomer->due_amount} | Overpaid: {$syncedCustomer->overpaid_amount} | Total Due: {$syncedCustomer->total_due}");
                }
            } catch (\Exception $e) {
                $this->error("  -> Failed: " . $e->getMessage());
            }
        }

        $this->info('Done recalculating customer dues!');
        return 0;
    }
}
