@extends('layouts.app')

@section('title', 'I nostri nomi a dominio · '.$tenant->brandName())

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/domain-names.css') }}?v={{ filemtime(public_path('css/domain-names.css')) }}">
@endpush

@section('content')
    <section class="ksm-section">
        <div class="ksm-container">
            <header class="ksm-dn__intro">
                <h1>I nostri nomi a dominio</h1>
                <p>
                    I nostri nomi a dominio sono stati puntati a KSM – Il Portale Dei Portali al punto esatto della categoria.
                    Questo consente alle aziende iscritte al portale di essere presenti nella pagina che viene aperta
                    dall'unico dominio al mondo che rappresenta la propria categoria. Ad esempio chi digita
                    <strong>www.pub.it</strong> raggiunge KSM alla voce <em>pub</em>.
                </p>
            </header>

            <form method="GET" action="{{ route('domain_names.index') }}" class="ksm-card ksm-dn__filters" role="search">
                <div class="ksm-field ksm-dn__query">
                    <label class="ksm-label" for="cerca">Cerca un nome</label>
                    <input class="ksm-input" id="cerca" name="cerca" type="search" value="{{ $filters['term'] }}"
                           placeholder="Per esempio: pizza, hotel, scarpe">
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="estensione">Estensione</label>
                    <select class="ksm-select" id="estensione" name="estensione" data-dn-auto>
                        <option value="">Tutte</option>
                        @foreach ($extensions as $ext => $count)
                            <option value="{{ $ext }}" @selected($filters['extension'] === $ext)>.{{ $ext }} ({{ $count }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="tema">Tema</label>
                    <select class="ksm-select" id="tema" name="tema" data-dn-auto>
                        <option value="">Tutti</option>
                        @foreach ($themes as $theme => $count)
                            <option value="{{ $theme }}" @selected($filters['theme'] === $theme)>{{ $theme }} ({{ $count }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="ordina">Ordina per</label>
                    <select class="ksm-select" id="ordina" name="ordina" data-dn-auto>
                        @foreach (\App\Models\DomainName::SORTS as $value => $label)
                            <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($filters['letter'] !== '')
                    <input type="hidden" name="iniziale" value="{{ $filters['letter'] }}">
                @endif
                <button class="ksm-btn ksm-btn--primary" type="submit"><x-icon name="search" :size="18" /> Cerca</button>
            </form>

            @php($letterQuery = fn (string $letter) => route('domain_names.index', array_filter(
                array_merge(request()->except(['page', 'iniziale']), ['iniziale' => $letter]), fn ($v) => $v !== null && $v !== '')))
            <nav class="ksm-dn__letters" aria-label="Iniziale del nome">
                <a href="{{ route('domain_names.index', request()->except(['page', 'iniziale'])) }}"
                   @class(['is-current' => $filters['letter'] === ''])>Tutti</a>
                @foreach (array_merge(['0'], range('a', 'z')) as $letter)
                    <a href="{{ $letterQuery($letter) }}" @class(['is-current' => $filters['letter'] === $letter])>{{ $letter === '0' ? '0-9' : strtoupper($letter) }}</a>
                @endforeach
            </nav>

            <p class="ksm-muted ksm-dn__count">
                @if ($domains->total() === $total)
                    {{ number_format($total, 0, ',', '.') }} nomi a dominio
                @else
                    {{ number_format($domains->total(), 0, ',', '.') }} su {{ number_format($total, 0, ',', '.') }} nomi a dominio
                    <a href="{{ route('domain_names.index') }}">Azzera i filtri</a>
                @endif
            </p>

            @if ($domains->isEmpty())
                <p class="ksm-muted">Nessun nome a dominio trovato.</p>
            @else
                <ul class="ksm-dn__grid">
                    @foreach ($domains as $domain)
                        <li>
                            <a class="ksm-dn__item" href="{{ $domain->url }}" rel="noopener">
                                <span class="ksm-dn__name">{{ $domain->display }}</span>
                                @if ($domain->category)
                                    <span class="ksm-dn__theme">{{ $domain->category }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="ksm-dn__pager">{{ $domains->links() }}</div>
            @endif
        </div>
    </section>

    {{-- Le tendine applicano il filtro appena si cambia scelta; senza JavaScript resta il pulsante Cerca. --}}
    @push('scripts')
        <script>
            document.querySelectorAll('[data-dn-auto]').forEach(function (select) {
                select.addEventListener('change', function () { select.form.submit(); });
            });
        </script>
    @endpush
@endsection
