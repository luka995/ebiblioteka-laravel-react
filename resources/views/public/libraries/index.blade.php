@extends('layouts.public')

@section('title', 'Biblioteke | eBiblioteka')

@section('content')
<div class="public-shell catalog-shell" id="top">
    @include('public.partials.nav')
    <main>
        <section class="catalog-hero public-wrap">
            <div><div class="breadcrumbs"><a href="{{ route('home') }}">Početna</a><span>/</span><strong>Biblioteke</strong></div><span class="section-label">MREŽA BIBLIOTEKA</span><h1 class="display">Pronađite biblioteku<br><span class="serif">za sebe.</span></h1><p>Pretražite biblioteke, otvorite njihove kategorije i pronađite knjigu koja čeka na vas.</p></div>
            <div class="catalog-doodle" aria-hidden="true"><span>✦</span><strong>listaj<br>radoznalo</strong><i></i></div>
        </section>
        <section class="public-wrap library-search-panel">
            <div><span class="section-label">ISTRAŽITE KOLEKCIJE</span><h2 class="display">Koju policu<br><span class="serif">otvaramo?</span></h2></div>
            <form class="catalog-search" action="{{ route('libraries.index') }}" method="get"><label for="library-search">Naziv biblioteke, grad ili adresa</label><div><span>⌕</span><input id="library-search" name="q" value="{{ $query }}" placeholder="Na primer: Novi Sad" type="search"><button type="submit">Pretraži <span>↗</span></button></div></form>
        </section>
        <section class="public-wrap catalog-results">
            <div class="results-heading"><div><span class="section-label">{{ $libraries->count() }} {{ $libraries->count() === 1 ? 'BIBLIOTEKA' : 'BIBLIOTEKE' }}</span><h2 class="display">Biblioteke koje<br><span class="serif">možete posetiti.</span></h2></div><p>Izaberite biblioteku da vidite njene kategorije i knjige.</p></div>
            @if ($libraries->isEmpty())
                <div class="empty-catalog"><span>⌕</span><h3>Nismo pronašli tu biblioteku.</h3><p>Pokušajte sa drugim nazivom ili gradom.</p><a class="button button-blue" href="{{ route('libraries.index') }}">Prikaži sve biblioteke</a></div>
            @else
                <div class="library-directory">
                    @foreach ($libraries as $index => $library)
                        <a class="directory-card" href="{{ route('libraries.show', $library['slug']) }}"><span class="directory-number">0{{ $index + 1 }}</span><span class="directory-emblem" style="background: {{ $library['color'] }}">{{ $library['mark'] }}</span><span class="directory-copy"><strong>{{ $library['name'] }}</strong><small>{{ $library['city'] }} · {{ $library['address'] }}</small><span>{{ count($library['categories']) }} kategorija · {{ $library['count'] }}</span></span><span class="directory-arrow">↗</span></a>
                    @endforeach
                </div>
            @endif
        </section>
    </main>
    @include('public.partials.footer')
</div>
@endsection
