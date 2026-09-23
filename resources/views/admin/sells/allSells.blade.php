@extends('admin.master')
@section('title')
    Sell Products
@endsection
@section('body')
    <div class="row mt-2">
    <div class="col-lg-12">
        <div class="card">
            <div class="text-center" style="background-color: #fafafafa"><h3>All Sells Information</h3></div>
            <div class="card-header">
                <table id="sellsTable"  class="table">
                    <thead class="thead-dark">
                        <tr>
                            <th class="text-center">Purchase Date</th>
                            <th class="text-center">Invoice</th>
                            <th class="text-center">Name</th>
                            <th class="text-center">Phone</th>
                            <th class="text-center">Address</th>
                            {{-- <th class="text-center">Product</th> --}}
                            <th class="text-center">Cost</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sells as $sell)
                            <tr>
                                <td class="text-center">{{ $sell->updated_at->format('d/m/Y') ?? $sell->created_at->format('d/m/Y') }}</td>
                                <td class="text-center">{{ $sell->invoice ?? null }}</td>
                                <td class="text-center">{{ $sell->customer_name ?? null }}</td>
                                <td class="text-center">{{ $sell->customer_phone ?? null }}</td>
                                <td class="text-center">{{ $sell->customer_address ?? null }}</td>
                                {{-- <td class="text-center">@foreach ($sell->sellDetails as $details)
                                    <u> {{ $details->product->product_name ?? null }} <u> <br>
                                @endforeach</td> --}}
                                <td class="text-center">{{ $sell->grand_total ?? null }}</td>

                                <td>
                                    <div class="d-flex justify-content-center">
                                  
                                        <a href="{{ route('sell.view', ['id' => $sell->id]) }}"
                                            class="btn btn-outline-primary btn-sm mx-1"><i><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                                              </svg></i> View</a>

                                        <a href="{{ route('sell.edit', ['id' => $sell->id]) }}"
                                            class="btn btn-outline-primary btn-sm"><i><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil" viewBox="0 0 16 16">
                                                <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/>
                                              </svg></i> Edit</a>
                                     
                                        <form action="{{ route('sell.delete', ['id' => $sell->id]) }}" method="POST" id="deleteForm">
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

       
    </div>
    <script>
        $(document).ready(function() {
            $('#sellsTable').DataTable();
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
