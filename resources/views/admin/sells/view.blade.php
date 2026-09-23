@extends('admin.master')

@section('title', 'Sell Details')

@section('body')
    <div class="row mt-4">
        <div class="col-lg-8 offset-lg-2">
            <div class="card">
                <div class="card-header">
                    <h3 class="text-center">Sell Details</h3>
                </div>
                <div class="card-body">
                    <h5 class="mb-3">Customer Information</h5>
                    <table class="table table-bordered">
                        <tr>
                            <th>Invoice No:</th>
                            <td>{{ $sell->invoice }}</td>
                        </tr>
                        <tr>
                            <th>Customer Name</th>
                            <td>{{ $sell->customer_name }}</td>
                        </tr>
                        <tr>
                            <th>Customer Phone</th>
                            <td>{{ $sell->customer_phone }}</td>
                        </tr>
                        <tr>
                            <th>Customer Address</th>
                            <td>{{ $sell->customer_address }}</td>
                        </tr>
                        <tr>
                            <th>Selling Date</th>
                            <td>{{ $sell->updated_at->format('d/m/Y') ?? $sell->created_at->format('d/m/Y') }}</td>
                        </tr>
                    </table>

                    <h5 class="mb-3">Product Information</h5>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product Name</th>
                                <th>IMEI</th>
                                <th>Quantity</th>
                                <th>Selling Price (Each)</th>
                                <th>Total Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sell->sellDetails as $index => $details)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $details->product->product_name ?? 'N/A' }}</td>
                                    <td>{{ $details->imei ?? 'N/A' }}</td>
                                    <td>{{ $details->quantity }}</td>
                                    <td>{{ number_format($details->selling_price / $details->quantity, 2) }} BDT</td>
                                    <td>{{ number_format($details->selling_price, 2) }} BDT</td>
                                </tr>
                            @endforeach
                            <tr>
                                <th class="text-end" colspan="4">Grand Total:</th>
                                <td>{{ number_format($sell->grand_total, 2) }} BDT</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="text-center mt-3">
                        <a href="{{ route('sell.edit', ['id' => $sell->id]) }}" class="btn btn-secondary">Edit</a>
                        <a href="{{ route('all.sell.index') }}" class="btn btn-secondary">Back to List</a>
                        <a href="{{ route('sell.invoice', ['id' => $sell->id]) }}" class="btn btn-danger" target="_blank"><i><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer" viewBox="0 0 16 16">
                            <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1"/>
                            <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1"/>
                          </svg></i> Print</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection