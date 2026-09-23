@extends('admin.master')
@section('title')
    Manage Products
@endsection
@section('body')
    <div class="row mt-2">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <form class="form" action="{{ route('product.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <h3>Product information</h3>
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label>Brand</label>
                                <select class="form-control brands" name="brand_id" id="brand_id">
                                    <option value="">Select Brand</option>
                                    @foreach ($brands as $brand)
                                     <option value="{{$brand->id}}">{{$brand->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Category</label>
                                <select class="form-control categories" name="category_id" id="category_id">
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $category)
                                     <option value="{{$category->id}}">{{$category->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Product Name</label>
                                <input type="text" class="form-control" name="product_name" id="product_name"
                                    placeholder="Enter Product Name">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Price</label>
                                <input type="text" class="form-control" name="price"
                                    id="price" placeholder="Enter Price BDT">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Quantity</label>
                                <input type="text" class="form-control" name="qty"
                                    id="qty" placeholder="Enter Quantity">
                            </div>
                            <div class="form-group col-md-12">
                                <label>Details</label>
                                <textarea class="form-control" name="details" id="tinymce" cols="20" rows="5"></textarea>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <button type="submit" class="btn btn-info">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <table id="productTable"  class="table">
                    <thead class="thead-dark">
                        <tr>
                            <th class="text-center">Brand</th>
                            <th class="text-center">Category</th>
                            <th class="text-center">Name</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-center">Price</th>
                            <th class="text-center">Details</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr>
                                <td class="text-center">{{ $product->brand->name ?? null }}</td>
                                <td class="text-center">{{ $product->category->name ?? null }}</td>
                                <td class="text-center">{{ $product->product_name ?? null }}</td>
                                <td class="text-center">{{ $product->qty ?? null }}</td>
                                <td class="text-center">{{ $product->price ?? null }}</td>
                                {{-- <td>{{ $product->details ?? null }}</td> --}}
                                <td class="text-center">
                                    @if($product->details)
                                        <!-- Display first 5 words -->
                                        <span id="short-details-{{ $product->id }}">
                                            {{ Str::limit(strip_tags($product->details), 20) }}
                                        </span>
                                    
                                        <span id="full-details-{{ $product->id }}" style="display: none;">
                                            {!! $product->details !!}
                                        </span>
                                
                                        <!-- Show More/Show Less Button -->
                                        <button onclick="toggleDetails({{ $product->id }})" id="details-toggle-btn-{{ $product->id }}" class="btn btn-link">Show More</button>
                                    @else
                                        <span>No details available</span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    <div class="d-flex">
                                  
                                        <a href="{{ route('product.edit', ['id' => $product->id]) }}"
                                            class="btn btn-outline-primary btn-sm"><i><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil" viewBox="0 0 16 16">
                                                <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/>
                                              </svg></i> Edit</a>
                                     
                                        <form action="{{ route('product.delete', ['id' => $product->id]) }}" method="POST" id="deleteForm">
                                               @csrf
                                            <button type="button" class="btn btn-outline-danger btn-sm mx-1" data-toggle="tooltip"
                                                title='Delete' onclick="showConfirmation()">
                                                <i><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                                                    <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                                                    <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                                                  </svg></i>Delete
                                            </button>
                                        </form>                          
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        $(document).ready(function() {
             $('.brands').select2();
        });
    </script>
    <script>
        $(document).ready(function() {
             $('.categories').select2();
        });
    </script>
    <script>
        function toggleDetails(productId) {
            const shortDetails = document.getElementById(`short-details-${productId}`);
            const fullDetails = document.getElementById(`full-details-${productId}`);
            const toggleBtn = document.getElementById(`details-toggle-btn-${productId}`);
    
            if (shortDetails.style.display === 'none') {
                // Show short details and hide full details
                shortDetails.style.display = 'inline';
                fullDetails.style.display = 'none';
                toggleBtn.textContent = 'Show More';
            } else {
                // Show full details and hide short details
                shortDetails.style.display = 'none';
                fullDetails.style.display = 'inline';
                toggleBtn.textContent = 'Show Less';
            }
        }
    </script>
<script>
    $(document).ready(function() {
        $('#productTable').DataTable();
    });
</script>
<script>
    function showConfirmation() {
        Swal.fire({
            title: 'Are you sure?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteForm').submit();
            }
        });
    }
</script>
@endsection
