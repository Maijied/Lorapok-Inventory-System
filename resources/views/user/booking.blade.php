@extends('user.master')

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

    <div class="row justify-content-center">
        @if (session('message'))
            <div class="alert alert-success" role="alert">
                {{ session('message') }}
            </div>
        @endif
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <form action="{{ route('booking.hostel.store') }}" method="post">
                        @csrf
                        <input type="hidden" name="hostel_id" value="{{ $hostel->id }}">
                        <input type="hidden" name="user_id" value="{{ $user->id }}">
                        <input type="hidden" name="status" value="pending">

                        <div class="col-md-12">
                            <div class="row">
                                <div class="col-md-3">
                                    <label for="check_in">Check-In Date *</label>
                                    <input class="form-control" type="date" name="check_in" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="check_out">Check-Out Date *</label>
                                    <input class="form-control" type="date" name="check_out" required>
                                </div>
                                <div class="col-md-2">
                                    <label for="room_type">Room Type</label>
                                    <select class="form-control" name="room_type" id="room_type">
                                        <option value="">Select Room Type</option>
                                        <option value="single">Single</option>
                                        <option value="double">Double</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="occupants">Number of Occupants *</label>
                                    <input class="form-control" type="number" name="occupants" min="1" required>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-outline-primary" style="margin-top:23px;">Book
                                        Now</button>
                                </div>
                            </div>
                        </div>
                    </form>

                </div>
                <div class="card-body">
                    <h5>{{ $hostel->name }}</h5>
                    <small>Location: {{ $hostel->location }}</small>
                    <p>{{ $hostel->description }}</p>
                    <p>Total Rooms: {{ $hostel->total_rooms }}</p>
                    <p>Available Rooms: {{ $hostel->available_rooms }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
