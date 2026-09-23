@extends('frontend.master')

@section('content')
    <section class="Login-wrap pt-100 pb-75">
        <div class="container">
            <div class="row gx-5">
                <div class="col-xl-6 offset-xl-3 col-lg-8 offset-lg-2">
                    <div class="login-form-wrap">
                        <div class="login-header">
                            <h3>Register</h3>
                        </div>
                        <form class="login-form" method="POST" action="{{ route('register') }}">
                            @csrf
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <input id="name" type="text" name="name" type="text"
                                            placeholder="Username" required>
                                    </div>
                                </div>

                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <input id="email" type="email" name="email" type="text"
                                            placeholder="Email" required>
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <input id="pwd" name="password" placeholder="Password" type="password">
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <input id="pwd_2" name="password_confirmation" placeholder="Confirm Password"
                                            type="password">
                                    </div>
                                </div>
                                <div class="col-sm-12 col-12 mb-20">
                                    <div class="checkbox style3">
                                        <input type="checkbox" id="test_1">
                                        <label for="test_1">
                                            I Agree with the <a class="link style1" href="">Terms &amp;
                                                conditions</a>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <button class="btn style1 w-100 d-block">
                                            Register
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </form>
                        <div class="col-md-12">
                            <p class="mb-0">Have an Account? <a class="link style1" href="{{ route('login') }}">Sign
                                    In</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
