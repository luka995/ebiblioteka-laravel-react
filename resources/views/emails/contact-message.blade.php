<h1>Nova poruka sa kontakt forme</h1>

<p><strong>Ime:</strong> {{ $data['name'] }}</p>
<p><strong>E-mail:</strong> {{ $data['email'] }}</p>
<p><strong>Ustanova ili organizacija:</strong> {{ $data['organization'] ?: 'Nije navedeno' }}</p>

<p><strong>Poruka:</strong></p>
<p>{!! nl2br(e($data['message'])) !!}</p>
