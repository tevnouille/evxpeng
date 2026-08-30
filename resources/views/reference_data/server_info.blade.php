@extends('layouts.app')

@section('title', 'Administration — Information serveurs')

@section('content')
    <a href="{{ route('reference-data.index') }}" class="is-size-7">&larr; Administration</a>
    <h1 class="title mt-2">Information serveurs</h1>
    <p class="subtitle is-6">
        Ce qui fait tourner le site, et l'état de tout ce qui y est installé.
    </p>

    @if (! $available)
        <div class="notification is-warning is-light">
            <p>Aucun relevé n'a encore été produit.</p>
            <p class="is-size-7 mt-2">
                Il est établi sur la machine elle-même, par
                <code>scripts/server-inventory.py</code> : depuis le conteneur, ni les paquets
                du système ni <code>node_modules</code> ne sont visibles.
            </p>
        </div>
    @endif

    <div class="level mb-4">
        <div class="level-left">
            <div>
                <p class="heading">Relevé</p>
                <p>
                    @if ($generatedAt)
                        {{ $generatedAt->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}
                        <span class="has-text-grey">({{ $generatedAt->diffForHumans() }})</span>
                        @if ($stale)
                            <span class="tag is-warning ml-2">daté</span>
                        @endif
                    @else
                        <span class="has-text-grey">—</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="level-right">
            <form method="POST" action="{{ route('reference-data.server.refresh') }}">
                @csrf
                <button type="submit" class="button is-link">&#128260; Vérifier les mises à jour</button>
            </form>
        </div>
    </div>

    @if ($pending)
        <div class="notification is-info is-light">
            Une vérification est en attente&nbsp;: elle est prise en charge dans la minute,
            puis cette page affichera le nouveau relevé.
        </div>
    @endif

    @if ($updatePending)
        <div class="notification is-warning is-light">
            Une mise à jour est en cours&nbsp;: elle démarre dans la minute et peut durer
            quelques dizaines de secondes. Rechargez la page pour en voir l'issue.
        </div>
    @endif

    @if (count($updateLog) > 0)
        <div class="box">
            <h2 class="title is-5">Dernières mises à jour</h2>
            <div class="table-container">
                <table class="table is-fullwidth is-striped is-narrow">
                    <tbody>
                        @foreach (array_slice($updateLog, 0, 12) as $operation)
                            <tr>
                                <td class="is-size-7 has-text-grey">
                                    {{ \Illuminate\Support\Carbon::parse($operation['at'])->timezone(config('app.timezone'))->format('d/m H:i') }}
                                </td>
                                <td>{{ $operation['package'] }} <span class="tag is-light is-size-7">{{ $operation['ecosystem'] }}</span></td>
                                <td>
                                    <span class="tag {{ $operation['success'] ? 'is-success' : 'is-danger' }}">
                                        {{ $operation['success'] ? 'Réussie' : 'Échec' }}
                                    </span>
                                </td>
                                <td class="is-size-7">{{ \Illuminate\Support\Str::limit($operation['message'], 160) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($available)
        <div class="columns is-mobile is-multiline mb-4">
            @foreach (\App\Services\ServerInventory::VERDICTS as $key => $verdict)
                @continue($counts[$key] === 0)
                <div class="column">
                    <div class="box has-text-centered">
                        <p class="heading">{{ $verdict['label'] }}</p>
                        <p class="title is-4">{{ $counts[$key] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="box">
            <h2 class="title is-5">Machine</h2>
            <div class="columns is-multiline">
                @php
                    $specs = [
                        'Système' => $host['os'] ?? null,
                        'Noyau' => ($host['kernel'] ?? null).' ('.($host['arch'] ?? '').')',
                        'Nom' => $host['hostname'] ?? null,
                        'Processeur' => ($host['cpu_model'] ?? '—').' — '.($host['cpu_cores'] ?? '?').' cœurs',
                        'Mémoire' => isset($host['memory_total_mb'])
                            ? number_format($host['memory_total_mb'] / 1024, 1, ',', ' ').' Gio, dont '
                                .number_format(($host['memory_available_mb'] ?? 0) / 1024, 1, ',', ' ').' Gio libres'
                            : null,
                        'Disque' => isset($host['disk_total_gb'])
                            ? number_format($host['disk_total_gb'], 1, ',', ' ').' Gio, dont '
                                .number_format($host['disk_free_gb'] ?? 0, 1, ',', ' ').' Gio libres'
                            : null,
                        'Démarré depuis' => isset($host['uptime_days']) ? number_format($host['uptime_days'], 1, ',', ' ').' jour(s)' : null,
                        'Charge' => $host['load'] ? implode(' / ', $host['load']) : null,
                        'Docker' => $host['docker'] ?? null,
                    ];
                @endphp
                @foreach ($specs as $label => $value)
                    @continue(blank($value))
                    <div class="column is-4">
                        <p class="heading">{{ $label }}</p>
                        <p>{{ $value }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="columns">
            <div class="column is-6">
                <div class="box">
                    <h2 class="title is-5">Briques logicielles</h2>
                    <table class="table is-fullwidth is-striped">
                        <tbody>
                            @foreach ($runtimes as $runtime)
                                <tr>
                                    <td>{{ $runtime['name'] }}</td>
                                    <td class="has-text-right">{{ $runtime['version'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="column is-6">
                <div class="box">
                    <h2 class="title is-5">Conteneurs</h2>
                    <table class="table is-fullwidth is-striped">
                        <tbody>
                            @foreach ($containers as $container)
                                <tr>
                                    <td>{{ $container['name'] }}</td>
                                    <td class="has-text-grey">{{ $container['image'] }}</td>
                                    <td class="has-text-right is-size-7">{{ $container['status'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="box">
            <h2 class="title is-5">Comment lire la colonne « Mise à jour »</h2>
            <div class="tags">
                @foreach (\App\Services\ServerInventory::VERDICTS as $verdict)
                    <span class="tag {{ $verdict['class'] }}">{{ $verdict['label'] }}</span>
                @endforeach
            </div>
            <ul class="is-size-7 has-text-grey">
                @foreach (\App\Services\ServerInventory::VERDICTS as $verdict)
                    <li><strong>{{ $verdict['label'] }}</strong> — {{ $verdict['help'] }}</li>
                @endforeach
            </ul>
        </div>

        @foreach ($groups as $group)
            <div class="box" data-package-group>
                <div class="level is-mobile mb-2">
                    <div class="level-left">
                        <h2 class="title is-5 mb-0">{{ $group['label'] }}</h2>
                    </div>
                    <div class="level-right">
                        <span class="has-text-grey is-size-7">{{ count($group['packages']) }} paquet(s)</span>
                    </div>
                </div>
                <p class="has-text-grey is-size-7 mb-3">{{ $group['note'] }}</p>

                <div class="field is-grouped is-grouped-multiline mb-3">
                    <div class="control">
                        <input class="input is-small" type="search" data-package-filter
                               placeholder="Filtrer par nom, licence…" autocomplete="off">
                    </div>
                    <div class="control">
                        <label class="checkbox is-size-7">
                            <input type="checkbox" data-package-todo>
                            Seulement ce qui demande une décision
                        </label>
                    </div>
                    <div class="control">
                        <span class="is-size-7 has-text-grey" data-package-count></span>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table is-fullwidth is-striped is-hoverable is-narrow">
                        <thead>
                            <tr>
                                <th>Paquet</th>
                                <th>Installée</th>
                                <th>Disponible</th>
                                <th>Licence</th>
                                <th>Mise à jour</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['packages'] as $package)
                                @php($verdict = \App\Services\ServerInventory::VERDICTS[$package['verdict']])
                                <tr data-package-row
                                    data-verdict="{{ $package['verdict'] }}"
                                    data-search="{{ mb_strtolower($package['name'].' '.($package['license'] ?? '').' '.implode(' ', $package['tags'])) }}">
                                    <td>
                                        {{ $package['name'] }}
                                        @foreach ($package['tags'] as $tag)
                                            <span class="tag is-light is-size-7 ml-1">{{ $tag }}</span>
                                        @endforeach
                                        @if ($package['note'])
                                            <br><span class="has-text-grey is-size-7">{{ \Illuminate\Support\Str::limit($package['note'], 90) }}</span>
                                        @endif
                                    </td>
                                    <td class="is-family-monospace is-size-7">{{ $package['version'] ?? '—' }}</td>
                                    <td class="is-family-monospace is-size-7">
                                        @if ($package['available'] !== $package['version'])
                                            <strong>{{ $package['available'] ?? '—' }}</strong>
                                        @else
                                            <span class="has-text-grey">{{ $package['available'] ?? '—' }}</span>
                                        @endif
                                    </td>
                                    <td class="is-size-7">{{ $package['license'] ?? '—' }}</td>
                                    <td><span class="tag {{ $verdict['class'] }}">{{ $verdict['label'] }}</span></td>
                                    <td class="has-text-right">
                                        @if ($package['updatable'])
                                            <form method="POST" action="{{ route('reference-data.server.update') }}"
                                                  onsubmit="return confirm('Mettre à jour {{ $package['name'] }} en {{ $package['available'] }} ?');">
                                                @csrf
                                                <input type="hidden" name="ecosystem" value="{{ $package['ecosystem'] }}">
                                                <input type="hidden" name="package" value="{{ $package['name'] }}">
                                                <button type="submit" class="button is-small is-link is-light"
                                                        @disabled($updatePending)>Mettre à jour</button>
                                            </form>
                                        @elseif ($package['ecosystem'] === 'composer' && in_array($package['verdict'], ['recommandee', 'a-evaluer'], true))
                                            <span class="has-text-grey is-size-7" title="Monter une dépendance PHP oblige à reconstruire l'image et à recréer le conteneur.">
                                                à la main
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif
@endsection

@push('scripts')
    @vite('resources/js/server-info.js')
@endpush
