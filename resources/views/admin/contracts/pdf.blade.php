<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __('contracts.pdf.document_title', ['number' => $contract->contract_number]) }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: {{ $direction === 'rtl' ? "'Segoe UI', Tahoma, Arial, sans-serif" : "'Segoe UI', Arial, sans-serif" }};
            color: #111827;
            font-size: 12px;
            direction: {{ $direction }};
            text-align: {{ $direction === 'rtl' ? 'right' : 'left' }};
            margin: 0;
        }
        .container { width: 100%; padding: 14px; }
        .header { margin-bottom: 16px; text-align: center; }
        .brand { margin-bottom: 12px; }
        .brand-logo {
            display: block;
            max-width: 110px;
            max-height: 70px;
            width: auto;
            height: auto;
            margin: 0 auto 8px;
            object-fit: contain;
        }
        .brand-name { font-size: 16px; font-weight: 700; margin-bottom: 6px; }
        .title { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
        .subtitle { color: #6B7280; font-size: 11px; }
        .section {
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            margin-bottom: 12px;
            break-inside: avoid;
        }
        .section-header {
            border-bottom: 1px solid #E5E7EB;
            padding: 8px 12px;
            font-weight: 600;
            background: #F9FAFB;
        }
        .section-body { padding: 12px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { width: 50%; vertical-align: top; padding: 4px 10px 4px 0; }
        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 5px 0; vertical-align: top; }
        .label { color: #6B7280; font-size: 11px; width: 42%; }
        .value { font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: {{ $direction === 'rtl' ? 'right' : 'left' }};
            background: #F9FAFB;
            color: #6B7280;
            font-size: 11px;
            letter-spacing: {{ $direction === 'rtl' ? '0' : '.03em' }};
            text-transform: {{ $direction === 'rtl' ? 'none' : 'uppercase' }};
        }
        th, td { padding: 7px 6px; border-bottom: 1px solid #E5E7EB; vertical-align: top; }
        .muted { color: #6B7280; }
        .small { font-size: 11px; }
        .totals { margin-top: 10px; }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 9999px;
            background: #E5E7EB;
            font-size: 11px;
        }
        .notes { white-space: pre-line; }
        .diagram-wrap { margin-bottom: 10px; }
        .damage-diagram-svg { width: 100%; height: auto; display: block; }
        .diagram-note { margin-top: 8px; color: #6B7280; font-size: 11px; }
    </style>
</head>
<body>
    @php
        $diagramTitle = __('contracts.pdf.sections.vehicle_damage_diagram');
        $currentDamageTitle = __('contracts.pdf.sections.current_car_damages');
        $diagramHint = __('contracts.pdf.diagram_hint');
        $diagramEmpty = __('contracts.pdf.empty.no_current_damages');
        $currentDamageEmpty = __('contracts.pdf.empty.no_current_damages');
        $diagramMarkers = $damageDiagram['markers'] ?? [];
        $drivers = collect([]);

        if ($contract->primaryDriver) {
            $drivers->push($contract->primaryDriver);
        }

        foreach ($contract->additionalDrivers as $driver) {
            $drivers->push($driver);
        }
    @endphp

    <div class="container">
        <div class="header">
            <div class="brand">
                @if(!empty($companyLogo))
                    <img src="{{ $companyLogo }}" alt="{{ $companyName }}" class="brand-logo" />
                @endif
                @if(!empty($companyName))
                    <div class="brand-name">{{ $companyName }}</div>
                @endif
            </div>
            <div class="title">{{ __('contracts.pdf.document_title', ['number' => $contract->contract_number]) }}</div>
            <div class="subtitle">{{ __('contracts.pdf.generated_at', ['datetime' => $generatedAt->format('Y-m-d H:i')]) }}</div>
        </div>

        <div class="section">
            <div class="section-header">{{ __('contracts.pdf.sections.contract_summary') }}</div>
            <div class="section-body">
                <table class="grid">
                    <tr>
                        <td>
                            <table class="meta-table">
                                <tr><td class="label">{{ __('contracts.pdf.fields.status') }}</td><td class="value">{{ $contract->status ?? '-' }}</td></tr>
                                <tr><td class="label">{{ __('contracts.pdf.fields.contract_date') }}</td><td class="value">{{ optional($contract->contract_date)->format('Y-m-d') ?? '-' }}</td></tr>
                                <tr><td class="label">{{ __('contracts.pdf.fields.branch') }}</td><td class="value">{{ $contract->branch?->name ?? '-' }}</td></tr>
                                <tr><td class="label">{{ __('contracts.pdf.fields.currency') }}</td><td class="value">{{ $contract->currency ?? '-' }}</td></tr>
                            </table>
                        </td>
                        <td>
                            <table class="meta-table">
                                <tr><td class="label">{{ __('contracts.pdf.fields.reservation') }}</td><td class="value">{{ $contract->reservation?->reservation_number ?? '-' }}</td></tr>
                                <tr><td class="label">{{ __('contracts.pdf.fields.start_date') }}</td><td class="value">{{ optional($contract->start_date)->format('Y-m-d') ?? '-' }}</td></tr>
                                <tr><td class="label">{{ __('contracts.pdf.fields.end_date') }}</td><td class="value">{{ optional($contract->end_date)->format('Y-m-d') ?? '-' }}</td></tr>
                                <tr><td class="label">{{ __('contracts.pdf.fields.total_amount') }}</td><td class="value">{{ number_format((float) ($contract->total_amount ?? 0), 2) }} {{ $contract->currency ?? '' }}</td></tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-header">{{ __('contracts.pdf.sections.renter_vehicle') }}</div>
            <div class="section-body">
                <table class="grid">
                    <tr>
                        <td>
                            <table class="meta-table">
                                <tr><td class="label">{{ __('contracts.pdf.fields.renter_name') }}</td><td class="value">{{ $contract->renter_name ?? '-' }}</td></tr>
                                <tr><td class="label">{{ __('contracts.pdf.fields.id_number') }}</td><td class="value">{{ $contract->renter_id_number ?? '-' }}</td></tr>
                                <tr><td class="label">{{ __('contracts.pdf.fields.phone') }}</td><td class="value">{{ $contract->renter_phone ?? '-' }}</td></tr>
                                <tr><td class="label">{{ __('contracts.pdf.fields.reservation_client') }}</td><td class="value">{{ $contract->reservation?->user?->name ?? '-' }}</td></tr>
                            </table>
                        </td>
                        <td>
                            <table class="meta-table">
                                <tr><td class="label">{{ __('contracts.pdf.fields.car_details') }}</td><td class="value">{{ $contract->car_details ?? '-' }}</td></tr>
                                <tr><td class="label">{{ __('contracts.pdf.fields.plate_number') }}</td><td class="value">{{ $contract->plate_number ?? '-' }}</td></tr>
                                <tr><td class="label">{{ __('contracts.pdf.fields.reservation_car') }}</td><td class="value">@if($contract->reservation?->car) {{ $contract->reservation->car->year }} {{ $contract->reservation->car->make }} {{ $contract->reservation->car->model }} @else - @endif</td></tr>
                                <tr><td class="label">{{ __('contracts.pdf.fields.license_plate') }}</td><td class="value">{{ $contract->reservation?->car?->license_plate ?? '-' }}</td></tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-header">{{ __('contracts.pdf.sections.drivers') }}</div>
            <div class="section-body">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('contracts.pdf.tables.role') }}</th>
                            <th>{{ __('contracts.pdf.tables.name') }}</th>
                            <th>{{ __('contracts.pdf.tables.phone') }}</th>
                            <th>{{ __('contracts.pdf.tables.identity') }}</th>
                            <th>{{ __('contracts.pdf.tables.license') }}</th>
                            <th>{{ __('contracts.pdf.tables.nationality') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($drivers as $driver)
                            <tr>
                                <td>{{ $driver->role === 'primary' ? __('contracts.pdf.roles.primary') : __('contracts.pdf.roles.additional') }}</td>
                                <td>{{ $driver->full_name ?? '-' }}</td>
                                <td>{{ $driver->phone ?? '-' }}</td>
                                <td>{{ $driver->identity_number ?? $driver->residency_number ?? '-' }}</td>
                                <td>{{ $driver->license_number ?? '-' }}</td>
                                <td>{{ $driver->nationality ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="muted">{{ __('contracts.pdf.empty.no_drivers') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-header">{{ $diagramTitle }}</div>
            <div class="section-body">
                <div class="diagram-wrap">
                    @if(!empty($damageDiagram['data_uri']))
                        <img src="{{ $damageDiagram['data_uri'] }}" alt="{{ $diagramTitle }}" class="damage-diagram-svg" />
                    @endif
                </div>

                <div class="diagram-note">
                    @if(!empty($diagramMarkers))
                        {{ $diagramHint }}
                    @else
                        {{ $diagramEmpty }}
                    @endif
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-header">{{ $currentDamageTitle }}</div>
            <div class="section-body">
                @if(!empty($currentDamageCases))
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ __('contracts.pdf.tables.zone') }}</th>
                                <th>{{ __('contracts.pdf.tables.view') }}</th>
                                <th>{{ __('contracts.pdf.tables.type') }}</th>
                                <th>{{ __('contracts.pdf.tables.severity') }}</th>
                                <th>{{ __('contracts.pdf.tables.qty') }}</th>
                                <th>{{ __('contracts.pdf.tables.notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($currentDamageCases as $index => $damage)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $damage['zone_label'] ?? '-' }}</td>
                                    <td>{{ $damage['view_side_label'] ?? '-' }}</td>
                                    <td>{{ $damage['damage_type_label'] ?? '-' }}</td>
                                    <td>{{ $damage['severity_label'] ?? '-' }}</td>
                                    <td>{{ $damage['quantity'] ?? 0 }}</td>
                                    <td>{{ $damage['notes'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="muted">{{ $currentDamageEmpty }}</div>
                @endif
            </div>
        </div>

        <div class="section">
            <div class="section-header">{{ __('contracts.pdf.sections.damage_reports') }}</div>
            <div class="section-body">
                @forelse($contract->damageReports as $report)
                    <div style="margin-bottom: 14px;">
                        <div style="margin-bottom: 6px;">
                            <span class="value">{{ $report->report_number }}</span>
                            <span class="badge">{{ $reportTypeLabels[$report->report_type] ?? $report->report_type }}</span>
                            <span class="badge">{{ $statusLabels[$report->status] ?? $report->status }}</span>
                            <span class="small muted">{{ optional($report->inspected_at)->format('Y-m-d H:i') ?? __('contracts.pdf.empty.no_date') }}</span>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('contracts.pdf.tables.zone') }}</th>
                                    <th>{{ __('contracts.pdf.tables.view') }}</th>
                                    <th>{{ __('contracts.pdf.tables.type') }}</th>
                                    <th>{{ __('contracts.pdf.tables.severity') }}</th>
                                    <th>{{ __('contracts.pdf.tables.qty') }}</th>
                                    <th>{{ __('contracts.pdf.tables.estimated_cost') }}</th>
                                    <th>{{ __('contracts.pdf.tables.notes') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($report->items as $index => $item)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $zoneLabels[$item->zone_code] ?? $item->zone_code }}</td>
                                        <td>{{ $viewSideLabels[$item->view_side] ?? $item->view_side }}</td>
                                        <td>{{ $damageTypeLabels[$item->damage_type] ?? $item->damage_type }}</td>
                                        <td>{{ $severityLabels[$item->severity] ?? $item->severity }}</td>
                                        <td>{{ (int) $item->quantity }}</td>
                                        <td>
                                            @if($item->estimated_cost !== null)
                                                {{ $currencySymbol }}{{ number_format((float) $item->estimated_cost, 2) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $item->notes ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="muted">{{ __('contracts.pdf.empty.no_damage_items') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        <div class="totals small muted">{{ __('contracts.pdf.damage_total_quantity', ['quantity' => (int) $report->items->sum('quantity')]) }}</div>
                    </div>
                @empty
                    <div class="muted">{{ __('contracts.pdf.empty.no_damage_reports') }}</div>
                @endforelse
            </div>
        </div>

        @if($contract->notes)
            <div class="section">
                <div class="section-header">{{ __('contracts.pdf.sections.notes') }}</div>
                <div class="section-body notes">{{ $contract->notes }}</div>
            </div>
        @endif
    </div>
</body>
</html>
