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
                <h1>
                    <span class="logo-dots" aria-hidden="true">
                        <i style="background:#D79372"></i><i style="background:#8FB2C9"></i><i
                            style="background:#8FAF86"></i><i style="background:#DFB877"></i>
                    </span>いろどり
                </h1>
            </div>
        </div>
    </header>
    <div class="wrap narrow">
        @if ($errors->any())
            <div class="errors">
                入力を見なおしてください。
                <ul>@foreach ($errors->all() as $error)
                <li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </div>
</body>

</html>