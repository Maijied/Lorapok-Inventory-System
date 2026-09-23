@extends('admin.master')
@section('title')
    Edit Brands
@endsection
@section('body')

<div class="row mt-2">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-body">
                <form class="form" action="{{ route('brand.update', $brand->id) }}" method="POST">
                    @csrf
                    <h3>Edit Brand Information</h3>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>Brand Name</label>
                            <input type="text" class="form-control" name="name" id="name"
                                value="{{ $brand->name }}">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <button type="submit" class="btn btn-info">Update Brand</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection