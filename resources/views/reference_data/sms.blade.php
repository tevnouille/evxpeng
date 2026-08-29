@extends('layouts.app')

@section('title', 'Administration — Envois SMS')

@section('content')
    <a href="{{ route('reference-data.index') }}" class="is-size-7">&larr; Administration</a>
    <h1 class="title mt-2">Envois SMS</h1>

    <div class="columns is-multiline">
        <div class="column is-3">
            <div class="box">
                <p class="heading">Envoyés</p>
                <p class="title is-3">{{ $deliveredCount }}</p>
            </div>
        </div>
        <div class="column is-3">
            <div class="box">
                <p class="heading">En échec</p>
                <p class="title is-3 {{ $failedCount > 0 ? 'has-text-danger' : '' }}">{{ $failedCount }}</p>
            </div>
        </div>
        <div class="column is-6">
            <div class="box">
                <p class="heading">Configuration</p>
                <p class="title is-5">
                    @if ($configured)
                        <span class="tag is-success is-medium">API Free Mobile configurée</span>
                    @else
                        <span class="tag is-danger is-medium">Identifiants absents du .env</span>
                    @endif
                </p>
                <p class="has-text-grey is-size-7">
                    Paliers de charge notifiés : <strong>{{ implode(' %, ', $thresholds) }} %</strong>
                </p>
            </div>
        </div>
    </div>

    <div class="box">
        @if ($messages->isEmpty())
            <p class="has-text-grey">Aucun SMS envoyé pour l'instant.</p>
        @else
            <div class="table-container">
                <table class="table is-fullwidth is-striped is-hoverable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Message</th>
                            <th>Résultat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($messages as $message)
                            <tr>
                                <td class="is-vcentered">{{ $message->created_at->format('d/m/Y H:i:s') }}</td>
                                <td class="is-vcentered">{{ $message->message }}</td>
                                <td class="is-vcentered">
                                    @if ($message->delivered)
                                        <span class="tag is-success is-light">envoyé</span>
                                    @else
                                        <span class="tag is-danger is-light">échec</span>
                                        <br>
                                        <span class="has-text-grey is-size-7">
                                            {{ $message->failure_reason }}{{ $message->http_status ? ' (HTTP ' . $message->http_status . ')' : '' }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $messages->links() }}
        @endif
    </div>

    <p class="has-text-grey is-size-7">
        Les SMS partent lors du franchissement d'un palier de charge. L'API Free Mobile ne renvoie
        qu'un code HTTP : un « envoyé » signifie que Free a accepté le message, pas qu'il est arrivé
        sur le téléphone.
    </p>
@endsection
