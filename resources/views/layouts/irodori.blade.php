<!doctype html>
<html lang="ja">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'いろどり')</title>
    <link rel="stylesheet" href="{{ asset('css/irodori.css') }}">
</head>

<body>

    <header class="app">
        <div class="header-inner">
            <div class="brand">
                <h1><a href="{{ route('home') }}">
                        <span class="logo-dots" aria-hidden="true">
                            <i style="background:#D79372"></i><i style="background:#8FB2C9"></i><i
                                style="background:#8FAF86"></i><i style="background:#DFB877"></i>
                        </span>いろどり</a></h1>
                <span class="family">{{ auth()->user()->family->name ?? '' }}</span>
                <span class="spacer"></span>
                    <nav>
                        <a href="{{ route('month') }}">月</a>
                        <a href="{{ route('lessons.index') }}">習い事</a>
                        <a href="{{ route('anniversaries.index') }}">記念日</a>
                        <a href="{{ route('tasks.index') }}">提出物</a>
                        <a href="{{ route('members.index') }}">家族</a>
                        <form method="post" action="{{ route('logout') }}" style="display:inline;">
                            @csrf
                            <button type="submit">ログアウト</button>
                        </form>
                    </nav>
            </div>
            @yield('header')
        </div>
    </header>

    <div class="wrap @yield('wrap-class')">

        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="errors">
                入力を見なおしてください。
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>

    @yield('fab')
    @stack('scripts')
</body>

</html>