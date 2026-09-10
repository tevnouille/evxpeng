@extends('layouts.app')

@section('title', 'Partager ma position')

@section('content')
    <h1 class="title">Partager ma position</h1>

    <div class="box">
        <p class="mb-3">
            Crée un lien unique et temporaire montrant la position de la voiture sur une carte,
            avec le niveau de batterie et l'autonomie estimée à chaque point. Le lien reste valable
            jusqu'à la date de fin choisie, puis s'arrête de répondre.
        </p>
        <p>
            @if ($smsConfigured)
                Il part par SMS sur <strong>votre</strong> téléphone (identifiants Free Mobile renseignés
                dans <a href="{{ route('account.index') }}">/mon-compte</a>) — l'API Free Mobile ne permet
                pas d'envoyer à un autre numéro. À vous de le retransmettre au destinataire voulu.
            @else
                <span class="has-text-danger">Aucun identifiant Free Mobile renseigné dans
                <a href="{{ route('account.index') }}">/mon-compte</a> : le SMS ne pourra pas partir.</span>
                Le lien sera tout de même créé et affiché ci-dessous, à copier vous-même.
            @endif
        </p>
    </div>

    <form method="POST" action="{{ route('position-shares.store') }}" class="box">
        @csrf
        <h2 class="subtitle">Nouveau partage</h2>

        <div class="columns is-align-items-flex-end">
            <div class="column is-4">
                <div class="field">
                    <label class="label" for="duree">Durée du partage</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select id="duree" name="duree" required>
                                @foreach ($durations as $duration)
                                    @php
                                        // Au-dela d'une journee, l'heure ne parle plus : « 168 heures »
                                        // se lit moins vite que « 7 jours ».
                                        $label = ($duration > 24 && $duration % 24 === 0)
                                            ? intdiv($duration, 24).' jour'.(intdiv($duration, 24) > 1 ? 's' : '')
                                            : $duration.' heure'.($duration > 1 ? 's' : '');
                                    @endphp
                                    <option value="{{ $duration }}" @selected((int) old('duree', 1) === $duration)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="column is-4">
                <div class="field">
                    <div class="control">
                        <button class="button is-link" type="submit">Créer le lien</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @if ($shares->isEmpty())
        <div class="notification is-info is-light">Aucun partage créé pour l'instant.</div>
    @else
        <div class="box">
            <div class="table-container">
                <table class="table is-fullwidth is-striped is-hoverable">
                    <thead>
                        <tr>
                            <th>Créé le</th>
                            <th>Véhicule</th>
                            <th>Fin du partage</th>
                            <th>Statut</th>
                            <th>Lien</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($shares as $share)
                            <tr>
                                <td>{{ $share->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $share->vehicle->name }}</td>
                                <td>{{ $share->expires_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    @if ($share->isExpired())
                                        <span class="tag is-light">expiré</span>
                                    @else
                                        <span class="tag is-success is-light">actif</span>
                                    @endif
                                </td>
                                <td style="min-width: 16rem;">
                                    @if (! $share->isExpired())
                                        <input class="input is-small" type="text" value="{{ $share->url() }}"
                                               readonly onclick="this.select()">
                                    @else
                                        <span class="has-text-grey is-size-7">—</span>
                                    @endif
                                </td>
                                <td class="has-text-right">
                                    @if (! $share->isExpired())
                                        <div class="buttons is-right are-small">
                                            <a class="button is-light" href="{{ $share->url() }}" target="_blank" rel="noopener">Ouvrir</a>
                                            <form method="POST" action="{{ route('position-shares.destroy', $share) }}"
                                                  onsubmit="return confirm('Révoquer ce lien de partage ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="button is-danger is-light" type="submit">Révoquer</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
