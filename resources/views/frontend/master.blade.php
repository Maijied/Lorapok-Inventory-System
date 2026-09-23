<!DOCTYPE html>
<html lang="zxx">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gadget & Phones</title>
    <meta name="description" content="Gadget & Phones">
    <!-- responsive tag -->
    <link rel="icon" type="image/png" href="{{ asset('frontend/assets/logo.png') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Link of CSS files -->

</head>

<body>

    <!-- Page Wrapper End -->
    <div class="page-wrapper">


        @yield('content')


    </div>


    <!-- Link of JS files -->
    @include('sweetalert::alert')
</body>

</html>
