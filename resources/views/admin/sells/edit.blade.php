@extends('admin.master')
@section('title', 'Edit Sell')
@section('body')

<div class="card">
    <div class="card-body">
        <form action="{{ route('sell.update', $sell->id) }}" method="POST">
            @csrf
            <h3>Edit Selling Information</h3>

            <!-- Customer Details -->
            <div class="row">
                <div class="form-group col-md-4">
                    <label>Customer Name</label>
                    <input type="text" class="form-control" name="customer_name" value="{{ $sell->customer_name }}">
                </div>
                <div class="form-group col-md-4">
                    <label>Customer Phone</label>
                    <input type="text" class="form-control" name="customer_phone" value="{{ $sell->customer_phone }}" required>
                </div>
                <div class="form-group col-md-4">
                    <label>Customer Address</label>
                    <input type="text" class="form-control" name="customer_address" value="{{ $sell->customer_address }}">
                </div>
            </div>

            <!-- Products -->
            <h4>Products</h4>
            <table class="table" id="productTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>IMEI</th>
                        <th>Quantity</th>
                        <th>Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sell->sellDetails as $key => $details)
                    <tr>
                        <td class="serial">{{ $key + 1 }}</td>
                        <td>
                            <select class="form-control product-select" name="product_id[]">
                                @if ($products)
                                    @foreach ($products as $product)
                                    <option value="{{ $product->id }}" data-price="{{ $product->price }}" data-qty="{{ $product->qty }}" {{ $details->product_id == $product->id ? 'selected' : '' }}>
                                        {{ $product->product_name }} - ({{ $product->category->name ?? null}} - {{$product->brand->name ?? null}})(Stock: {{$product->qty}})
                                    </option>
                                    @endforeach
                                    @else
                                    <option>
                                      no products available
                                    </option>
                                @endif
                            </select>
                        </td>
                        <td>
                            <input type="text" class="form-control" name="imei[]" value="{{ $details->imei }}">
                        </td>
                        <td>
                            <input type="number" class="form-control qty" name="qty[]" value="{{ $details->quantity }}">
                        </td>
                        <td>
                            <input type="text" class="form-control total" name="total[]" value="{{ $details->selling_price }}">
                        </td>
                        <td>
                            <button type="button" class="btn btn-success add-row">Add</button>
                            <button type="button" class="btn btn-danger remove-row">Remove</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="form-group">
                <label>Grand Total</label>
                <input type="text" class="form-control" name="grand_total" id="grand_total" value="{{$sell->grand_total}}">
            </div>
            <button type="submit" class="btn btn-success">Update Sell</button>
        </form>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Initialize select2 for the product dropdown
        $('.product-select').select2();

        function updateSerialNumbers() {
            $('#productTable tbody tr').each(function (index) {
                $(this).find('.serial').text(index + 1);
            });
        }

        $(document).on('change', '.qty, .product-select', function () {
            const row = $(this).closest('tr');
            const selectedProduct = row.find('.product-select option:selected');
            const availableQty = parseInt(selectedProduct.data('qty') || 0); // Use qty from products table
            const selectedQty = parseInt(row.find('.qty').val() || 0);
            const price = row.find('.product-select option:selected').data('price') || 0;
            const qty = row.find('.qty').val() || 0;
            const total = price * qty;
            row.find('.total').val(total.toFixed(2));
            calculateGrandTotal();

            // Highlight the field if the quantity exceeds stock
            if (selectedQty > availableQty) {
                row.find('.qty').addClass('is-invalid');
                row.find('.stock-error').show();
            } else {
                row.find('.qty').removeClass('is-invalid');
                row.find('.stock-error').hide();
            }
        });

        // When the total price is manually changed in a specific row
        $(document).on('input', '.total', function () {
            const row = $(this).closest('tr');
            const total = parseFloat(row.find('.total').val()) || 0; // Get total of the specific row
            row.find('.total').val(total.toFixed(2)); // Set formatted total value
            calculateGrandTotal(); // Recalculate the grand total
        });

        function calculateGrandTotal() {
            let grandTotal = 0;
            $('#productTable tbody tr').each(function () {
                const total = parseFloat($(this).find('.total').val()) || 0; // Get the total of each row
                grandTotal += total; // Add it to the grand total
            });
            $('#grand_total').val(grandTotal.toFixed(2)); // Update the grand total
        }

        // Add new row
        $(document).on('click', '.add-row', function () {
            const newRow = `
            <tr>
                <td class="serial"></td>
                <td>
                    <select class="form-control product-select" name="product_id[]">
                        <option value="">Select Product</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" 
                                    data-price="{{ $product->price }}" 
                                    data-qty="{{ $product->qty }}">
                                {{ $product->product_name }} - ({{ $product->category->name ?? null}} - {{$product->brand->name ?? null}})(Stock: {{$product->qty}})
                            </option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <input type="text" class="form-control" name="imei[]" placeholder="IMEI Number">
                 </td>
                <td>
                    <input type="number" class="form-control qty" name="qty[]" placeholder="Enter Quantity" min="1">
                    <small class="text-danger stock-error" style="display:none;">Exceeds available stock</small>
                </td>
                <td>
                    <input type="text" class="form-control total" name="total[]" placeholder="Total Price BDT">
                </td>
                <td>
                    <button type="button" class="btn btn-success add-row">Add</button>
                    <button type="button" class="btn btn-danger remove-row">Remove</button>
                </td>
            </tr>`;
            $('#productTable tbody').append(newRow);
            $('.product-select').select2(); // Reinitialize select2
            updateSerialNumbers();
        });

        // Remove row
        $(document).on('click', '.remove-row', function () {
            $(this).closest('tr').remove();
            updateSerialNumbers();
            calculateGrandTotal();
        });

        // Update serial numbers
        function updateSerialNumbers() {
            $('#productTable tbody tr').each(function (index) {
                $(this).find('.serial').text(index + 1);
            });
        }

        updateSerialNumbers();
    });
</script>
@endsection