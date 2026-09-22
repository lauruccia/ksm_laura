@extends('layouts.panel')

@section('title', $title.' · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    @php
        /*
         * Modulo del dominio, a sezioni. I campi dei contenuti vanno in site[...]:
         * lasciati vuoti valgono i predefiniti, mostrati come segnaposto.
         */
        $value = fn (string $field) => old($field, $record->$field);
        $site = fn (string $key) => old('site.'.$key, data_get($record->site, $key));
        $siteOn = fn (string $key, bool $default) => (bool) old('site.'.$key, data_get($record->site, $key, $default));
        $name = fn (string $key) => 'site['.str_replace('.', '][', $key).']';
        $hero = $defaults->hero();
        $footerDefaults = $defaults->footer();
        $featuredDefaults = $defaults->featured();
        $benefitDefaults = $defaults->benefits();
        $sections = [
            'generale' => 'Generale', 'contenuto' => 'Cosa mostra', 'aspetto' => 'Aspetto', 'apertura' => 'Apertura',
            'vantaggi' => 'Vantaggi', 'categorie' => 'Categorie', 'prodotti' => 'Prodotti', 'menu' => 'Menu',
            'piede' => 'Piè di pagina', 'seo' => 'SEO',
        ];
        $linkGroups = [
            'menu' => ['menu.left' => 'Menu a sinistra del marchio', 'menu.right' => 'Menu a destra del marchio'],
            'piede' => ['footer.links' => 'Prima colonna di link', 'footer.info' => 'Seconda colonna di link (privacy, condizioni…)'],
        ];
    @endphp

    <style>
        .ksm-domain-form { display: grid; grid-template-columns: 190px minmax(0, 1fr); gap: 24px; align-items: start; max-width: 1180px; }
        .ksm-domain-form__nav { position: sticky; top: 18px; display: grid; gap: 2px; }
        .ksm-domain-form__nav a { padding: 8px 12px; border-radius: 8px; color: var(--ksm-ink); font-size: .9rem; }
        .ksm-domain-form__nav a:hover { background: var(--ksm-accent-soft); }
        .ksm-domain-form__section { padding: 22px 24px; margin-bottom: 18px; scroll-margin-top: 16px; }
        .ksm-domain-form__section h2 { margin: 0 0 4px; font-size: 1.1rem; }
        .ksm-domain-form__section > p { margin: 0 0 16px; color: var(--ksm-muted); font-size: .88rem; }
        .ksm-domain-form__switch { display: flex; gap: 8px; align-items: center; margin-bottom: 14px; font-weight: 600; }
        .ksm-domain-form__links { display: grid; grid-template-columns: 1fr 2fr; gap: 6px 10px; margin-bottom: 16px; }
        .ksm-domain-form__benefit { display: grid; grid-template-columns: 150px 1fr 1fr; gap: 10px; margin-bottom: 8px; }
        .ksm-domain-form__checks { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 6px 14px; max-height: 260px; overflow: auto; padding: 10px; border: 1px solid var(--ksm-line); border-radius: 8px; }
        .ksm-domain-form__preview { display: block; max-height: 70px; max-width: 220px; margin: 6px 0; border-radius: 6px; background: #f1f4f6; }
        .ksm-domain-form__save { position: sticky; bottom: 0; padding: 14px 0; background: linear-gradient(transparent, var(--ksm-canvas) 35%); }
        @media (max-width: 900px) {
            .ksm-domain-form { grid-template-columns: 1fr; }
            .ksm-domain-form__nav { position: static; display: flex; flex-wrap: wrap; }
            .ksm-domain-form__benefit, .ksm-domain-form__links { grid-template-columns: 1fr; }
        }
    </style>

    <div class="ksm-panel__head">
        <h1>{{ $record->exists ? 'Modifica' : 'Nuovo' }} · {{ $title }}</h1>
        <div style="display: flex; gap: 8px;">
            @if ($record->exists)
                <a class="ksm-btn ksm-btn--ghost" href="https://{{ $record->domain }}" target="_blank" rel="noopener">Apri il sito</a>
            @endif
            <a class="ksm-btn ksm-btn--ghost" href="{{ route($routePrefix.'.index') }}">Torna all'elenco</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="ksm-alert ksm-alert--error" style="margin-bottom: 16px;">Controlla i campi segnalati: {{ $errors->count() }} da correggere.</div>
    @endif

    <div class="ksm-domain-form">
        <nav class="ksm-domain-form__nav" aria-label="Sezioni del modulo">
            @foreach ($sections as $anchor => $label)
                <a href="#{{ $anchor }}">{{ $label }}</a>
            @endforeach
        </nav>

        <form method="POST" enctype="multipart/form-data"
              action="{{ $record->exists ? route($routePrefix.'.update', $record) : route($routePrefix.'.store') }}">
            @csrf
            @if ($record->exists)
                @method('PUT')
            @endif

            {{-- Generale --}}
            <section class="ksm-card ksm-domain-form__section" id="generale">
                <h2>Generale</h2>
                <p>Chi è il sito: nome, indirizzo e marchio. Valgono su tutte le pagine del dominio.</p>
                <div class="ksm-formgrid">
                    <div class="ksm-field">
                        <label class="ksm-label" for="name">Nome del sito</label>
                        <input class="ksm-input" id="name" name="name" value="{{ $value('name') }}" required>
                        <small class="ksm-muted">Compare nel titolo della scheda del browser, nel copyright del piede e come mittente delle email del sito. In testata solo se manca il logo.</small>
                        @error('name')<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ksm-field">
                        <label class="ksm-label" for="domain">Dominio</label>
                        <input class="ksm-input" id="domain" name="domain" value="{{ $value('domain') }}" placeholder="mozzarelledibufala.it" required>
                        <small class="ksm-muted">L'indirizzo del sito, senza www e senza https://. Cambiarlo azzera la verifica del collegamento.</small>
                        @error('domain')<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ksm-field">
                        <label class="ksm-label" for="logo">Logo</label>
                        @if ($record->logo)
                            <img class="ksm-domain-form__preview" src="{{ asset('storage/'.$record->logo) }}" alt="">
                            <label style="display: flex; gap: 6px; align-items: center;"><input type="checkbox" name="remove_logo" value="1"> Togli il logo</label>
                        @endif
                        <input class="ksm-input" id="logo" name="logo" type="file" accept="image/*">
                        <small class="ksm-muted">Il marchio in testata, su ogni pagina. Meglio PNG con sfondo trasparente. Senza logo la testata mostra il nome scritto.</small>
                        @error('logo')<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ksm-field">
                        <label class="ksm-label" for="favicon">Icona della scheda (favicon)</label>
                        @if ($record->favicon)
                            <img class="ksm-domain-form__preview" src="{{ asset('storage/'.$record->favicon) }}" alt="" style="max-height: 32px;">
                            <label style="display: flex; gap: 6px; align-items: center;"><input type="checkbox" name="remove_favicon" value="1"> Togli l'icona</label>
                        @endif
                        <input class="ksm-input" id="favicon" name="favicon" type="file" accept="image/*">
                        <small class="ksm-muted">La piccola icona accanto al titolo nella scheda del browser. Quadrata. Senza, si usa il logo.</small>
                        @error('favicon')<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <label class="ksm-domain-form__switch">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked($value('is_active'))> Dominio attivo
                </label>
                <small class="ksm-muted">Spento, il dominio non ha più un sito suo: mostra il sito principale.</small>
            </section>

            {{-- Cosa mostra --}}
            <section class="ksm-card ksm-domain-form__section" id="contenuto">
                <h2>Cosa mostra</h2>
                <p>Prodotti e aziende fuori dal filtro non si vedono sul dominio, nemmeno aprendo l'indirizzo a mano.</p>
                <div class="ksm-formgrid">
                    <div class="ksm-field">
                        <label class="ksm-label" for="type">Filtro</label>
                        <select class="ksm-select" id="type" name="type">
                            @foreach ($types as $key => $label)
                                <option value="{{ $key }}" @selected($value('type') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="ksm-muted">Quali aziende e prodotti entrano nel sito. "Nessun filtro": tutto il marketplace. "Per città": usa il campo Città o regione. "Per categoria": usa le categorie qui a fianco.</small>
                        @error('type')<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ksm-field">
                        <label class="ksm-label" for="product_category_id">Categoria prodotti</label>
                        <select class="ksm-select" id="product_category_id" name="product_category_id">
                            <option value="">Tutte</option>
                            @foreach ($productCategories as $id => $label)
                                <option value="{{ $id }}" @selected((string) $value('product_category_id') === (string) $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="ksm-muted">Con il filtro per categoria: solo questa e le sue sottocategorie.</small>
                        @error('product_category_id')<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ksm-field">
                        <label class="ksm-label" for="company_scope">Aziende mostrate</label>
                        <select class="ksm-select" id="company_scope" name="company_scope">
                            @foreach ($companyScopes as $key => $label)
                                <option value="{{ $key }}" @selected($value('company_scope') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="ksm-muted">Quali aziende compaiono in home, nell'elenco Aziende e con la loro scheda.</small>
                        @error('company_scope')<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ksm-field">
                        <label class="ksm-label" for="company_category_id">Categoria aziende</label>
                        <select class="ksm-select" id="company_category_id" name="company_category_id">
                            <option value="">Tutte</option>
                            @foreach ($companyCategories as $id => $label)
                                <option value="{{ $id }}" @selected((string) $value('company_category_id') === (string) $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="ksm-muted">Con il filtro per categoria limita aziende e prodotti (es. Ristoranti: solo i voucher dei ristoranti). Non usata con "Solo chi vende".</small>
                        @error('company_category_id')<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ksm-field">
                        <label class="ksm-label" for="city">Città o regione</label>
                        <input class="ksm-input" id="city" name="city" value="{{ $value('city') }}">
                        <small class="ksm-muted">Con il filtro per città. Il nome di una regione ("Calabria") prende tutta la regione. Il nome di una città ("Ostia") prende le aziende la cui città contiene quella parola intera: Ostia, Lido di Ostia, Ostia Antica, ma non Ostiano. Vale per aziende e prodotti, e toglie dalla home il menu "Tutte le regioni".</small>
                        @error('city')<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="ksm-formgrid">
                    <div class="ksm-field">
                        <label class="ksm-label" for="entry_page">Pagina iniziale</label>
                        <select class="ksm-select" id="entry_page" name="entry_page">
                            @foreach ($entryPages as $key => $label)
                                <option value="{{ $key }}" @selected($value('entry_page') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="ksm-muted">Cosa si apre digitando il dominio. "Home": apertura, vantaggi, ricerca, aziende e prodotti in evidenza. "Shop prodotti": lo shop con le sezioni Apertura, Vantaggi, Categorie e Prodotti di questo modulo.</small>
                        @error('entry_page')<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ksm-field">
                        <label class="ksm-label" for="entry_company_id">ID dell'azienda</label>
                        <input class="ksm-input" id="entry_company_id" name="entry_company_id" type="number" min="1" value="{{ $value('entry_company_id') }}">
                        <small class="ksm-muted">{{ $entryCompany ? 'Ora: '.$entryCompany->name : 'Solo con la pagina di un\'azienda.' }}</small>
                        @error('entry_company_id')<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ksm-field">
                        <label class="ksm-label" for="entry_cms_page_id">Pagina CMS</label>
                        <select class="ksm-select" id="entry_cms_page_id" name="entry_cms_page_id">
                            <option value="">Nessuna</option>
                            @foreach ($cmsPages as $id => $label)
                                <option value="{{ $id }}" @selected((string) $value('entry_cms_page_id') === (string) $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="ksm-muted">Solo con Pagina iniziale "Pagina CMS".</small>
                        @error('entry_cms_page_id')<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ksm-field">
                        <label class="ksm-label" for="site-ads-mode">Banner del circuito</label>
                        <select class="ksm-select" id="site-ads-mode" name="site[ads][mode]">
                            @foreach (['all' => 'Tutte le campagne adatte', 'targeted' => 'Solo le campagne scelte per questo dominio', 'none' => 'Nessun banner'] as $key => $label)
                                <option value="{{ $key }}" @selected(($site('ads.mode') ?? 'all') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="ksm-muted">"Tutte" comprende le campagne senza domini scelti, anche quelle di KSM.</small>
                        @error('site.ads.mode')<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </section>

            {{-- Aspetto --}}
            <section class="ksm-card ksm-domain-form__section" id="aspetto">
                <h2>Aspetto</h2>
                <p>La testata e i colori, su tutte le pagine del sito. Colori in formato HEX, ad esempio #0b4662. Vuoti: quelli dello stile scelto.</p>
                <div class="ksm-formgrid">
                    <div class="ksm-field">
                        <label class="ksm-label" for="header_variant">Stile della testata</label>
                        <select class="ksm-select" id="header_variant" name="header_variant">
                            <option value="">Predefinito (marketplace)</option>
                            @foreach ($variants as $key => $label)
                                <option value="{{ $key }}" @selected($value('header_variant') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="ksm-muted">Marketplace: pulsante Registrati in testata. Shop e Artigianale: carrello in testata e menu da negozio.</small>
                    </div>
                    @foreach ([
                        'header_background' => ['Colore principale', 'Sfondo della fascia di apertura, del piede e delle parti scure.'],
                        'header_accent' => ['Colore dei pulsanti e accenti', 'Pulsanti, link attivi, pallini del motto e parole evidenziate.'],
                        'header_color' => ['Colore del testo e del marchio', 'Titoli e testi scuri, e il nome del sito in testata.'],
                    ] as $field => [$label, $hint])
                        <div class="ksm-field">
                            <label class="ksm-label" for="{{ $field }}">{{ $label }}</label>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <span style="width: 34px; height: 34px; flex: none; border-radius: 8px; border: 1px solid var(--ksm-line); background: {{ preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value($field)) ? $value($field) : 'transparent' }};"></span>
                                <input class="ksm-input" id="{{ $field }}" name="{{ $field }}" value="{{ $value($field) }}" placeholder="#rrggbb">
                            </div>
                            <small class="ksm-muted">{{ $hint }}</small>
                            @error($field)<span class="ksm-error">{{ $message }}</span>@enderror
                        </div>
                    @endforeach
                    <div class="ksm-field">
                        <label class="ksm-label" for="header_tagline">Sottotitolo sotto il marchio</label>
                        <input class="ksm-input" id="header_tagline" name="header_tagline" value="{{ $value('header_tagline') }}" placeholder="L'eccellenza artigianale a casa tua">
                        <small class="ksm-muted">La prima riga sotto il logo. Vuoto: "{{ trim(__('site.claim_line1').' '.__('site.claim_line2')) }}".</small>
                    </div>
                    <div class="ksm-field">
                        <label class="ksm-label" for="header_subline">Motto sotto il marchio</label>
                        <input class="ksm-input" id="header_subline" name="header_subline" value="{{ $value('header_subline') }}" placeholder="Sapori autentici · Territorio · Passione">
                        <small class="ksm-muted">La riga in maiuscoletto sotto il sottotitolo. Separa le parole con · e diventano MARE • STORIA • PERSONE. Vuoto: "{{ __('site.claim_tagline') }}".</small>
                    </div>
                </div>
            </section>

            {{-- Apertura --}}
            <section class="ksm-card ksm-domain-form__section" id="apertura">
                <h2>Apertura</h2>
                <p>La fascia grande in cima alla pagina. Ogni campo dice dove compare: <strong>home e shop</strong>, oppure <strong>solo shop</strong>. Nella home i due pulsanti sono fissi: Esplora le aziende e Scopri i prodotti. I campi vuoti usano il testo grigio d'esempio.</p>
                <label class="ksm-domain-form__switch">
                    <input type="hidden" name="site[hero][enabled]" value="0">
                    <input type="checkbox" name="site[hero][enabled]" value="1" @checked($siteOn('hero.enabled', true))> Mostra l'apertura nello shop
                </label>
                <div class="ksm-formgrid">
                    @foreach ([
                        'hero.title' => ['Titolo', $hero['title'], 'Home e shop. La prima parte del titolo, in bianco.'],
                        'hero.highlight' => ['Parte del titolo in evidenza', $hero['highlight'], 'Home e shop. Segue il titolo, in verde di risalto.'],
                        'hero.script' => ['Scritta a mano sull\'immagine', 'Il gusto della tradizione ogni giorno', 'Home e shop. In corsivo, in basso a destra, con una riga sotto. Vuota: non compare.'],
                        'hero.eyebrow' => ['Scritta piccola sopra il titolo', $hero['eyebrow'], 'Solo shop.'],
                        'hero.primary_label' => ['Pulsante principale', $hero['primary_label'], 'Solo shop. Il pulsante pieno.'],
                        'hero.primary_url' => ['Link del pulsante principale', $hero['primary_url'], 'Solo shop. #catalogo scende al catalogo nella stessa pagina.'],
                        'hero.secondary_label' => ['Secondo pulsante', $hero['secondary_label'], 'Solo shop. Il pulsante a contorno.'],
                        'hero.secondary_url' => ['Link del secondo pulsante', '/prodotti?offerta=1', 'Solo shop.'],
                        'hero.badge_title' => ['Riquadro: numero o parola forte', '100%', 'Solo shop. Il bollino sopra l\'immagine. Vuoto: niente bollino.'],
                        'hero.badge_text' => ['Riquadro: testo', 'Latte di bufala italiano', 'Solo shop. La riga piccola del bollino.'],
                    ] as $key => [$label, $placeholder, $hint])
                        <div class="ksm-field">
                            <label class="ksm-label" for="site-{{ $key }}">{{ $label }}</label>
                            <input class="ksm-input" id="site-{{ $key }}" name="{{ $name($key) }}" value="{{ $site($key) }}" placeholder="{{ $placeholder }}">
                            <small class="ksm-muted">{{ $hint }}</small>
                            @error('site.'.$key)<span class="ksm-error">{{ $message }}</span>@enderror
                        </div>
                    @endforeach
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="site-hero.text">Testo</label>
                    <textarea class="ksm-textarea" id="site-hero.text" name="site[hero][text]" rows="3" placeholder="{{ $hero['text'] }}">{{ $site('hero.text') }}</textarea>
                    <small class="ksm-muted">Home e shop. Una o due frasi sotto il titolo.</small>
                    @error('site.hero.text')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
                <label class="ksm-domain-form__switch">
                    <input type="hidden" name="site[hero][badge_flag]" value="0">
                    <input type="checkbox" name="site[hero][badge_flag]" value="1" @checked($siteOn('hero.badge_flag', false))> Bandiera italiana nel riquadro (solo shop)
                </label>
                <div class="ksm-field">
                    <label class="ksm-label" for="hero_image">Immagine</label>
                    @if ($site('hero.image'))
                        <img class="ksm-domain-form__preview" src="{{ asset('storage/'.$site('hero.image')) }}" alt="">
                        <label style="display: flex; gap: 6px; align-items: center;"><input type="checkbox" name="remove_hero_image" value="1"> Togli l'immagine</label>
                    @endif
                    <input class="ksm-input" id="hero_image" name="hero_image" type="file" accept="image/*">
                    <small class="ksm-muted">Orizzontale, almeno 1600 pixel di larghezza: il soggetto a destra, il testo sta a sinistra.</small>
                    @error('hero_image')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
            </section>

            {{-- Vantaggi --}}
            <section class="ksm-card ksm-domain-form__section" id="vantaggi">
                <h2>Vantaggi</h2>
                <p><strong>Home e shop.</strong> I riquadri bianchi subito sotto l'apertura, fino a quattro. Per ogni riquadro: icona, titolo in grassetto e riga piccola sotto. Un riquadro senza titolo non compare. Tutti vuoti: nella home "Migliaia di aziende…", nello shop le voci grigie d'esempio.</p>
                <label class="ksm-domain-form__switch">
                    <input type="hidden" name="site[benefits][enabled]" value="0">
                    <input type="checkbox" name="site[benefits][enabled]" value="1" @checked($siteOn('benefits.enabled', true))> Mostra i vantaggi
                </label>
                <div class="ksm-domain-form__benefit ksm-muted" style="font-size: .8rem; margin-bottom: 4px;"><span>Icona</span><span>Titolo</span><span>Riga sotto</span></div>
                @for ($i = 0; $i < 4; $i++)
                    <div class="ksm-domain-form__benefit">
                        <select class="ksm-select" name="site[benefits][items][{{ $i }}][icon]" aria-label="Icona {{ $i + 1 }}">
                            @foreach ($icons as $key => $label)
                                <option value="{{ $key }}" @selected(($site("benefits.items.$i.icon") ?? $benefitDefaults[$i]['icon'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input class="ksm-input" name="site[benefits][items][{{ $i }}][title]" value="{{ $site("benefits.items.$i.title") }}" placeholder="{{ $benefitDefaults[$i]['title'] ?? 'Titolo' }}" aria-label="Titolo {{ $i + 1 }}">
                        <input class="ksm-input" name="site[benefits][items][{{ $i }}][text]" value="{{ $site("benefits.items.$i.text") }}" placeholder="{{ $benefitDefaults[$i]['text'] ?? 'Testo' }}" aria-label="Testo {{ $i + 1 }}">
                    </div>
                @endfor
                @error('site.benefits.items')<span class="ksm-error">{{ $message }}</span>@enderror
            </section>

            {{-- Categorie --}}
            <section class="ksm-card ksm-domain-form__section" id="categorie">
                <h2>Categorie</h2>
                <p><strong>Solo shop.</strong> I riquadri con foto delle categorie, sotto i vantaggi. Spunta quelle da mostrare. Senza spunte: le sottocategorie della categoria del dominio. La foto di ogni riquadro si carica da Categorie prodotti.</p>
                <label class="ksm-domain-form__switch">
                    <input type="hidden" name="site[categories][enabled]" value="0">
                    <input type="checkbox" name="site[categories][enabled]" value="1" @checked($siteOn('categories.enabled', true))> Mostra le categorie
                </label>
                <div class="ksm-formgrid">
                    <div class="ksm-field">
                        <label class="ksm-label" for="site-categories-title">Titolo</label>
                        <input class="ksm-input" id="site-categories-title" name="site[categories][title]" value="{{ $site('categories.title') }}" placeholder="{{ __('storefront.categories') }}">
                        <small class="ksm-muted">Il titolo sopra i riquadri.</small>
                    </div>
                    <div class="ksm-field">
                        <label class="ksm-label" for="site-categories-link">Link a destra del titolo</label>
                        <input class="ksm-input" id="site-categories-link" name="site[categories][link_label]" value="{{ $site('categories.link_label') }}" placeholder="{{ __('site.shop_all_products') }}">
                        <small class="ksm-muted">Il testo del link che porta a tutti i prodotti.</small>
                    </div>
                </div>
                <div class="ksm-domain-form__checks">
                    @foreach ($railOptions as $id => $label)
                        <label style="display: flex; gap: 6px; align-items: center;">
                            <input type="checkbox" name="site[categories][ids][]" value="{{ $id }}" @checked(in_array($id, array_map('intval', (array) $site('categories.ids')), true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <label class="ksm-domain-form__switch" style="margin-top: 12px;">
                    <input type="hidden" name="site[categories][offers]" value="0">
                    <input type="checkbox" name="site[categories][offers]" value="1" @checked($siteOn('categories.offers', true))> Riquadro "In offerta" in fondo
                </label>
            </section>

            {{-- Prodotti --}}
            <section class="ksm-card ksm-domain-form__section" id="prodotti">
                <h2>Prodotti</h2>
                <p><strong>Solo shop.</strong> Una fila di prodotti in evidenza e, sotto, il catalogo completo con i filtri. Nella home i prodotti in evidenza sono scelti in automatico.</p>
                <label class="ksm-domain-form__switch">
                    <input type="hidden" name="site[featured][enabled]" value="0">
                    <input type="checkbox" name="site[featured][enabled]" value="1" @checked($siteOn('featured.enabled', true))> Mostra i prodotti in evidenza
                </label>
                <div class="ksm-formgrid">
                    <div class="ksm-field">
                        <label class="ksm-label" for="site-featured-title">Titolo</label>
                        <input class="ksm-input" id="site-featured-title" name="site[featured][title]" value="{{ $site('featured.title') }}" placeholder="{{ $featuredDefaults['title'] }}">
                    </div>
                    <div class="ksm-field">
                        <label class="ksm-label" for="site-featured-subtitle">Sottotitolo</label>
                        <input class="ksm-input" id="site-featured-subtitle" name="site[featured][subtitle]" value="{{ $site('featured.subtitle') }}" placeholder="{{ $featuredDefaults['subtitle'] }}">
                    </div>
                    <div class="ksm-field">
                        <label class="ksm-label" for="site-featured-sort">Quali prodotti</label>
                        <select class="ksm-select" id="site-featured-sort" name="site[featured][sort]">
                            @foreach ($sorts as $key => $label)
                                <option value="{{ $key }}" @selected(($site('featured.sort') ?? 'bestsellers') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="ksm-muted">Con quale criterio si scelgono i prodotti della fila.</small>
                    </div>
                    <div class="ksm-field">
                        <label class="ksm-label" for="site-featured-count">Quanti</label>
                        <input class="ksm-input" id="site-featured-count" name="site[featured][count]" type="number" min="2" max="12" value="{{ $site('featured.count') ?? 6 }}">
                        <small class="ksm-muted">Da 2 a 12.</small>
                        @error('site.featured.count')<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <label class="ksm-domain-form__switch">
                    <input type="hidden" name="site[featured][search]" value="0">
                    <input type="checkbox" name="site[featured][search]" value="1" @checked($siteOn('featured.search', true))> Casella di ricerca accanto al titolo
                </label>
                <label class="ksm-domain-form__switch">
                    <input type="hidden" name="site[catalog][enabled]" value="0">
                    <input type="checkbox" name="site[catalog][enabled]" value="1" @checked($siteOn('catalog.enabled', true))> Mostra il catalogo completo con i filtri
                </label>
                <div class="ksm-field">
                    <label class="ksm-label" for="site-catalog-title">Titolo del catalogo</label>
                    <input class="ksm-input" id="site-catalog-title" name="site[catalog][title]" value="{{ $site('catalog.title') }}" placeholder="{{ __('storefront.catalog') }}">
                    <small class="ksm-muted">Il titolo sopra l'elenco completo dei prodotti.</small>
                </div>
            </section>

            {{-- Menu e piede: righe di link --}}
            @foreach (['menu' => 'Menu', 'piede' => 'Piè di pagina'] as $anchor => $heading)
                <section class="ksm-card ksm-domain-form__section" id="{{ $anchor }}">
                    <h2>{{ $heading }}</h2>
                    @if ($anchor === 'menu')
                        <p><strong>Tutte le pagine.</strong> Le voci della testata, ai lati del logo. Per ogni voce: a sinistra il testo, a destra il link. Link interni come /prodotti, /aziende, /contatti o /prodotti?offerta=1, oppure indirizzi completi con https://. Tutto vuoto: le voci predefinite (Home, Aziende, Prodotti, Contattaci…).</p>
                    @else
                        <p><strong>Tutte le pagine.</strong> La fascia in fondo al sito. Non mostra nulla di KSM: marchio, recapiti e link sono quelli del dominio. Indirizzo, telefono ed email compaiono solo se compilati.</p>
                        <div class="ksm-field">
                            <label class="ksm-label" for="site-footer-about">Descrizione</label>
                            <textarea class="ksm-textarea" id="site-footer-about" name="site[footer][about]" rows="3" placeholder="{{ $footerDefaults['about'] ?: 'Due righe sul sito' }}">{{ $site('footer.about') }}</textarea>
                            <small class="ksm-muted">Due righe sotto il logo nel piede. Vuota: la Descrizione del dominio della sezione SEO.</small>
                        </div>
                        <div class="ksm-formgrid">
                            @foreach ([
                                'address' => ['Indirizzo', 'Nel piede.'],
                                'phone' => ['Telefono', 'Nel piede.'],
                                'email' => ['Email', 'Nel piede. I messaggi del modulo Contattaci arrivano a questo indirizzo.'],
                            ] as $field => [$label, $hint])
                                <div class="ksm-field">
                                    <label class="ksm-label" for="{{ $field }}">{{ $label }}</label>
                                    <input class="ksm-input" id="{{ $field }}" name="{{ $field }}" value="{{ $value($field) }}">
                                    <small class="ksm-muted">{{ $hint }}</small>
                                    @error($field)<span class="ksm-error">{{ $message }}</span>@enderror
                                </div>
                            @endforeach
                            @foreach (['facebook' => 'Pagina Facebook', 'instagram' => 'Profilo Instagram'] as $network => $label)
                                <div class="ksm-field">
                                    <label class="ksm-label" for="social-{{ $network }}">{{ $label }}</label>
                                    <input class="ksm-input" id="social-{{ $network }}" name="social_links[{{ $network }}]" type="url" placeholder="https://"
                                           value="{{ old('social_links.'.$network, $record->social_links[$network] ?? '') }}">
                                    <small class="ksm-muted">L'icona nel piede compare solo se l'indirizzo è compilato.</small>
                                    @error('social_links.'.$network)<span class="ksm-error">{{ $message }}</span>@enderror
                                </div>
                            @endforeach
                            <div class="ksm-field">
                                <label class="ksm-label" for="site-footer-links-title">Titolo della prima colonna</label>
                                <input class="ksm-input" id="site-footer-links-title" name="site[footer][links_title]" value="{{ $site('footer.links_title') }}" placeholder="{{ $footerDefaults['links_title'] }}">
                            </div>
                            <div class="ksm-field">
                                <label class="ksm-label" for="site-footer-info-title">Titolo della seconda colonna</label>
                                <input class="ksm-input" id="site-footer-info-title" name="site[footer][info_title]" value="{{ $site('footer.info_title') }}" placeholder="{{ $footerDefaults['info_title'] }}">
                            </div>
                        </div>
                    @endif

                    @foreach ($linkGroups[$anchor] as $group => $groupLabel)
                        <h3 style="font-size: .95rem; margin: 8px 0;">{{ $groupLabel }}</h3>
                        <div class="ksm-domain-form__links">
                            @for ($i = 0; $i < \App\Support\Sites\SiteContent::LINK_ROWS; $i++)
                                <input class="ksm-input" name="{{ $name("$group.$i.label") }}" value="{{ $site("$group.$i.label") }}" placeholder="Etichetta" aria-label="{{ $groupLabel }}: etichetta {{ $i + 1 }}">
                                <input class="ksm-input" name="{{ $name("$group.$i.url") }}" value="{{ $site("$group.$i.url") }}" placeholder="/prodotti" aria-label="{{ $groupLabel }}: link {{ $i + 1 }}">
                            @endfor
                        </div>
                        @foreach ($errors->get("site.$group.*") as $messages)
                            <span class="ksm-error">{{ $messages[0] }}</span>
                        @endforeach
                    @endforeach

                    @if ($anchor === 'piede')
                        <div class="ksm-formgrid">
                            <div class="ksm-field">
                                <label class="ksm-label" for="site-footer-copyright">Riga del copyright</label>
                                <input class="ksm-input" id="site-footer-copyright" name="site[footer][copyright]" value="{{ $site('footer.copyright') }}" placeholder="{{ $footerDefaults['copyright'] }}">
                                <small class="ksm-muted">L'ultima riga del piede, a sinistra.</small>
                            </div>
                            <div class="ksm-field">
                                <label class="ksm-label" for="site-footer-legal">Dati legali</label>
                                <input class="ksm-input" id="site-footer-legal" name="site[footer][legal]" value="{{ $site('footer.legal') }}" placeholder="Ragione sociale · P. IVA">
                                <small class="ksm-muted">Accanto al copyright, nell'ultima riga del piede.</small>
                            </div>
                        </div>
                    @endif
                </section>
            @endforeach

            {{-- SEO --}}
            <section class="ksm-card ksm-domain-form__section" id="seo">
                <h2>SEO e condivisione</h2>
                <p>Non si vedono nella pagina: sono quello che mostrano Google e i social quando qualcuno cerca o condivide il sito.</p>
                <div class="ksm-field">
                    <label class="ksm-label" for="site-seo-title">Titolo della pagina iniziale</label>
                    <input class="ksm-input" id="site-seo-title" name="site[seo][title]" value="{{ $site('seo.title') }}" placeholder="Mozzarella di bufala fresca a casa tua" maxlength="255">
                    <small class="ksm-muted">Il titolo nella scheda del browser e nei risultati di Google, solo per la pagina iniziale. Vuoto: il nome del sito seguito da una frase standard.</small>
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="site-seo-description">Descrizione</label>
                    <textarea class="ksm-textarea" id="site-seo-description" name="site[seo][description]" rows="2" maxlength="320">{{ $site('seo.description') }}</textarea>
                    <small class="ksm-muted">Le due righe grigie sotto il titolo nei risultati di Google. Massimo circa 160 caratteri.</small>
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="description">Descrizione del dominio</label>
                    <textarea class="ksm-textarea" id="description" name="description" rows="2">{{ $value('description') }}</textarea>
                    <small class="ksm-muted">Usata quando mancano la descrizione SEO o quella del piede.</small>
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="seo_image">Immagine per la condivisione</label>
                    @if ($site('seo.image'))
                        <img class="ksm-domain-form__preview" src="{{ asset('storage/'.$site('seo.image')) }}" alt="">
                        <label style="display: flex; gap: 6px; align-items: center;"><input type="checkbox" name="remove_seo_image" value="1"> Togli l'immagine</label>
                    @endif
                    <input class="ksm-input" id="seo_image" name="seo_image" type="file" accept="image/*">
                    <small class="ksm-muted">1200 × 630 pixel.</small>
                    @error('seo_image')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
            </section>

            <div class="ksm-domain-form__save">
                <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
            </div>
        </form>
    </div>
@endsection
