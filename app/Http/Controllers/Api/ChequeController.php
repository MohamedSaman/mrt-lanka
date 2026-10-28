<?php

namespace App\Http\Controllers\Api;

use App\Models\Cheque;
use Illuminate\Http\Request;

class ChequeController extends ApiController
{
    /**
     * List cheques with optional search and pagination
     */
    public function index(Request $request)
    {
        $query = Cheque::with(['customer']);

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('cheque_number', 'like', "%{$search}%")
                  ->orWhere('bank_name', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Allow filtering by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        $paginator = $query->orderBy('cheque_date', 'desc')->paginate(50);

        // Transform items to simple structure expected by mobile app
        $items = $paginator->items();

        $statusMap = [
            'complete' => 'cleared',
            'pending' => 'pending',
            'return' => 'bounced',
            'cancelled' => 'cancelled',
        ];

        $mapped = array_map(function ($chq) use ($statusMap) {
            // ensure cheque_date is a string in YYYY-MM-DD format
            $date = null;
            if (!empty($chq->cheque_date)) {
                try {
                    $date = date('Y-m-d', strtotime($chq->cheque_date));
                } catch (\Throwable $e) {
                    $date = (string) $chq->cheque_date;
                }
            }

            return [
                'id' => $chq->id,
                'cheque_number' => $chq->cheque_number,
                'bank_name' => $chq->bank_name,
                'cheque_amount' => (float) $chq->cheque_amount,
                'amount' => (float) $chq->cheque_amount,
                'cheque_date' => $date,
                'issue_date' => $date,
                'due_date' => null,
                'status' => $statusMap[$chq->status] ?? $chq->status,
                'party_name' => $chq->customer?->name,
                'party_type' => 'customer',
                'notes' => null,
            ];
        }, $items);

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'count' => $paginator->total(),
            'next' => $paginator->nextPageUrl(),
            'previous' => $paginator->previousPageUrl(),
            'results' => $mapped,
        ]);
    }
}
