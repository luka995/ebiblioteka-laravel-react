@extends('layouts.public')

@section('title', 'еБиблиотека | Софтвер за школске библиотеке')
@section('meta_description', 'еБиблиотека је софтвер за школске библиотеке: каталог, издавање, резервације, инвентар, извештаји, разреди и чланске карте.')

@section('content')
<div class="public-shell" id="top">
    @include('public.partials.nav')

    <main>
        @include('public.home.hero')
        @include('public.home.facts')
        @include('public.home.comparison')
        @include('public.home.modules')
        @include('public.home.schools')
        @include('public.home.demo')
        @include('public.home.catalog')
        @include('public.home.news')
        @include('public.home.guide')
        @include('public.home.faq')
        @include('public.home.contact-cta')
    </main>

    @include('public.partials.footer')
</div>
@endsection

@push('head')
    @php
        $faqSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($faq)->map(fn (array $item): array => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ])->all(),
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush
