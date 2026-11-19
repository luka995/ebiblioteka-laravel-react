<?php

return [

    'reset' => [
        'subject' => 'Promena lozinke',
        'greeting' => 'Zdravo!',
        'intro' => 'Primili smo zahtev za promenu lozinke za vaš nalog na eBiblioteka platformi.',
        'action' => 'Promeni lozinku',
        'outro' => 'Ako niste zatražili promenu lozinke, slobodno ignorišite ovu poruku.',
        'subcopy_intro' => 'Ako imate problem sa klikom na dugme „:actionText", kopirajte vezu ispod u pregledač:',
        'expiry' => 'Ova veza ističe za :count minuta.',
    ],

    'account' => [
        'subject' => 'Vaš nalog je kreiran',
        'greeting' => 'Dobrodošli!',
        'intro' => 'Vaš nalog na eBiblioteka platformi je kreiran. U nastavku se nalaze podaci za prijavu.',
        'email_label' => 'E-adresa',
        'password_label' => 'Lozinka',
        'action' => 'Prijavite se',
        'outro' => 'Nakon prijave preporučujemo da promenite lozinku u podešavanjima naloga.',
    ],

    'password_changed' => [
        'subject' => 'Lozinka je promenjena',
        'greeting' => 'Zdravo!',
        'intro' => 'Administrator je promenio lozinku za vaš nalog na eBiblioteka platformi. U nastavku se nalaze novi podaci za prijavu.',
        'email_label' => 'E-adresa',
        'password_label' => 'Nova lozinka',
        'action' => 'Prijavite se',
        'outro' => 'Ako ovu promenu niste očekivali, odmah se obratite administratoru biblioteke.',
    ],

    'contact' => [
        'subject' => 'Nova poruka sa kontakt forme',
        'greeting' => 'Nova poruka sa kontakt forme',
        'intro' => 'Primili ste novu poruku preko kontakt forme na eBiblioteka sajtu.',
        'name_label' => 'Ime',
        'email_label' => 'E-adresa',
        'organization_label' => 'Ustanova ili organizacija',
        'organization_none' => 'Nije navedeno',
        'message_label' => 'Poruka',
    ],

    'library_request' => [
        'subject' => 'Novi zahtev za otvaranje biblioteke — :school',
        'greeting' => 'Novi zahtev za otvaranje biblioteke',
        'intro' => 'Preko sajta je stigao novi zahtev za otvaranje biblioteke. Podaci o školi su u nastavku.',
        'school_label' => 'Škola',
        'type_label' => 'Tip škole',
        'city_label' => 'Mesto',
        'address_label' => 'Adresa',
        'contact_label' => 'Kontakt osoba',
        'email_label' => 'E-adresa',
        'phone_label' => 'Telefon',
        'students_label' => 'Broj učenika',
        'employees_label' => 'Broj zaposlenih',
        'message_label' => 'Poruka',
        'none' => 'Nije navedeno',
    ],

    'circulation' => [
        'labels' => [
            'barcode' => 'Bar-kod',
            'inventory_number' => 'Inventarni broj',
            'library' => 'Biblioteka',
            'pickup_until' => 'Preuzimanje do',
            'expires_at' => 'Rezervacija ističe',
            'my_reservations' => 'Moje rezervacije',
            'library_reservations' => 'Rezervacije biblioteke',
            'issued_at' => 'Datum izdavanja',
            'due_at' => 'Rok vraćanja',
            'due_soon' => 'Uskoro ističe rok',
            'due_today' => 'Rok ističe danas',
            'returned_at' => 'Datum vraćanja',
            'overdue' => 'Prekoračenje',
            'days' => 'dana',
            'none' => 'Nema stavki.',
            'my_loans' => 'Moje pozajmice',
        ],
        'reservation_created_user' => [
            'subject' => 'Rezervacija je uspešno kreirana',
            'intro' => 'Zdravo :name, vaša rezervacija je zabeležena. Primerak vas čeka do navedenog roka.',
        ],
        'reservation_created_staff' => [
            'subject' => 'Nova rezervacija',
            'intro' => 'Korisnik :name je napravio/la novu rezervaciju.',
        ],
        'reservation_cancelled_user' => [
            'subject' => 'Rezervacija je otkazana',
            'intro' => 'Vaša rezervacija je otkazana.',
        ],
        'reservation_cancelled_staff' => [
            'subject' => 'Korisnik je otkazao rezervaciju',
            'intro' => 'Korisnik :name je otkazao/la nepreuzetu rezervaciju.',
        ],
        'reservation_expiring_user' => [
            'subject' => 'Rezervacija uskoro ističe',
            'intro' => 'Vaša rezervacija ističe za približno :hours sati. Preuzmite knjigu na vreme.',
        ],
        'reservation_expired_user' => [
            'subject' => 'Rezervacija je istekla',
            'intro' => 'Vaša rezervacija je istekla i rezervisani primerak je ponovo dostupan.',
        ],
        'loan_issued_user' => [
            'subject' => 'Knjiga je izdata',
            'intro' => 'Zdravo :name, knjiga je izdata. Rok vraćanja je naveden u nastavku.',
        ],
        'loan_issued_staff' => [
            'subject' => 'Potvrda izdavanja',
            'intro' => 'Knjiga je izdata korisniku :name.',
        ],
        'loan_returned_user' => [
            'subject' => 'Knjiga je vraćena',
            'intro' => 'Knjiga je uspešno vraćena.',
        ],
        'loan_returned_staff' => [
            'subject' => 'Potvrda vraćanja',
            'intro' => 'Korisnik :name je vratio/la knjigu.',
        ],
        'loan_due_soon_user' => [
            'subject' => 'Podsetnik: rok vraćanja se približava',
            'intro' => 'Sledeće knjige uskoro dospevaju za vraćanje.',
        ],
        'loan_due_today_user' => [
            'subject' => 'Rok vraćanja je danas',
            'intro' => 'Danas je rok za vraćanje sledećih knjiga.',
        ],
        'loan_overdue_user' => [
            'subject' => 'Pozajmica je prekoračena',
            'intro' => 'Sledeće knjige su prekoračile rok vraćanja.',
        ],
        'staff_daily_digest' => [
            'subject' => 'Dnevni pregled cirkulacije',
            'intro' => 'Pregled cirkulacije za biblioteku :library.',
            'due_title' => 'Knjige za vraćanje za 3 dana',
            'overdue_title' => 'Prekoračene pozajmice',
            'reservations_title' => 'Rezervacije koje ističu',
        ],
    ],

    'school_year' => [
        'upcoming' => [
            'subject' => 'Nova školska godina :name počinje za :days dana',
            'title' => 'Nova školska godina :name',
            'intro' => 'Obaveštavamo vas da će nova školska godina :name biti automatski aktivirana za :days dana.',
            'active_year' => 'Trenutno aktivna godina je :name.',
            'starts_on' => 'Početak nove godine: :date.',
            'prepare' => 'Pripremite odeljenja i raspored učenika na vreme.',
            'action' => 'Otvori eBiblioteku',
        ],
        'activated' => [
            'subject' => 'Školska godina :name je sada aktivna',
            'title' => 'Školska godina :name je sada aktivna',
            'intro' => 'Aktivna školska godina je promenjena na :name.',
            'previous_year' => 'Prethodno aktivna školska godina bila je :name.',
            'starts_on' => 'Ova školska godina počela je: :date.',
            'action' => 'Otvori eBiblioteku',
        ],
    ],

    'brand' => [
        'name' => 'eBiblioteka',
        'tagline' => 'svaka knjiga je nova pustolovina',
    ],

];
