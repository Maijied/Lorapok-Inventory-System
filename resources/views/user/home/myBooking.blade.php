@extends('user.master')
@section('title')
    My Bookings
@endsection

@section('body')
    <div class="row page-titles mt-4 mt-md-0">
        <div class="col-5 align-self-center">
            <h4 class="text-themecolor">My Bookings</h4>
        </div>
        <div class="col-7 align-self-center text-end">
            <div class="d-flex justify-content-end align-items-center">
                <ol class="breadcrumb justify-content-end">
                    <li class="breadcrumb-item"><a href="{{ url('/user/home') }}">Home</a></li>
                    <li class="breadcrumb-item active">My Bookings</li>
                </ol>

            </div>
        </div>
    </div>

    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <table class="table display table-striped border no-wrap">
                    <thead>
                        <tr class="text-center">
                            <th>Name</th>
                            <th>Location</th>
                            <th>Room Type</th>
                            <th>Check In Date</th>
                            <th>Check Out Date</th>
                            <th>Occupants</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookings as $booking)
                            <tr class="text-center">
                                <td>{{ $booking->hostel->name ?? null }}</td>
                                <td>{{ $booking->hostel->location ?? null }}</td>
                                <td>{{ $booking->room_type ?? null }}</td>
                                <td>{{ $booking->check_in }}</td>
                                <td>{{ $booking->check_out }}</td>
                                <td>{{ $booking->occupants }}</td>
                                <td>
                                    <span class=" @if ($booking->status == 'pending') text-danger @else text-success @endif">
                                        {{ $booking->status ?? null }}</span>
                                </td>

                            </tr>
                        @endforeach

                    </tbody>

                </table>
            </div>
        </div>
    </div>
@endsection
