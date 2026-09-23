<!DOCTYPE html>
<html lang="en">


<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <title>@yield('title')</title>

    @include('admin.include.style')

    <script src="https://cdn.tiny.cloud/1/xp4eyn58se6am9fbdbbxgjh7cqbmfvr6jk7dtkc90050c2wb/tinymce/6/tinymce.min.js"
        referrerpolicy="origin"></script>



</head>

<body class="skin-blue fixed-layout">
    <div class="preloader">
        <div class="loader">
            <div class="loader__figure"></div>
            <p class="loader__label">BIOXIN</p>
        </div>
    </div>

    <div id="main-wrapper">

        @include('user.include.header')
        @include('user.include.sidebar')
        <div class="page-wrapper">
            <div class="container-fluid">
                @yield('body')
            </div>
        </div>
    </div>
    @include('user.include.script')
    @include('sweetalert::alert')

</body>

<script>
    tinymce.init({
        selector: 'textarea#tinymce',
        height: 500
    });
</script>


</html>
