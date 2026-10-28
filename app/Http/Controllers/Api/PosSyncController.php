<?php

namespace App\Http\Controllers\Api;

use App\Models\ProductDetail;
use App\Models\ProductStock;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PosSyncController extends ApiController
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $expectedToken = env('FASTPOS_TOKEN');
            if (!empty($expectedToken)) {
                $bearer = $request->bearerToken();
                $headerToken = $request->header('X-FastPOS-Token');
                $inputToken = $request->input('token') ?: $request->input('api_token');
                $provided = $bearer ?: ($headerToken ?: $inputToken);

                if (!$provided || trim((string)$provided) !== trim((string)$expectedToken)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized: Invalid or missing FastPOS API Token. Please check token in FastPOS settings.'
                    ], 401);
                }
            }
            return $next($request);
        });
    }

    /**
     * 1. POS Health Check / Heartbeat
     * GET /api/v1/pos/health
     */
    public function health()
    {
        return response()->json([
            'status' => 'ok',
            'server_time' => now()->toIso8601String(),
            'tenant' => 'jaffnagold',
            'system' => 'Jaffna Gold & Jewelry POS API',
            'version' => '1.0.0'
        ]);
    }

    /**
     * 2. Settings & Tenant Verification
     * POST /api/v1/pos/settings/verify
     */
    public function verifySettings(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'tenant' => 'jaffnagold',
            'shop_name' => 'Jaffna Gold & Jewelry',
            'currency' => 'LKR',
            'currency_symbol' => 'Rs.',
            'tax_percentage' => 0.0,
            'address' => 'Hospital Road, Jaffna, Sri Lanka',
            'phone' => '+94 21 222 0000',
            'receipt_footer' => 'Thank you for shopping with Jaffna Gold!'
        ]);
    }

    /**
     * 2.1 Active Staff & Users for POS Cashier Selection
     * GET /api/v1/pos/users
     */
    public function getUsers(Request $request)
    {
        try {
            $hasRole = \Illuminate\Support\Facades\Schema::hasColumn('users', 'role');
            $hasStaffType = \Illuminate\Support\Facades\Schema::hasColumn('users', 'staff_type');
            $cols = ['id', 'name', 'email'];
            if ($hasRole) $cols[] = 'role';
            if ($hasStaffType) $cols[] = 'staff_type';

            $users = \App\Models\User::select($cols)
                ->where(function ($q) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'status')) {
                        $q->whereNull('status')->orWhere('status', 'active')->orWhere('status', '1');
                    }
                })
                ->orderBy('name')
                ->get();

            $formatted = [];
            foreach ($users as $u) {
                $role = 'staff';
                if ($hasStaffType && !empty($u->staff_type)) {
                    $role = $u->staff_type;
                } elseif ($hasRole && !empty($u->role)) {
                    $role = $u->role;
                }
                $formatted[] = [
                    'id' => (int)$u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => strtolower((string)$role),
                ];
            }

            return response()->json([
                'status' => 'success',
                'users' => $formatted
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 3. Product Catalog Sync (Delta or Full)
     * GET /api/v1/pos/products/sync
     */
    public function syncProducts(Request $request)
    {
        $query = ProductDetail::with(['price', 'stock', 'prices', 'stocks'])
            ->where('status', 'active');

        if ($request->has('updated_after') && !empty($request->updated_after)) {
            $query->where('updated_at', '>=', $request->updated_after);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('barcode', $s)
                  ->orWhere('code', $s)
                  ->orWhere('id', $s)
                  ->orWhere('barcode', 'like', "%{$s}")
                  ->orWhere('barcode', 'like', "%{$s}%")
                  ->orWhere('code', 'like', "%{$s}%")
                  ->orWhere('name', 'like', "%{$s}%");
            });
        }

        $perPage = min(max((int) $request->get('per_page', 500), 1), 1000);
        $page = max((int) $request->get('page', 1), 1);
        $totalAll = (clone $query)->count();
        $products = $query->orderBy('id')->skip(($page - 1) * $perPage)->take($perPage)->get();

        $formatted = [];
        foreach ($products as $p) {
            $sellingPrice = 0.0;
            if ($p->price) {
                $sellingPrice = (float) ($p->price->selling_price ?? $p->price->retail_price ?? 0.0);
            }

            $availableStock = 0.0;
            if ($p->stock) {
                $availableStock = (float) ($p->stock->available_stock ?? 0.0);
            }

            $formatted[] = [
                'id' => $p->id,
                'remote_id' => $p->id,
                'sku' => $p->code ?? "PROD-{$p->id}",
                'barcode' => $p->barcode ?? $p->code ?? (string)$p->id,
                'name' => (!empty($p->size) && !str_contains($p->name, (string)$p->size)) ? "{$p->name} {$p->size}" : $p->name,
                'unit' => 'Pcs',
                'selling_price' => $sellingPrice,
                'min_selling_price' => $sellingPrice,
                'tax_percentage' => 0.0,
                'stock_qty' => $availableStock,
                'is_active' => true,
                'updated_at' => $p->updated_at ? $p->updated_at->toIso8601String() : now()->toIso8601String()
            ];
        }

        return response()->json([
            'status' => 'success',
            'tenant' => 'jaffnagold',
            'sync_timestamp' => now()->toIso8601String(),
            'total_count' => count($formatted),
            'total_all' => $totalAll,
            'page' => $page,
            'has_more' => ($page * $perPage) < $totalAll,
            'products' => $formatted
        ]);
    }

    /**
     * 4. Batch Invoice Sync
     * POST /api/v1/pos/invoices/sync
     */
    public function syncInvoices(Request $request)
    {
        $invoices = $request->input('invoices', []);
        $terminalId = $request->input('terminal_id', 'POS-01');

        if (empty($invoices)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No invoices provided'
            ], 400);
        }

        $syncedUuids = [];

        DB::transaction(function () use ($invoices, $terminalId, &$syncedUuids) {
            foreach ($invoices as $invData) {
                $uuid = $invData['uuid'] ?? $invData['local_invoice_no'];
                $billNo = $invData['local_invoice_no'] ?? $uuid;

                // Idempotency: Check if sale already exists
                $existing = Sale::where('invoice_number', $billNo)->first();
                if ($existing) {
                    $syncedUuids[] = $billNo;
                    continue;
                }

                $customerPhone = $invData['customer_phone'] ?? null;
                if (!empty($customerPhone) && $customerPhone === 'xxxxx') {
                    $customerPhone = null;
                }

                // Resolve customer_id if possible
                $customerId = null;
                if (!empty($customerPhone)) {
                    $customerId = Customer::where('phone', $customerPhone)->value('id');
                }
                if (!$customerId) {
                    $customerId = Customer::where('name', 'Walking Customer')->value('id');
                }

                $userId = \Illuminate\Support\Facades\Auth::id();
                if (!$userId) {
                    if (!empty($invData['user_id'])) {
                        $userId = (int)$invData['user_id'];
                    } elseif (!empty($invData['cashier_name'])) {
                        $foundUser = \App\Models\User::where('name', $invData['cashier_name'])->first();
                        $userId = $foundUser ? $foundUser->id : null;
                    }
                    if (!$userId) {
                        $staffUser = \App\Models\User::where('role', 'staff')->where('id', 207)->first();
                        $userId = $staffUser ? $staffUser->id : (\App\Models\User::first()?->id ?? 1);
                    }
                }

                // Create Sale record
                $dueAmt = (float)($invData['due_amount'] ?? 0.0);
                $isPartial = $dueAmt > 0;
                $sale = Sale::create([
                    'sale_id' => Sale::generateSaleId(),
                    'invoice_number' => $billNo,
                    'customer_id' => $customerId,
                    'user_id' => $userId,
                    'walking_customer_name' => $invData['customer_name'] ?? 'Walk-In',
                    'walking_customer_phone' => $customerPhone,
                    'customer_type' => 'retail',
                    'subtotal' => $invData['subtotal'] ?? 0.0,
                    'discount_amount' => $invData['discount_amount'] ?? 0.0,
                    'discount_type' => 'fixed',
                    'total_amount' => $invData['grand_total'] ?? 0.0,
                    'payment_type' => $isPartial ? 'partial' : 'full',
                    'payment_status' => $isPartial ? 'partial' : 'paid',
                    'status' => 'confirm',
                    'sale_type' => 'pos',
                    'order_source' => 'pos',
                    'delivery_status' => 'delivered',
                    'notes' => "Synced from FastPOS Terminal [{$terminalId}]",
                    'due_amount' => $dueAmt
                ]);

                // Create Payment record(s)
                try {
                    $rawPm = strtolower(trim($invData['payment_method'] ?? 'cash'));
                    $splitPayments = $invData['split_payments'] ?? null;
                    if (is_string($splitPayments)) {
                        $splitPayments = json_decode($splitPayments, true);
                    }

                    if ($rawPm === 'multiple' && is_array($splitPayments)) {
                        // 1. Primary cash portion
                        $cashAmt = (float)($splitPayments['cash'] ?? 0.0);
                        if ($cashAmt > 0) {
                            \App\Models\Payment::create([
                                'customer_id' => $customerId,
                                'sale_id' => $sale->id,
                                'amount' => $cashAmt,
                                'payment_method' => 'cash',
                                'payment_reference' => "CASH-{$billNo}",
                                'is_completed' => true,
                                'status' => 'paid',
                                'payment_date' => now(),
                                'notes' => "Cash portion for {$billNo}"
                            ]);
                        }

                        // 2. Secondary portion (Card/Online or Bank Transfer)
                        $secMethod = strtolower(trim($splitPayments['secondary_method'] ?? 'online'));
                        $enumSecMethod = in_array($secMethod, ['card', 'online', 'card payment']) ? 'online' : 'bank_transfer';
                        $secAmt = (float)($splitPayments['secondary_amount'] ?? 0.0);
                        $secRef = $splitPayments['secondary_ref'] ?? "POS-{$billNo}";

                        if ($secAmt > 0) {
                            \App\Models\Payment::create([
                                'customer_id' => $customerId,
                                'sale_id' => $sale->id,
                                'amount' => $secAmt,
                                'payment_method' => $enumSecMethod,
                                'payment_reference' => $secRef ?: "POS-{$billNo}",
                                'is_completed' => true,
                                'status' => 'paid',
                                'payment_date' => now(),
                                'notes' => "Secondary ({$enumSecMethod}) portion for {$billNo}"
                            ]);
                        }
                    } else {
                        // Single payment
                        $enumMethod = match($rawPm) {
                            'card', 'online', 'card payment' => 'online',
                            'bank_transfer', 'transfer', 'bank transfer' => 'bank_transfer',
                            'cheque' => 'cheque',
                            'credit' => 'credit',
                            'multiple' => 'multiple',
                            default => 'cash'
                        };

                        $paidAmt = (float)($invData['paid_amount'] ?? $invData['grand_total'] ?? 0.0);
                        if ($paidAmt > 0) {
                            $payRef = $invData['cheque_no'] ?? ($invData['payment_reference'] ?? "POS-{$billNo}");
                            $notes = $rawPm === 'cheque'
                                ? "Cheque #" . ($invData['cheque_no'] ?? '') . " (" . ($invData['bank_name'] ?? '') . ") for {$billNo}"
                                : "Payment for {$billNo}";

                            $payment = \App\Models\Payment::create([
                                'customer_id' => $customerId,
                                'sale_id' => $sale->id,
                                'amount' => $paidAmt,
                                'payment_method' => $enumMethod,
                                'payment_reference' => $payRef,
                                'is_completed' => ($rawPm !== 'cheque'),
                                'status' => ($rawPm === 'cheque' ? 'pending' : 'paid'),
                                'payment_date' => now(),
                                'notes' => $notes
                            ]);

                            if ($rawPm === 'cheque' && class_exists(\App\Models\Cheque::class)) {
                                try {
                                    \App\Models\Cheque::create([
                                        'cheque_number' => $invData['cheque_no'] ?? $payRef,
                                        'cheque_date' => $invData['cheque_date'] ?? now()->toDateString(),
                                        'bank_name' => $invData['bank_name'] ?? 'Cheque',
                                        'cheque_amount' => $paidAmt,
                                        'status' => 'pending',
                                        'customer_id' => $customerId,
                                        'payment_id' => $payment->id
                                    ]);
                                } catch (\Exception $chqEx) {
                                    Log::warning("POS Cheque table create error: " . $chqEx->getMessage());
                                }
                            }
                        }
                    }
                } catch (\Exception $pe) {
                    Log::warning("POS Payment creation note: " . $pe->getMessage());
                }

                // Create Line Items & Decrement Stock safely
                $items = $invData['items'] ?? [];
                foreach ($items as $item) {
                    $productId = $item['product_remote_id'] ?? $item['product_id'] ?? null;
                    $qty = (float)($item['qty'] ?? 1);
                    $price = (float)($item['unit_price'] ?? 0.0);
                    $lineTotal = (float)($item['line_total'] ?? ($qty * $price));

                    // Safely validate foreign key: ensure product_id exists in product_details table
                    $validProductId = null;
                    if (!empty($productId)) {
                        $foundProduct = ProductDetail::find($productId);
                        if ($foundProduct) {
                            $validProductId = $foundProduct->id;
                        }
                    }

                    // Fallback to SKU / barcode match if remote ID did not match local product_details
                    if (!$validProductId && !empty($item['sku'])) {
                        $foundProduct = ProductDetail::where('code', $item['sku'])
                            ->orWhere('barcode', $item['sku'])
                            ->first();
                        if ($foundProduct) {
                            $validProductId = $foundProduct->id;
                        }
                    }
                    if (!$validProductId && !empty($item['barcode'])) {
                        $foundProduct = ProductDetail::where('barcode', $item['barcode'])
                            ->orWhere('code', $item['barcode'])
                            ->first();
                        if ($foundProduct) {
                            $validProductId = $foundProduct->id;
                        }
                    }

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $validProductId,
                        'product_code' => $item['sku'] ?? $item['barcode'] ?? '',
                        'product_name' => $item['product_name'] ?? 'Item',
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'discount_per_unit' => 0.0,
                        'total_discount' => 0.0,
                        'discount_type' => 'fixed',
                        'discount_percentage' => 0.0,
                        'total' => $lineTotal
                    ]);

                    // Deduct stock in jaffnagold if a valid product_id is associated
                    if ($validProductId) {
                        $stock = ProductStock::where('product_id', $validProductId)->first();
                        if ($stock) {
                            $stock->available_stock = max(0, $stock->available_stock - $qty);
                            $stock->total_stock = max(0, $stock->total_stock - $qty);
                            $stock->save();
                        }
                    }
                }

                // Auto-send SMS invoice if requested and phone is present (matching store billing behaviour)
                if (!empty($customerPhone) && !empty($invData['send_sms'])) {
                    try {
                        \App\Services\SmsService::notifyStoreSale($sale);
                    } catch (\Throwable $se) {
                        Log::warning("[PosSync] Auto SMS sending error: " . $se->getMessage(), [
                            'bill_no' => $billNo,
                            'phone' => $customerPhone
                        ]);
                    }
                }

                $syncedUuids[] = $billNo;
            }
        });

        return response()->json([
            'status' => 'success',
            'terminal_id' => $terminalId,
            'synced_count' => count($syncedUuids),
            'synced_uuids' => $syncedUuids
        ]);
    }

    /**
     * 5. Direct SMS Dispatch from POS Terminal
     * POST /api/v1/pos/sms/send
     */
    public function sendSms(Request $request)
    {
        $phone = $request->input('phone');
        $message = $request->input('message');

        if (empty($phone) || empty($message)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Phone number and message are required.'
            ], 422);
        }

        $result = \App\Services\SmsService::sendSms($phone, $message);
        return response()->json([
            'status' => ($result['success'] ?? false) ? 'success' : 'error',
            'message' => $result['message'] ?? 'SMS response received',
            'data' => $result
        ]);
    }

    /**
     * 6. Fetch Today's Invoices / Sales for POS Terminal Sync
     * GET /api/v1/pos/invoices/today
     * GET /api/v1/pos/sales/today
     */
    public function getTodaysInvoices(Request $request)
    {
        $date = $request->input('date', now()->toDateString());
        $includeRecent = filter_var($request->input('include_recent', false), FILTER_VALIDATE_BOOLEAN);

        $sales = Sale::with(['items', 'payments', 'customer'])
            ->whereDate('created_at', $date)
            ->orderBy('id', 'desc')
            ->get();

        // If today has 0 sales yet and include_recent is requested, fallback to recent sales
        if ($sales->isEmpty() && $includeRecent) {
            $sales = Sale::with(['items', 'payments', 'customer'])
                ->orderBy('id', 'desc')
                ->take(50)
                ->get();
        }

        $formatted = [];
        foreach ($sales as $s) {
            $items = [];
            foreach ($s->items as $it) {
                $items[] = [
                    'product_id' => $it->product_id,
                    'product_name' => $it->product_name ?? 'Item',
                    'sku' => $it->product_code ?? '',
                    'qty' => (float)$it->quantity,
                    'unit_price' => (float)$it->unit_price,
                    'line_total' => (float)$it->total,
                    'discount_percent' => (float)($it->discount_percentage ?? 0)
                ];
            }

            $paymentMethod = 'CASH';
            $splitPayments = null;
            if ($s->payments && $s->payments->isNotEmpty()) {
                $uniqueMethods = $s->payments->pluck('payment_method')->filter()->unique();
                if ($uniqueMethods->count() > 1) {
                    $paymentMethod = 'MULTIPLE';
                    $cashPay = $s->payments->firstWhere('payment_method', 'cash');
                    $secPay = $s->payments->where('payment_method', '!=', 'cash')->first();
                    $splitPayments = [
                        'cash' => (float)($cashPay ? $cashPay->amount : 0.0),
                        'secondary_method' => $secPay ? ($secPay->payment_method === 'online' ? 'card' : 'bank_transfer') : 'card',
                        'secondary_amount' => (float)($secPay ? $secPay->amount : 0.0),
                        'secondary_ref' => $secPay ? ($secPay->payment_reference ?? '') : '',
                    ];
                } elseif ($uniqueMethods->count() === 1) {
                    $m = strtolower($uniqueMethods->first());
                    $paymentMethod = match($m) {
                        'cash' => 'CASH',
                        'bank_transfer' => 'BANK TRANSFER',
                        'online', 'card' => 'CARD PAYMENT',
                        'cheque' => 'CHEQUE',
                        'credit' => 'CREDIT',
                        'multiple' => 'MULTIPLE',
                        default => strtoupper(str_replace('_', ' ', $m))
                    };
                }
            } elseif ($s->due_amount > 0) {
                $paymentMethod = 'CREDIT';
            }

            $customerPhone = $s->walking_customer_phone;
            if (empty($customerPhone) && $s->customer) {
                $customerPhone = $s->customer->phone ?? $s->customer->mobile ?? '';
            }
            if ($customerPhone === 'xxxxx') {
                $customerPhone = '';
            }

            $formatted[] = [
                'id' => $s->id,
                'sale_id' => $s->sale_id,
                'bill_no' => $s->invoice_number,
                'customer_name' => $s->walking_customer_name ?? ($s->customer ? $s->customer->name : 'Walk-In'),
                'customer_phone' => $customerPhone ?? '',
                'subtotal' => (float)$s->subtotal,
                'discount' => (float)$s->discount_amount,
                'tax' => 0.0,
                'grand_total' => (float)$s->total_amount,
                'payment_method' => $paymentMethod,
                'split_payments' => $splitPayments,
                'items' => $items,
                'items_json' => json_encode($items),
                'created_at' => $s->created_at ? $s->created_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
                'is_synced' => 1,
                'sms_sent' => !empty($customerPhone) ? 1 : 0,
                'source' => $s->order_source ?? 'domain'
            ];
        }

        return response()->json([
            'status' => 'success',
            'date' => $date,
            'count' => count($formatted),
            'invoices' => $formatted
        ]);
    }
}
