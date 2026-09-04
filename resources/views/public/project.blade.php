@extends('layouts.public')

@section('title', 'О пројекту | еБиблиотека')

@section('content')
<div class="public-shell catalog-shell project-shell" id="top">
    @include('public.partials.nav')

    <main>
        <section class="project-hero public-wrap">
            <div class="project-hero-main">
                <div class="breadcrumbs"><a href="{{ route('home') }}">Почетна</a><span>/</span><strong>О пројекту</strong></div>
                <span class="section-label">НОВА ВЕРЗИЈА еБИБЛИОТЕКЕ</span>
                <h1 class="display">Библиотека<br><span class="serif">која прати</span><br>ваш дан.</h1>
                <p class="project-lead">Академија Филиповић развија нову верзију софтвера <strong>еБиблиотека.rs</strong> — са мање администрације за библиотекаре и једноставнијим приступом књигама за ученике, наставнике и родитеље.</p>
                <div class="project-hero-actions"><a class="button button-blue" href="{{ route('libraries.index') }}">Истражите каталог <span>↗</span></a><a class="text-link" href="https://akademijafilipovic.com" target="_blank" rel="noreferrer">Сазнајте више о Академији <span>↗</span></a></div>
            </div>
            <div class="project-hero-panel">
                <div class="project-panel-top"><span>еБиблиотека</span><span>01 / 04</span></div>
                <div class="project-panel-title">Од полице<br>до података.</div>
                <div class="project-panel-flow"><div><b>01</b><span>Скенирај</span><small>Камером телефона</small></div><i>↓</i><div><b>02</b><span>Евидентирај</span><small>Задужење или раздужење</small></div><i>↓</i><div><b>03</b><span>Прати</span><small>Рокове и доступност</small></div></div>
                <div class="project-panel-footer"><span class="project-panel-dot"></span> Једноставнији рад, сваког дана <strong>↗</strong></div>
            </div>
        </section>

        <section class="project-intro public-wrap">
            <div class="project-intro-heading"><span class="section-label">ЗАШТО НОВА ВЕРЗИЈА</span><h2 class="display">Технологија треба да<br><span class="serif">склони препреке.</span></h2></div>
            <div class="project-intro-copy"><p>Академија Филиповић више од две деценије развија решења за савремено образовање. еБиблиотека је настала из потребе да школска библиотека буде прегледна, доступна и корисна у свакодневном раду — не још једна компликована обавеза.</p><p>Нова верзија задржава оно што је проверено у пракси: каталог, бар-код евиденцију, задужење, раздужење, резервације и извештаје. Истовремено уводи брже токове рада и искуство прилагођено телефону.</p></div>
            <div class="project-proof"><div><strong>2003.</strong><span>Академија Филиповић<br>почиње рад</span></div><div><strong>2013.</strong><span>еБиблиотека добија<br>међународно признање</span></div><div><strong>80.000+</strong><span>полазника програма<br>стручног усавршавања</span></div></div>
        </section>

        <section class="project-capabilities">
            <div class="public-wrap">
                <div class="project-capabilities-heading"><div><span class="section-label">ФУНКЦИОНАЛНОСТИ</span><h2 class="display">Све што је важно.<br><span class="serif">Без сувишних корака.</span></h2></div><p>Нова еБиблиотека повезује библиотекаре, школе и читаоце око истих, јасних података.</p></div>
                <div class="project-capability-list">
                    <article class="project-capability"><span class="project-capability-number">01</span><div class="project-capability-icon">⌁</div><div><h3>Скенирање књига мобилним телефоном</h3><p>Камера телефона препознаје бар-код књиге, па библиотекар може брже да пронађе примерак и евидентира задужење или раздужење — без посебног читача на сваком радном месту.</p></div><span class="project-capability-arrow">↗</span></article>
                    <article class="project-capability"><span class="project-capability-number">02</span><div class="project-capability-icon">⌕</div><div><h3>Каталог доступан на сваком уређају</h3><p>Претрага по наслову, аутору, издавачу и другим подацима ради прегледно на рачунару, таблету и телефону. Читалац одмах види шта је доступно.</p></div><span class="project-capability-arrow">↗</span></article>
                    <article class="project-capability"><span class="project-capability-number">03</span><div class="project-capability-icon">↔</div><div><h3>Једноставне резервације и рокови</h3><p>Задужења, раздужења и резервације прате један јасан ток. Обавештења подсећају на рокове и информишу корисника када је тражена књига доступна.</p></div><span class="project-capability-arrow">↗</span></article>
                    <article class="project-capability"><span class="project-capability-number">04</span><div class="project-capability-icon">◎</div><div><h3>Подаци који раде за библиотеку</h3><p>Извештаји и статистике о фонду, позајмицама, најтраженијим насловима и раду библиотеке настају из евиденције коју већ водите.</p></div><span class="project-capability-arrow">↗</span></article>
                </div>
            </div>
        </section>

        <section class="project-audience public-wrap">
            <div class="project-audience-heading"><span class="section-label">ЗА КОГА ЈЕ еБИБЛИОТЕКА</span><h2 class="display">Један систем.<br><span class="serif">Три боља погледа.</span></h2></div>
            <div class="project-audience-list"><div><span>01 / БИБЛИОТЕКАР</span><p><strong>Води фонд, не папир.</strong> Брже евидентирање, тачнији подаци и преглед корисника, рокова и доступности на једном месту.</p></div><div><span>02 / ШКОЛА</span><p><strong>Види ширу слику.</strong> Уређен каталог, поуздани извештаји и могућност повезивања више библиотека у једну мрежу.</p></div><div><span>03 / ЧИТАЛАЦ</span><p><strong>Пронађи књигу без чекања.</strong> Претрага, доступност, резервација и преглед задужења са рачунара или мобилног телефона.</p></div></div>
        </section>

        <section class="project-cta public-wrap">
            <div><span class="section-label">ПОГЛЕДАЈТЕ КАКО РАДИ</span><h2 class="display">Добра библиотека<br><span class="serif">почиње добрим подацима.</span></h2></div>
            <div class="project-cta-copy"><p>Истражите јавни каталог нове еБиблиотеке и погледајте како библиотеке, категорије и наслови изгледају у новом дигиталном простору.</p><a class="button button-yellow" href="{{ route('libraries.index') }}">Отворите каталог <span>↗</span></a></div>
        </section>
    </main>

    @include('public.partials.footer')
</div>
@endsection
