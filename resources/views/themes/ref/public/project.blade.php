@extends('themes.ref.layouts.public')

@section('title', 'О е-Библиотеци | Ебиблиотека Академије Филиповић')

@section('content')
<main>
    @include('themes.ref.public.partials.nav')

    <section class="page-hero">
        <div class="container page-hero-inner">
            <div>
                <div class="crumbs"><a href="{{ route('home') }}">Почетна</a><span>/</span><span class="current">О е-Библиотеци</span></div>
                <span class="section-kicker">НОВА ВЕРЗИЈА ЕБИБЛИОТЕКЕ</span>
                <h1>Библиотека <em>која прати ваш дан</em></h1>
                <p class="page-lead">Академија Филиповић развија нову верзију софтвера еБиблиотека — са мање администрације за библиотекаре и једноставнијим приступом књигама за ученике, наставнике и родитеље.</p>
            </div>
            <div class="page-hero-aside">
                <div class="hero-stat-card">
                    <small>Ради од</small>
                    <strong>2003.</strong>
                    <span>две деценије са школама</span>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="lead-heading">
                <div>
                    <span class="section-kicker">ЗАШТО НОВА ВЕРЗИЈА</span>
                    <h2>Технологија треба да <em>склони препреке</em></h2>
                </div>
                <p>Нова верзија задржава оно што је проверено у пракси и уводи брже токове рада.</p>
            </div>
            <div class="stat-proof">
                <div><strong>2003.</strong><span>Академија Филиповић почиње рад</span></div>
                <div><strong>2013.</strong><span>еБиблиотека добија међународно признање</span></div>
                <div><strong>80.000+</strong><span>полазника програма стручног усавршавања</span></div>
            </div>
        </div>
    </section>

    <section class="section section--tint">
        <div class="container">
            <div class="lead-heading">
                <div>
                    <span class="section-kicker">ФУНКЦИОНАЛНОСТИ</span>
                    <h2>Све што је важно. <em>Без сувишних корака</em></h2>
                </div>
                <p>Нова еБиблиотека повезује библиотекаре, школе и читаоце око истих, јасних података.</p>
            </div>

            <div class="feature-list">
                <article class="feature">
                    <span class="feature-index">01</span>
                    <div>
                        <h3>Скенирање књига мобилним телефоном</h3>
                        <p>Камера телефона препознаје бар-код књиге, па библиотекар може брже да пронађе примерак и евидентира задужење или раздужење — без посебног читача на сваком радном месту.</p>
                    </div>
                </article>
                <article class="feature">
                    <span class="feature-index">02</span>
                    <div>
                        <h3>Каталог доступан на сваком уређају</h3>
                        <p>Претрага по наслову, аутору, издавачу и другим подацима ради прегледно на рачунару, таблету и телефону. Читалац одмах види шта је доступно.</p>
                    </div>
                </article>
                <article class="feature">
                    <span class="feature-index">03</span>
                    <div>
                        <h3>Једноставне резервације и рокови</h3>
                        <p>Задужења, раздужења и резервације прате један јасан ток. Обавештења подсећају на рокове и информишу корисника када је тражена књига доступна.</p>
                    </div>
                </article>
                <article class="feature">
                    <span class="feature-index">04</span>
                    <div>
                        <h3>Подаци који раде за библиотеку</h3>
                        <p>Извештаји и статистике о фонду, позајмицама, најтраженијим насловима и раду библиотеке настају из евиденције коју већ водите.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="lead-heading">
                <div>
                    <span class="section-kicker">ЗА КОГА ЈЕ ЕБИБЛИОТЕКА</span>
                    <h2>Један систем. <em>Три боља погледа</em></h2>
                </div>
            </div>
            <div class="audience-grid">
                <div class="audience-card">
                    <h3>Библиотекар</h3>
                    <p><strong>Води фонд, не папир.</strong> Брже евидентирање, тачнији подаци и преглед корисника, рокова и доступности на једном месту.</p>
                </div>
                <div class="audience-card">
                    <h3>Школа</h3>
                    <p><strong>Види ширу слику.</strong> Уређен каталог, поуздани извештаји и могућност повезивања више библиотека у једну мрежу.</p>
                </div>
                <div class="audience-card">
                    <h3>Читалац</h3>
                    <p><strong>Пронађи књигу без чекања.</strong> Претрага, доступност, резервација и преглед задужења са рачунара или мобилног телефона.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="quote-section">
        <div class="container quote-inner">
            <blockquote>„Добра библиотека почиње добрим подацима.”</blockquote>
            <p>Истражите јавни каталог нове еБиблиотеке и погледајте како библиотеке, категорије и наслови изгледају у новом дигиталном простору.</p>
            <a class="btn btn--gold" href="{{ route('libraries.index') }}">Отворите каталог</a>
        </div>
    </section>

    @include('themes.ref.public.partials.footer')
</main>
@endsection
