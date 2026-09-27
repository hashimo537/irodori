@extends('layouts.irodori-guest')
@section('title', 'いろどり — 家族の1週間を、色で見る')

@section('content')
  <div class="card">
    <h2>家族の1週間を、色で見る。</h2>
    <p class="lead">
      子どもごとに色分けされた「時間の帯」で、
      だれが・いつ・どこにいるのかが ひと目でわかります。
      招待コードひとつで、パパも おじいちゃん・おばあちゃんも 同じ画面を見られます。
    </p>
    <div class="btn-row">
      <a class="btn primary" href="{{ route('register') }}">はじめる</a>
      <a class="btn" href="{{ route('login') }}">ログイン</a>
    </div>
  </div>
@endsection
