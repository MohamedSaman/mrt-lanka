<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Customer Ledger - {{ $customer->name }}</title>
    <style>
        @page {
            size: a4 portrait;
            margin: 12mm 12mm 16mm 12mm;
        }

        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #222;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }

        /* Header */
        .header-table {
            width: 100%;
            border-bottom: 2px solid #1a202c;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .header-table td {
            vertical-align: top;
        }

        .company-title {
            font-size: 18px;
            font-weight: bold;
            color: #111;
            letter-spacing: 1px;
            margin: 0 0 2px 0;
        }

        .company-tagline {
            font-size: 10px;
            color: #555;
            font-weight: bold;
            margin: 0 0 4px 0;
            letter-spacing: 0.5px;
        }

        .company-info {
            font-size: 9px;
            color: #444;
            margin: 0;
            line-height: 1.3;
        }

        .doc-title-box {
            text-align: right;
        }

        .doc-title {
            font-size: 16px;
            font-weight: bold;
            color: #c2410c; /* warm rust/orange theme matching Hardmen */
            margin: 0 0 4px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .doc-meta {
            font-size: 9px;
            color: #555;
            margin: 1px 0;
        }

        /* Info Section (Customer & Statement info) */
        .info-container {
            width: 100%;
            margin-bottom: 12px;
        }

        .info-container td {
            vertical-align: top;
            width: 50%;
        }

        .info-card {
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 8px 10px;
        }

        .info-card-right {
            margin-left: 6px;
        }

        .info-card-left {
            margin-right: 6px;
        }

        .card-header-label {
            font-size: 10px;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .meta-row {
            margin-bottom: 3px;
            font-size: 9.5px;
        }

        .meta-label {
            color: #64748b;
            font-weight: bold;
            width: 90px;
            display: inline-block;
        }

        .meta-value {
            color: #0f172a;
            font-weight: 500;
        }

        /* Summary Stats Cards */
        .summary-boxes {
            width: 100%;
            margin-bottom: 14px;
            border-collapse: separate;
            border-spacing: 6px 0;
        }

        .summary-box {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px 8px;
            text-align: center;
        }

        .summary-box.debit {
            background-color: #fef2f2;
            border-color: #fecaca;
        }

        .summary-box.credit {
            background-color: #f0fdf4;
            border-color: #bbf7d0;
        }

        .summary-box.balance {
            background-color: #fff7ed;
            border-color: #fed7aa;
        }

        .summary-title {
            font-size: 8.5px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .summary-amount {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
        }

        .summary-box.debit .summary-amount {
            color: #dc2626;
        }

        .summary-box.credit .summary-amount {
            color: #16a34a;
        }

        .summary-box.balance .summary-amount {
            color: #c2410c;
        }

        /* Transactions Table */
        .ledger-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 15px;
        }

        .ledger-table thead {
            display: table-header-group;
        }

        .ledger-table th {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8.5px;
            padding: 6px 6px;
            letter-spacing: 0.5px;
            border: 1px solid #1e293b;
        }

        .ledger-table td {
            padding: 5px 6px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .ledger-table tr {
            page-break-inside: avoid;
        }

        .ledger-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .row-bf {
            background-color: #fef3c7 !important;
            font-weight: bold;
        }

        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }

        .text-danger { color: #dc2626; }
        .text-success { color: #16a34a; }
        .text-info { color: #0284c7; }
        .text-warning { color: #d97706; }
        .text-muted { color: #94a3b8; }

        .fw-bold { font-weight: bold; }
        .badge {
            font-size: 8px;
            padding: 1px 4px;
            border-radius: 3px;
            display: inline-block;
        }

        .badge-dr {
            background-color: #fee2e2;
            color: #b91c1c;
            font-weight: bold;
        }

        .badge-cr {
            background-color: #dcfce7;
            color: #15803d;
            font-weight: bold;
        }

        /* Table Footer */
        .ledger-table tfoot td {
            background-color: #f1f5f9;
            font-weight: bold;
            border-top: 2px solid #334155;
            border-bottom: 2px solid #334155;
            padding: 7px 6px;
            font-size: 9.5px;
        }

        /* Signatures & Footer */
        .footer-table {
            width: 100%;
            margin-top: 25px;
            page-break-inside: avoid;
        }

        .footer-table td {
            width: 50%;
            vertical-align: top;
            text-align: center;
        }

        .sig-line {
            width: 180px;
            border-top: 1px dashed #64748b;
            margin: 35px auto 4px auto;
        }

        .sig-text {
            font-size: 8.5px;
            color: #475569;
            text-transform: uppercase;
            font-weight: bold;
        }

        .disclaimer {
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
            margin-top: 15px;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <table class="header-table">
        <tr>
            <td style="width: 58%;">
                <div class="company-title">HARDMEN (PVT) LTD</div>
                <div class="company-tagline">TOOLS WITH POWER</div>
                <div class="company-info">
                    421/2, Doolmala, Thihariya, Kalagedihena.<br>
                    <strong>Tel:</strong> (077) 9752950 &nbsp;|&nbsp; <strong>Email:</strong> Hardmenlanka@gmail.com
                </div>
            </td>
            <td style="width: 42%;" class="doc-title-box">
                <div class="doc-title">Customer Ledger</div>
                <div class="doc-meta"><strong>Date Generated:</strong> {{ now()->format('d M Y, h:i A') }}</div>
                <div class="doc-meta">
                    <strong>Period:</strong>
                    @if(!empty($startDate) && !empty($endDate))
                        {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} &mdash; {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
                    @elseif(!empty($startDate))
                        From {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} to Present
                    @elseif(!empty($endDate))
                        Up to {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
                    @else
                        All Transactions (Full Statement)
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <!-- Info Cards Section -->
    <table class="info-container">
        <tr>
            <td style="padding-right: 4px;">
                <div class="info-card info-card-left">
                    <div class="card-header-label">Customer Information</div>
                    <div class="meta-row">
                        <span class="meta-label">Customer:</span>
                        <span class="meta-value fw-bold">{{ $customer->name }}</span>
                    </div>
                    @if($customer->business_name)
                    <div class="meta-row">
                        <span class="meta-label">Business:</span>
                        <span class="meta-value">{{ $customer->business_name }}</span>
                    </div>
                    @endif
                    <div class="meta-row">
                        <span class="meta-label">Phone:</span>
                        <span class="meta-value">{{ $customer->phone ?: '-' }}</span>
                    </div>
                    @if($customer->email)
                    <div class="meta-row">
                        <span class="meta-label">Email:</span>
                        <span class="meta-value">{{ $customer->email }}</span>
                    </div>
                    @endif
                    <div class="meta-row">
                        <span class="meta-label">Address:</span>
                        <span class="meta-value">{{ $customer->address ?: '-' }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Type:</span>
                        <span class="meta-value">{{ ucfirst($customer->type ?? 'Customer') }}</span>
                    </div>
                </div>
            </td>
            <td style="padding-left: 4px;">
                <div class="info-card info-card-right">
                    <div class="card-header-label">Account Status</div>
                    <div class="meta-row">
                        <span class="meta-label">Initial Opening:</span>
                        <span class="meta-value">Rs. {{ number_format($customer->opening_balance ?? 0, 2) }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Total Due:</span>
                        <span class="meta-value fw-bold text-danger">Rs. {{ number_format($customer->total_due ?? 0, 2) }}</span>
                    </div>
                    @if(($customer->overpaid_amount ?? 0) > 0)
                    <div class="meta-row">
                        <span class="meta-label">Overpaid Amount:</span>
                        <span class="meta-value text-success">Rs. {{ number_format($customer->overpaid_amount, 2) }}</span>
                    </div>
                    @endif
                    <div class="meta-row">
                        <span class="meta-label">Total Entries:</span>
                        <span class="meta-value">{{ count($ledger) }} record(s)</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Filter Status:</span>
                        <span class="meta-value">
                            @if(!empty($startDate) || !empty($endDate))
                                <span style="color: #c2410c; font-weight: bold;">Filtered by Date</span>
                            @else
                                Complete History
                            @endif
                        </span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Summary Box Counters -->
    <table class="summary-boxes">
        <tr>
            <td class="summary-box debit" style="width: 33.3%;">
                <div class="summary-title">Total Debits (Sales / Invoices)</div>
                <div class="summary-amount">Rs. {{ number_format($totalDebit, 2) }}</div>
            </td>
            <td class="summary-box credit" style="width: 33.3%;">
                <div class="summary-title">Total Credits (Payments / Returns)</div>
                <div class="summary-amount">Rs. {{ number_format($totalCredit, 2) }}</div>
            </td>
            <td class="summary-box balance" style="width: 33.3%;">
                <div class="summary-title">Net Ending Balance</div>
                <div class="summary-amount">
                    Rs. {{ number_format(abs($closingBalance), 2) }}
                    @if($closingBalance > 0.005)
                        <span class="badge badge-dr">Dr</span>
                    @elseif($closingBalance < -0.005)
                        <span class="badge badge-cr">Cr</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <!-- Ledger Table -->
    <table class="ledger-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 4%;">#</th>
                <th class="text-left" style="width: 17%;">Date & Time</th>
                <th class="text-left" style="width: 29%;">Description</th>
                <th class="text-left" style="width: 16%;">Reference</th>
                <th class="text-right" style="width: 11%;">Debit (Rs.)</th>
                <th class="text-right" style="width: 11%;">Credit (Rs.)</th>
                <th class="text-right" style="width: 12%;">Balance (Rs.)</th>
            </tr>
        </thead>
        <tbody>
            @php $currentBal = 0; @endphp
            @forelse($ledger as $index => $entry)
                @php
                    $currentBal += ($entry['debit'] - $entry['credit']);
                    $isBF = isset($entry['is_bf']) && $entry['is_bf'];
                @endphp
                <tr class="{{ $isBF ? 'row-bf' : '' }}">
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-left">{{ $entry['date'] }}</td>
                    <td class="text-left">
                        @if($isBF)
                            <strong>{{ $entry['description'] }}</strong>
                        @elseif($entry['type'] === 'opening')
                            <span class="text-info">{{ $entry['description'] }}</span>
                        @elseif($entry['type'] === 'sale_return')
                            <span class="text-warning">{{ $entry['description'] }}</span>
                        @elseif($entry['type'] === 'sale')
                            <span class="text-danger">{{ $entry['description'] }}</span>
                        @elseif($entry['type'] === 'payment')
                            <span class="text-success">{{ $entry['description'] }}</span>
                        @elseif($entry['type'] === 'product_return')
                            <span class="text-warning">{{ $entry['description'] }}</span>
                        @elseif($entry['type'] === 'cheque_return')
                            <span class="text-danger fw-bold">{{ $entry['description'] }}</span>
                        @else
                            {{ $entry['description'] }}
                        @endif
                    </td>
                    <td class="text-left">{{ $entry['reference'] ?? '-' }}</td>
                    <td class="text-right">
                        @if($entry['debit'] > 0)
                            <span class="text-danger fw-bold">{{ number_format($entry['debit'], 2) }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($entry['credit'] > 0)
                            <span class="text-success fw-bold">{{ number_format($entry['credit'], 2) }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td class="text-right fw-bold {{ $currentBal > 0.005 ? 'text-danger' : ($currentBal < -0.005 ? 'text-success' : '') }}">
                        {{ number_format(abs($currentBal), 2) }}
                        @if($currentBal > 0.005)
                            <span class="badge badge-dr">Dr</span>
                        @elseif($currentBal < -0.005)
                            <span class="badge badge-cr">Cr</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted" style="padding: 20px;">
                        No ledger transactions found for the selected period.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if(count($ledger) > 0)
        <tfoot>
            <tr>
                <td colspan="4" class="text-right fw-bold">TOTALS:</td>
                <td class="text-right text-danger">{{ number_format($totalDebit, 2) }}</td>
                <td class="text-right text-success">{{ number_format($totalCredit, 2) }}</td>
                <td class="text-right {{ $closingBalance > 0.005 ? 'text-danger' : 'text-success' }}">
                    {{ number_format(abs($closingBalance), 2) }}
                    @if($closingBalance > 0.005)
                        <span class="badge badge-dr">Dr</span>
                    @elseif($closingBalance < -0.005)
                        <span class="badge badge-cr">Cr</span>
                    @endif
                </td>
            </tr>
        </tfoot>
        @endif
    </table>

    <!-- Signatures -->
    <table class="footer-table">
        <tr>
            <td>
                <div class="sig-line"></div>
                <div class="sig-text">Prepared By</div>
            </td>
            <td>
                <div class="sig-line"></div>
                <div class="sig-text">Customer / Authorized Signature</div>
            </td>
        </tr>
    </table>

    <!-- Disclaimer -->
    <div class="disclaimer">
        This is a computer-generated account statement issued by Hardmen (Pvt) Ltd.<br>
        Please inspect all transactions. If you notice any discrepancy, please notify our accounts department immediately.
    </div>

</body>
</html>
