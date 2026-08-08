@extends('layouts.app')

@section('title', $monthName . ' ' . $year)

@section('content')
    <div class="level">
        <div class="level-left">
            <h1 class="title">{{ $monthName }} {{ $year }}</h1>
        </div>
        <div class="level-right">
            <a href="{{ route('history.index', ['year' => $year]) }}" class="button is-light">&larr; Retour aux mois</a>
        </div>
    </div>

    @include('charging_sessions._sessions_table', ['emptyMessage' => 'Aucune recharge ce mois-ci.'])
@endsection
