<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Stock Movement Report</title>

    <style>
        @page {
            margin: 24px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #374151;
            background: #ffffff;
        }

        .header {
            padding-bottom: 14px;
            margin-bottom: 18px;
            text-align: center;
            border-bottom: 2px solid #8B7355;
        }

        .title {
            margin: 0;
            font-size: 20px;
            color: #3C2F23;
        }

        .subtitle {
            margin-top: 4px;
            color: #6B7280;
        }

        .summary {
            width: 100%;
            margin-bottom: 16px;
            border-collapse: collapse;
            background: #F6EFE6;
        }

        .summary td {
            padding: 8px 10px;
        }

        .filters {
            margin-bottom: 16px;
            padding: 10px;
            border: 1px solid #E5E7EB;
        }

        .filters strong {
            color: #3C2F23;
        }

        table.movements {
            width: 100%;
            border-collapse: collapse;
        }

        table.movements th {
            padding: 7px 6px;
            text-align: left;
            font-size: 8px;
            color: #ffffff;
            text-transform: uppercase;
            background: #8B7355;
        }

        table.movements td {
            padding: 7px 6px;
            vertical-align: top;
            border-bottom: 1px solid #E5E7EB;
        }

        table.movements tr:nth-child(even) {
            background: #F9FAFB;
        }

        .movement-in {
            color: #047857;
            font-weight: bold;
        }

        .movement-out {
            color: #B91C1C;
            font-weight: bold;
        }

        .muted {
            color: #6B7280;
        }

        .empty {
            padding: 36px 0;
            text-align: center;
            color: #6B7280;
        }

        .footer {
            padding-top: 12px;
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            color: #6B7280;
            border-top: 1px solid #E5E7EB;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1 class="title">Stock Movement Report</h1>

        <div class="subtitle">
            {{ $spaName }} • {{ $branchName }}
        </div>

        <div class="subtitle">
            Generated: {{ $generatedAt }}
        </div>
    </div>

    <table class="summary">
        <tr>
            <td>
                <strong>Total Movements:</strong>
                {{ $totalLogs }}
            </td>

            <td>
                <strong>Branch:</strong>
                {{ $branchName }}
            </td>

            <td>
                <strong>Generated:</strong>
                {{ $generatedAt }}
            </td>
        </tr>
    </table>

    @if(
        $filters['product'] ||
        $filters['movementType'] ||
        $filters['dateFrom'] ||
        $filters['dateTo']
    )
        <div class="filters">
            <strong>Applied Filters:</strong>

            @if($filters['product'])
                Product: {{ $filters['product'] }}
            @endif

            @if($filters['movementType'])
                &nbsp; | &nbsp;
                Movement:
                {{ ucwords(str_replace('_', ' ', $filters['movementType'])) }}
            @endif

            @if($filters['dateFrom'])
                &nbsp; | &nbsp;
                From: {{ $filters['dateFrom'] }}
            @endif

            @if($filters['dateTo'])
                &nbsp; | &nbsp;
                To: {{ $filters['dateTo'] }}
            @endif
        </div>
    @endif

    @if($logs->count() > 0)

        <table class="movements">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Movement</th>
                    <th>Qty</th>
                    <th>Before</th>
                    <th>After</th>
                    <th>Reason</th>
                    <th>By</th>
                </tr>
            </thead>

            <tbody>

                @foreach($logs as $log)
                    @php
                        $quantity = rtrim(
                            rtrim(number_format((float) $log->quantity, 3, '.', ''), '0'),
                            '.'
                        );

                        $before = rtrim(
                            rtrim(number_format((float) $log->balance_before, 3, '.', ''), '0'),
                            '.'
                        );

                        $after = rtrim(
                            rtrim(number_format((float) $log->balance_after, 3, '.', ''), '0'),
                            '.'
                        );
                    @endphp

                    <tr>

                        <td>
                            {{ $log->occurred_at?->format('M d, Y h:i A') ?? $log->created_at?->format('M d, Y h:i A') }}
                        </td>

                        <td>
                            <strong>
                                {{ $log->product?->name ?? 'Deleted Product' }}
                            </strong>

                            @if($log->product?->sku)
                                <br>
                                <span class="muted">
                                    {{ $log->product->sku }}
                                </span>
                            @endif
                        </td>

                        <td>
                            {{ ucwords(str_replace('_', ' ', $log->movement_type)) }}

                            <br>

                            <span class="{{ $log->direction === 'in' ? 'movement-in' : 'movement-out' }}">
                                {{ strtoupper($log->direction) }}
                            </span>
                        </td>

                        <td>
                            {{ $log->direction === 'in' ? '+' : '-' }}
                            {{ $quantity }}
                            {{ $log->unit }}
                        </td>

                        <td>
                            {{ $before }}
                            {{ $log->unit }}
                        </td>

                        <td>
                            {{ $after }}
                            {{ $log->unit }}
                        </td>

                        <td>
                            {{ $log->notes ?: '—' }}

                            @if($log->reference_type)
                                <br>
                                <span class="muted">
                                    Ref:
                                    {{ ucwords(str_replace('_', ' ', $log->reference_type)) }}

                                    @if($log->reference_id)
                                        #{{ $log->reference_id }}
                                    @endif
                                </span>
                            @endif
                        </td>

                        <td>
                            {{ $log->user?->name ?? $log->user?->full_name ?? 'System' }}
                        </td>

                    </tr>
                @endforeach

            </tbody>
        </table>

    @else

        <div class="empty">
            No stock movements were found for the selected filters.
        </div>

    @endif

    <div class="footer">
        <div>
            This report was automatically generated by the Levictas Spa System.
        </div>

        <div>
            &copy; {{ date('Y') }} {{ $spaName }}. All rights reserved.
        </div>
    </div>

</body>
</html>