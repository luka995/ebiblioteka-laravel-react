<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Skraceni set kljuceva koje servis vraca na najcesce koriscenim pravilima.
    |
    */

    'accepted' => 'Polje :attribute mora biti prihvaćeno.',
    'array' => 'Polje :attribute mora biti niz.',
    'confirmed' => 'Potvrda polja :attribute se ne poklapa.',
    'digits' => 'Polje :attribute mora imati tačno :digits cifara.',
    'email' => 'Polje :attribute mora biti ispravna email adresa.',
    'exists' => 'Izabrana vrednost za :attribute nije važeća.',
    'integer' => 'Polje :attribute mora biti ceo broj.',
    'lowercase' => 'Polje :attribute mora biti napisano malim slovima.',
    'max' => [
        'string' => 'Polje :attribute ne sme imati više od :max karaktera.',
    ],
    'min' => [
        'string' => 'Polje :attribute mora imati najmanje :min karaktera.',
    ],
    'regex' => 'Format polja :attribute nije ispravan.',
    'required' => 'Polje :attribute je obavezno.',
    'string' => 'Polje :attribute mora biti tekst.',
    'unique' => 'Polje :attribute već je zauzeto.',

    'custom' => [
        'role_not_assignable' => 'Ova uloga nije dostupna za dodelu sa vašeg naloga.',
        'place_duplicate' => 'Mesto sa tim nazivom već postoji u izabranoj regiji.',
        'cannot_delete_self' => 'Ne možete obrisati sopstveni nalog.',
    ],

    'attributes' => [
        'name' => 'naziv',
        'address' => 'adresa',
        'city' => 'grad',
        'post_code' => 'poštanski broj',
        'work_time' => 'radno vreme',
        'place_id' => 'mesto',
        'first_name' => 'ime',
        'last_name' => 'prezime',
        'username' => 'korisničko ime',
        'email' => 'email adresa',
        'password' => 'lozinka',
        'role' => 'uloga',
        'libraries' => 'biblioteke',
        'bar_code' => 'bar-kod',
        'jmbg' => 'JMBG',
        'libraries.*' => 'biblioteka',
    ],

];
