@extends('layouts.app')

@section('title', 'Changelog')

@section('content')
    <h1 class="title">Changelog</h1>
    <p class="subtitle is-6">Ce qui a changé dans l'application, du plus récent au plus ancien.</p>

    <p class="has-text-grey is-size-7 mb-5">
        Seules les évolutions qui se voient à l'usage sont listées&nbsp;: les corrections internes
        et les changements techniques n'y figurent pas.
    </p>

    @foreach ($releases as $release)
        <div class="box">
            <h2 class="title is-5 mb-4">
                {{ $release['date']->translatedFormat('j F Y') }}
            </h2>

            @foreach ($release['entries'] as $entry)
                @php($type = $types[$entry['type']] ?? null)
                <div class="columns is-vcentered mb-2">
                    <div class="column is-narrow" style="min-width: 8rem;">
                        @if ($type)
                            <span class="tag {{ $type['class'] }} is-light">{{ $type['label'] }}</span>
                        @endif
                    </div>
                    <div class="column">
                        <p>{{ $entry['text'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
@endsection
