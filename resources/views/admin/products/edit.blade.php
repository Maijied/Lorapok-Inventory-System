@extends('admin.master')
@section('title')
    Edit Product
@endsection
@section('body')

<div class="row mt-2">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-body">
                <form class="form" action="{{ route('product.update', $product->id) }}" method="POST">
                    @csrf
                    <h3>Edit Product Information</h3>
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label>Brand</label>
                            <select class="form-control brands" name="brand_id" id="brand_id">
                                <option value="">Select Brand</option>
                                @foreach ($brands as $brand)
                                 <option value="{{$brand->id}}" @if ($product->brand_id==$brand->id)
                                     selected
                                 @endif>{{$brand->name}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Category</label>
                            <select class="form-control categories" name="category_id" id="category_id">
                                <option value="">Select Category</option>
                                @foreach ($categories as $category)
                                 <option value="{{$category->id}}" @if ($product->category_id==$category->id)
                                    selected
                                @endif>{{$category->name}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Product Name</label>
                            <input type="text" class="form-control" name="product_name" id="product_name" value="{{$product->product_name}}"
                                placeholder="Enter Product Name">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Price</label>
                            <input type="text" class="form-control" name="price" value="{{$product->price}}"
                                id="price" placeholder="Enter Price BDT">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Quantity</label>
                            <input type="text" class="form-control" name="qty" value="{{$product->qty}}"
                                id="qty" placeholder="Enter Quantity">
                        </div>
                        <div class="form-group col-md-12">
                            <label>Details</label>
                            <textarea class="form-control" name="details" id="tinymce" cols="20" rows="5">{{$product->details}}</textarea>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <button type="submit" class="btn btn-info">Update Product</button>
                    </div>
                </form>
            </div>
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
@endsection