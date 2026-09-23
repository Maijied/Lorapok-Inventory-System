<!DOCTYPE html>
<html lang="en">


<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <link rel="icon" type="image/png" href="{{ asset('frontend/assets/logo.png') }}">

    <meta name="author" content="">
    <!-- Favicon icon -->
    <title>@yield('title')</title>
    <!-- This page CSS -->
    <!-- chartist CSS -->
    @include('admin.include.style')
    {{-- <script src="https://cdn.tiny.cloud/1/xp4eyn58se6am9fbdbbxgjh7cqbmfvr6jk7dtkc90050c2wb/tinymce/6/tinymce.min.js"
        referrerpolicy="origin"></script> --}}
        <script src="{{ asset('admin/assets/node_modules/tinymce/tinymce/tinymce.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>


</head>

<body class="skin-blue fixed-layout">
    <!-- ============================================================== -->
    <!-- Preloader - style you can find in spinners.css -->
    <!-- ============================================================== -->
    <div class="preloader">
        <div class="loader">
            <div class="loader__figure"></div>
            <p class="loader__label">Gadget & Phones</p>
        </div>
    </div>
    <div id="main-wrapper">
        @include('admin.include.header')
        @include('admin.include.sidebar')

        <div class="page-wrapper">

            <div class="container-fluid">
                @yield('body')
            </div>

        </div>

    </div>

    @include('admin.include.script')
    @include('sweetalert::alert')

</body>

<script>
    tinymce.init({
        selector: 'textarea#tinymce',
        height: 500,
        license_key: 'gpl'
    });
</script>


</html>
