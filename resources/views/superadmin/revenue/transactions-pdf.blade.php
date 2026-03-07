<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Financial Transactions Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { margin: 0 0 8px; font-size: 20px; }
        .meta { margin-bottom: 12px; color: #4b5563; }
        .summary { margin: 10px 0 16px; }
        .summary td { padding: 6px 10px; border: 1px solid #e5e7eb; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; font-size: 11px; text-transform: uppercase; }
        .small { color: #6b7280; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Financial Transactions Report</h1>
    <div class="meta">
        Generated at: {{ $generatedAt->format('Y-m-d H:i:s') }}<br>
        Filters:
        Source={{ $filters['source'] ?? 'all' }},
        Status={{ $filters['status'] ?? 'all' }},
        Tenant ID={{ $filters['tenant_id'] ?? 'all' }},
        Date From={{ $filters['date_from'] ?? '-' }},
        Date To={{ $filters['date_to'] ?? '-' }},
        Search={{ $filters['search'] ?? '-' }}
    </div>

    <table class="summary">
        <tr>
            <td><strong>Total Rows</strong><br>{{ number_format((int) ($summary['total_rows'] ?? 0)) }}</td>
            <td><strong>Booking Revenue</strong><br>${{ number_format((float) ($summary['booking_revenue'] ?? 0), 2) }}</td>
            <td><strong>Subscription Revenue</strong><br>${{ number_format((float) ($summary['subscription_revenue'] ?? 0), 2) }}</td>
            <td><strong>Total Revenue</strong><br>${{ number_format((float) ($summary['total_revenue'] ?? 0), 2) }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Source</th>
                <th>Date</th>
                <th>Tenant</th>
                <th>User</th>
                <th>Status</th>
                <th>Method</th>
                <th>Amount</th>
                <th>Currency</th>
                <th>Reference</th>
                <th>Context</th>
                <th>Plan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->source }}</td>
                    <td>{{ $row->paid_at }}</td>
                    <td>
                        {{ $row->tenant_name ?: '-' }}<br>
                        <span class="small">{{ $row->tenant_slug ?: '-' }}</span>
                    </td>
                    <td>
                        {{ $row->user_name ?: '-' }}<br>
                        <span class="small">{{ $row->user_email ?: '-' }}</span>
                    </td>
                    <td>{{ $row->status }}</td>
                    <td>{{ $row->payment_method }}</td>
                    <td>{{ number_format((float) $row->amount, 2) }}</td>
                    <td>{{ strtoupper((string) $row->currency) }}</td>
                    <td>{{ $row->reference }}</td>
                    <td>{{ $row->context_reference }}</td>
                    <td>{{ $row->plan_name }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" style="text-align:center;">No records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

