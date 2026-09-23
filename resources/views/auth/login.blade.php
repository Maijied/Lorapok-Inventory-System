@extends('frontend.master')

@section('content')
<style>
    /* Reseting */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Poppins', sans-serif;
    }

    body {
        background: #faf6f6fa;
    }

    .wrapper {
        max-width: 350px;
        min-height: 450px;
        margin: 80px auto;
        padding: 40px 30px 30px 30px;
        background-color: #fbeee6;
        border-radius: 15px;
        box-shadow: 13px 13px 20px #cbced1, -13px -13px 20px #fff;
    }

    .logo {
        margin: 15px 105px;
        background: #fbeee6;
    }

    .logo img {
        height: 80px;
        object-fit: cover;
        border-radius: 0%;
        box-shadow: 0px 0px 3px #5f5f5f,
            0px 0px 0px 5px #fbeee6,
            0px 0px 0px #fbeee6,
            -8px -8px 15px #fff;
    }

    .wrapper .name {
        font-weight: 600;
        font-size: 1.4rem;
        letter-spacing: 1.3px;
        padding-left: 10px;
        color: #555;
    }

    .wrapper .form-field input {
        width: 100%;
        display: block;
        border: none;
        outline: none;
        background: none;
        font-size: 1.2rem;
        color: #666;
        padding: 10px 15px 10px 10px;
        /* border: 1px solid red; */
    }

    .wrapper .form-field {
        padding-left: 10px;
        margin-bottom: 20px;
        border-radius: 20px;
        box-shadow: inset 8px 8px 8px #cbced1, inset -8px -8px 8px #fbeee6fe;
    }

    .wrapper .form-field .fas {
        color: #555;
    }

    .wrapper .btn {
        box-shadow: none;
        width: 100%;
        height: 40px;
        background-color: #03A9F4;
        color: #fff;
        border-radius: 25px;
        box-shadow: 3px 3px 3px #b1b1b1,
            -3px -3px 3px #fff;
        letter-spacing: 1.3px;
    }

    .wrapper .btn:hover {
        background-color: #039BE5;
    }

    .wrapper a {
        text-decoration: none;
        font-size: 0.8rem;
        color: #03A9F4;
    }

    .wrapper a:hover {
        color: #039BE5;
    }

    @media(max-width: 380px) {
        .wrapper {
            margin: 30px 20px;
            padding: 40px 15px 15px 15px;
        }
    }
</style>
<div class="wrapper">
    <div class="logo">
        <img src="{{ asset('frontend/assets/logo.png') }}" alt="">
    </div>
    <div class="text-center name" style="margin-bottom: 10px;">
        <h3>LOGIN</h3>
    </div>
    <form class="p-3 mt-3" action="{{ route('login') }}" id="loginForm" method="post">
        @csrf
        <div class="form-field d-flex
       align-items-center">
            <span class="far fa-user"></span>
            <input type="text" name="email" id="email" required placeholder="Email">
        </div>
        <div class="form-field d-flex align-items-center">
            <span class="fas fa-key"></span>
            <input type="password" name="password" id="password" placeholder="Password">
        </div>
        @error('email')
            <script type="text/javascript">
                alert('{{ $message }}');
            </script>
        @enderror
        @error('password')
            <script type="text/javascript">
                alert('{{ $message }}');
            </script>
        @enderror
        <button type="submit" class="btn mt-3">Login</button>
    </form>
</div>

@endsection
