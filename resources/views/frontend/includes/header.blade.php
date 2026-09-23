<header class="header-wrap style2">

    <div class="header-bottom">
        <div class="container">
            <nav class="navbar navbar-expand-md navbar-light">
                <div class="collapse navbar-collapse main-menu-wrap" id="navbarSupportedContent">
                    <div class="menu-close d-lg-none">
                        <a href="javascript:void(0)"> <i class="ri-close-line"></i></a>
                    </div>
                    <ul class="navbar-nav ms-auto">
                        <ul class="navbar-nav ms-auto">



                            <li class="nav-item">
                                <a href="{{ route('home') }}"
                                    class="nav-link {{ Request::routeIs('home') ? 'active' : '' }}">
                                    Home
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('login') }}"
                                    class="nav-link {{ Request::routeIs('login') ? 'active' : '' }}">
                                    Login
                                </a>
                            </li>
                            {{-- <li class="nav-item">
                                <a href="{{ route('register') }}"
                                    class="nav-link {{ Request::routeIs('register') ? 'active' : '' }}">Register</a>
                            </li> --}}

                        </ul>

                    </ul>
                </div>
            </nav>

            <div class="mobile-bar-wrap">
                {{--                <button class="searchbtn d-lg-none"><i class="ri-search-line"></i></button> --}}
                <div class="mobile-menu d-lg-none">
                    <a href="javascript:void(0)"><i class="ri-menu-line"></i></a>
                </div>
            </div>
        </div>
    </div>
</header>
