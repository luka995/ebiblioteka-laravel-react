<section class="school-section" id="skole">
    <div class="public-wrap school-inner">
        <div class="school-copy">
            <span class="section-label">ПРИЛАГОЂЕНО ОБРАЗОВНОМ СИСТЕМУ</span>
            <h2 class="display">Направљено за <span class="serif">школски образовни систем.</span></h2>
            <p>еБиблиотека прати структуру основне школе (1–8. разред) и средње школе (1–4. разред), са одељењима и школским годинама какве школе стварно воде.</p>
            <div class="school-points">
                <div>
                    <span>01</span>
                    <p><strong>Разреди и одељења</strong>Ученици су повезани са одељењем и школском годином, уз историју распореда и прелазак у следећи разред.</p>
                </div>
                <div>
                    <span>02</span>
                    <p><strong>Оба писма, један налог</strong>Ћирилица и латиница раде заједно. Сваки корисник бира приказ који му одговара.</p>
                </div>
                <div>
                    <span>03</span>
                    <p><strong>Извештаји за школу</strong>Годишњи извештаји позајмица и резервација, инвентарна књига и књига ревизије.</p>
                </div>
            </div>
            <a class="button button-yellow" href="{{ route('library-requests.create', [], false) }}">Региструјте школу <span>↗</span></a>
        </div>

        <div class="school-card">
            <div class="school-card-top"><span>РАЗРЕДИ · ПРИКАЗ ИЗ ДЕМО ШКОЛЕ</span><span>2026/2027</span></div>
            <div class="school-chips">
                @foreach ([['I-1', '24 ученика'], ['I-2', '23 ученика'], ['II-1', '25 ученика'], ['II-2', '24 ученика'], ['III-1', '24 ученика'], ['III-2', '22 ученика']] as [$class, $students])
                    <div class="school-chip"><strong>{{ $class }}</strong><small>{{ $students }}</small></div>
                @endforeach
            </div>
            <div class="school-types">
                <span>ПОДРЖАНИ ТИПОВИ ШКОЛА</span>
                <div>
                    @foreach ($schoolTypes as $schoolType)
                        <span class="school-type">{{ $schoolType }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
