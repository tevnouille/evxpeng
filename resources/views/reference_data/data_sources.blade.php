@extends('layouts.app')

@section('title', 'Administration — Données récupérées')

@section('content')
    <a href="{{ route('reference-data.index') }}" class="is-size-7">&larr; Administration</a>
    <h1 class="title mt-2">Données récupérées</h1>
    <p class="subtitle is-6">
        Ce que l'application va chercher ailleurs, et depuis quand.
    </p>

    <p class="has-text-grey is-size-7 mb-5">
        Les horodatages sont lus dans les données elles-mêmes, pas déclarés&nbsp;: une collecte
        interrompue se voit ici même si elle n'a levé aucune erreur.
        @unless ($isAdmin)
            Les sources communes à tous les comptes ne peuvent être relancées que par l'administrateur.
        @endunless
    </p>

    <div class="columns is-multiline">
        @foreach ($sources as $source)
            @php($stale = $inventory->isStale($source))
            <div class="column is-4">
                <div class="box is-flex is-flex-direction-column" style="height: 100%;">
                    <p class="title is-4 mb-2">{!! $source['icon'] !!}</p>
                    <h2 class="title is-5 mb-1">{{ $source['label'] }}</h2>
                    <p class="has-text-grey is-size-7 mb-4">{{ $source['description'] }}</p>

                    <div class="mb-4">
                        <p class="heading mb-1">Dernière mise à jour</p>
                        @if ($source['updated_at'])
                            <p class="title is-6 mb-1">
                                {{ $source['updated_at']->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                @if ($stale)
                                    <span class="tag is-warning is-light ml-1">en retard</span>
                                @endif
                            </p>
                            <p class="has-text-grey is-size-7">{{ $source['updated_at']->diffForHumans() }}</p>
                        @else
                            <p class="title is-6 mb-1">—</p>
                            <p class="has-text-grey is-size-7">jamais collectée</p>
                        @endif
                    </div>

                    <table class="table is-narrow is-fullwidth is-size-7 mb-4">
                        <tbody>
                            <tr>
                                <td class="has-text-grey">Contenu</td>
                                <td class="has-text-right">{{ $source['volume'] }}</td>
                            </tr>
                            @if ($source['detail'])
                                <tr>
                                    <td class="has-text-grey">Détail</td>
                                    <td class="has-text-right">{{ $source['detail'] }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td class="has-text-grey">Rythme</td>
                                <td class="has-text-right">{{ $source['schedule'] }}</td>
                            </tr>
                            <tr>
                                <td class="has-text-grey">Origine</td>
                                <td class="has-text-right"><code>{{ $source['origin'] }}</code></td>
                            </tr>
                            <tr>
                                <td class="has-text-grey">Portée</td>
                                <td class="has-text-right">
                                    {{ $source['shared'] ? 'commune à tous les comptes' : 'propre à votre compte' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="mt-auto">
                        @if ($source['shared'] && ! $isAdmin)
                            <button class="button is-light is-fullwidth" type="button" disabled
                                    title="Source commune : seul l'administrateur peut la relancer">
                                Mettre à jour
                            </button>
                        @elseif (($source['available'] ?? true) === false)
                            <button class="button is-light is-fullwidth" type="button" disabled
                                    title="Rien à collecter pour l'instant">
                                Mettre à jour
                            </button>
                        @else
                            <form method="POST" action="{{ route('reference-data.sources.refresh', $source['key']) }}">
                                @csrf
                                <button class="button is-link is-light is-fullwidth" type="submit">
                                    Mettre à jour maintenant
                                </button>
                            </form>
                            @if ($source['background'])
                                <p class="has-text-grey is-size-7 mt-2">
                                    L'import tourne en arrière-plan et dure plusieurs minutes.
                                </p>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
