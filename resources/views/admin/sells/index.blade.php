@extends('admin.master')
@section('title')
    Sell Products
@endsection
@section('body')
    <div class="row mt-2">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <form class="form" action="{{ route('sell.store') }}" method="POST">
                        @csrf
                        <h3>Selling Information</h3>
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label>Customer Name</label>
                                <input type="text" class="form-control" name="customer_name" placeholder="Enter Customer Name">
                            </div>
                            <div class="form-group col-md-4">
                                <label>Customer Phone</label>
                                <input type="text" class="form-control" name="customer_phone" placeholder="Enter Customer Phone" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Customer Address</label>
                                <input type="text" class="form-control" name="customer_address" placeholder="Enter Customer Address">
                            </div>
                        </div>

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
                                <tr>
                                    <td class="serial">1</td>
                                    <td>
                                        <select class="form-control product-select" name="product_id[]">
                                            <option value="">Select Product</option>
                                            @foreach ($products as $product)
                                            <option value="{{ $product->id }}"
                                                data-price="{{ $product->price }}"
                                                data-qty="{{ $product->qty }}">
                                            {{ $product->product_name ?? null }} -
                                            ({{ $product->category->name ?? null }} - {{ $product->brand->name ?? null }})(Stock: {{ $product->qty }})
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
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="form-group">
                            <label>Grand Total</label>
                            <input type="text" class="form-control" name="grand_total" id="grand_total">
                        </div>

                        <button type="submit" class="btn btn-info">Sell Products</button>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <script>
        $(document).ready(function () {
            // Initialize select2 for the product dropdown
            $('.product-select').select2();

            // Update serial numbers
            function updateSerialNumbers() {
                $('#productTable tbody tr').each(function (index) {
                    $(this).find('.serial').text(index + 1);
                });
            }

            // Recalculate totals and validate stock
            function recalculateRow(row) {
                const selectedProduct = row.find('.product-select option:selected');
                const availableQty = parseInt(selectedProduct.data('qty') || 0); // Stock quantity
                const selectedQty = parseInt(row.find('.qty').val() || 0); // Entered quantity
                const price = parseFloat(selectedProduct.data('price') || 0); // Price of product
                const total = price * selectedQty; // Calculate total

                // Update total field
                row.find('.total').val(total.toFixed(2));

                // Highlight error if quantity exceeds stock
                if (selectedQty > availableQty) {
                    row.find('.qty').addClass('is-invalid');
                    row.find('.stock-error').show();
                } else {
                    row.find('.qty').removeClass('is-invalid');
                    row.find('.stock-error').hide();
                }

                calculateGrandTotal(); // Update grand total
            }

            // Calculate grand total
            function calculateGrandTotal() {
                let grandTotal = 0;
                $('#productTable tbody tr').each(function () {
                    const total = parseFloat($(this).find('.total').val()) || 0;
                    grandTotal += total;
                });
                $('#grand_total').val(grandTotal.toFixed(2));
            }

            // Event: On quantity or product change
            $(document).on('input', '.qty', function () {
                const row = $(this).closest('tr');
                recalculateRow(row);
            });

            $(document).on('change', '.product-select', function () {
                const row = $(this).closest('tr');
                recalculateRow(row);
            });

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
                                    {{ $product->product_name ?? null }} -
                                    ({{ $product->category->name ?? null }} - {{ $product->brand->name ?? null }})(Stock: {{ $product->qty }})
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
                        <button type="button" class="btn btn-success add-row m-1">Add</button>
                        <button type="button" class="btn btn-danger remove-row">Remove</button>
                    </td>
                </tr>`;
                $('#productTable tbody').append(newRow);
                $('.product-select').select2(); // Reinitialize select2 for new rows
                updateSerialNumbers();
            });

            // Remove row
            $(document).on('click', '.remove-row', function () {
                $(this).closest('tr').remove();
                updateSerialNumbers();
                calculateGrandTotal();
            });

            updateSerialNumbers(); // Initialize serial numbers
        });
    </script>
@endsection
