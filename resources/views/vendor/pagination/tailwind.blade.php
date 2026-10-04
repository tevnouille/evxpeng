{{--
    Remplace la vue de pagination par defaut de Laravel (nommee "tailwind"
    malgre ce fichier -- c'est le nom fixe que le framework resout, changer le
    nom du fichier ne changerait rien). La vue d'origine s'appuie sur des
    classes Tailwind (`class="w-5 h-5"` sur les SVG precedent/suivant) pour
    leur donner une taille : sans Tailwind charge dans ce projet (Bulma ici),
    ces classes ne font rien et les SVG s'affichent a leur taille native --
    enormes. Reecrite en Bulma, avec des libelles texte plutot que des icones :
    plus simple, et ca evite justement ce genre de piege de taille.
--}}
@if ($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        @if ($paginator->onFirstPage())
            <span class="pagination-previous" disabled>Précédent</span>
        @else
            <a class="pagination-previous" href="{{ $paginator->previousPageUrl() }}" rel="prev">Précédent</a>
        @endif

        @if ($paginator->hasMorePages())
            <a class="pagination-next" href="{{ $paginator->nextPageUrl() }}" rel="next">Suivant</a>
        @else
            <span class="pagination-next" disabled>Suivant</span>
        @endif

        <ul class="pagination-list">
            @foreach ($elements as $element)
                {{-- Puce "..." --}}
                @if (is_string($element))
                    <li><span class="pagination-ellipsis">&hellip;</span></li>
                @endif

                {{-- Liens de page --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li><a class="pagination-link is-current" aria-current="page" aria-label="{{ __('Page :page', ['page' => $page]) }}">{{ $page }}</a></li>
                        @else
                            <li><a class="pagination-link" href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </ul>
    </nav>
@endif
