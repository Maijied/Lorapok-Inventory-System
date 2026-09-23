@extends('user.master')
@section('title')
    User Dashboard
@endsection

@section('body')
    <div class="row page-titles mt-4 mt-md-0">
        <div class="col-5 align-self-center">
            <h4 class="text-themecolor">User Dashboard</h4>
        </div>
        <div class="col-7 align-self-center text-end">
            <div class="d-flex justify-content-end align-items-center">
                <ol class="breadcrumb justify-content-end">
                    <li class="breadcrumb-item"><a href="{{ url('/user/home') }}">Home</a></li>
                    <li class="breadcrumb-item active">User Dashboard</li>
                </ol>

            </div>
        </div>
    </div>

@endsection
