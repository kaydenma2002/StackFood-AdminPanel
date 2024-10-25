@extends('layouts.vendor.app')

@section('title', translate('messages.Order List'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">Today's Orders</h2>

    <div class="row">
        @forelse($orders as $order)
            <div class="col-md-4 mb-4">
                <div class="card h-100 bg-light">
                    <div class="card-body">
                        <h5 class="card-title">Order #{{ $order->id }}</h5>

                        <p class="card-text">
                            <strong>Status:</strong>
                            <span class="badge
                                @if($order->order_status === 'pending') bg-warning text-dark
                                @elseif($order->order_status === 'confirmed') bg-success text-white
                                @elseif($order->order_status === 'handover') bg-secondary text-white
                                @elseif($order->order_status === 'processing') bg-primary text-white
                                @elseif($order->order_status === 'delivered') bg-info text-white
                                @else bg-light text-dark @endif">
                                {{ ucfirst($order->order_status) }}
                            </span>
                        </p>



                        <!-- Food Items -->
                        <ul class="list-group">
                            @foreach($order->details as $detail)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <!-- Display the food image -->
                                        <img src="{{ asset('storage/product/' . $detail->food->image) }}"
                                            alt="{{ $detail->food ? $detail->food->name : 'Unknown' }}"
                                            class="img-thumbnail me-2" style="width: 70px; height: 70px;">

                                        <div>
                                            <strong>{{ $detail->food ? $detail->food->name : 'Unknown' }}</strong><br>
                                            <small class="text-muted">Unit Price: ${{ number_format($detail->price, 2) }}</small>
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <strong>${{ number_format($detail->price * $detail->quantity, 2) }}</strong><br>
                                        <small class="text-muted">Qty: {{ $detail->quantity }}</small>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="card-footer">
                        @if($order->order_status === 'pending')
                            <a href="{{ route('vendor.orders.updateStatus', ['id' => $order->id, 'status' => 'confirmed']) }}" class="btn btn-success btn-sm">Confirm</a>
                        @elseif($order->order_status === 'confirmed')
                            <a href="{{ route('vendor.orders.updateStatus', ['id' => $order->id, 'status' => 'handover']) }}" class="btn btn-secondary btn-sm">Handover</a>
                        @elseif($order->order_status === 'handover')
                            <a href="{{ route('vendor.orders.updateStatus', ['id' => $order->id, 'status' => 'processing']) }}" class="btn btn-primary btn-sm">Process</a>
                        @elseif($order->order_status === 'processing')
                            <a href="{{ route('vendor.orders.updateStatus', ['id' => $order->id, 'status' => 'delivered']) }}" class="btn btn-info btn-sm">Deliver</a>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info text-center">
                    No orders found.
                </div>
            </div>
        @endforelse



    </div>

    <!-- Pagination links -->
    <div class="d-flex justify-content-center mt-4">
        {{ $orders->links() }}
    </div>
</div>
@endsection
