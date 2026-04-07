@extends('layouts.ecommerce')

@section('title', 'Client Debts')

@section('content')
    <section class="card">
        <div class="actions" style="justify-content: space-between;">
            <div>
                <h1 class="headline" style="margin: 0;">Client Debts</h1>
                <p class="subtle" style="margin: 6px 0 0;">Clients with remaining debt from saved payments.</p>
            </div>
        </div>

        <div class="actions" style="margin-top: 12px;">
            <a href="{{ route('client-depts.index') }}" class="btn {{ request()->routeIs('client-depts.index') ? 'btn-primary' : 'btn-muted' }}">Client Debts</a>
            <a href="{{ route('client-depts.history') }}" class="btn {{ request()->routeIs('client-depts.history') ? 'btn-primary' : 'btn-muted' }}">Debt History</a>
        </div>

        <form method="GET" action="{{ route('client-depts.index') }}" class="actions" style="margin-top: 12px;">
            <div style="min-width: 260px; flex: 1;">
                <label for="q">Search client / phone / invoice</label>
                <input id="q" type="text" name="q" value="{{ $q ?? '' }}" placeholder="Client name, phone, invoice no...">
            </div>
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="{{ route('client-depts.index') }}" class="btn btn-muted">Reset</a>
        </form>
    </section>

    @if(!empty($errorMessage))
        <section class="card">
            <div class="flash error">{{ $errorMessage }}</div>
        </section>
    @endif

    <section class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Invoice No</th>
                    <th>Date</th>
                    <th>Client No</th>
                    <th>Client Name</th>
                    <th>Phone</th>
                    <th style="text-align: right;">Amount</th>
                    <th style="text-align: right;">Recieve</th>
                    <th style="text-align: right;">Debt</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>#{{ $row->invoice_no }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($row->invoice_date)->format('Y-m-d') }}</td>
                        <td>{{ $row->client_no }}</td>
                        <td>{{ $row->client_name }}</td>
                        <td>{{ $row->phone ?: 'N/A' }}</td>
                        <td style="text-align: right;">${{ number_format((float) ($row->amount ?? 0), 2) }}</td>
                        <td style="text-align: right;">${{ number_format((float) ($row->recieve_amount ?? 0), 2) }}</td>
                        <td style="text-align: right; color: #a32222; font-weight: 700;">
                            ${{ number_format((float) ($row->debt_amount ?? 0), 2) }}
                        </td>
                        <td><span class="chip">{{ $row->invoice_status ?? 'N/A' }}</span></td>
                        <td>
                            <a href="{{ route('client-depts.detail', ['invoiceNo' => (int) $row->invoice_no]) }}" class="btn btn-muted">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="subtle">No client debts found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($rows, 'links'))
            <div style="margin-top: 12px;">
                {{ $rows->links('pagination.orbit') }}
            </div>
        @endif
    </section>
@endsection
