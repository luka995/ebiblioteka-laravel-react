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
    'current_password' => 'Trenutna lozinka nije ispravna.',
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
        'cannot_force_delete' => 'Nalog se ne može trajno obrisati jer postoje povezani zapisi.',
        'active_library_required' => 'Morate imati izabranu aktivnu biblioteku.',
        'active_library_invalid' => 'Izabrana biblioteka ne pripada vašem nalogu.',
        'region_has_places' => 'Regija se ne može obrisati jer sadrži mesta.',
        'place_has_libraries' => 'Mesto se ne može obrisati jer sadrži biblioteke.',
        'tag_duplicate' => 'Tag sa tim nazivom već postoji u izabranoj biblioteci.',
        'tag_library_mismatch' => 'Tag ne pripada biblioteci u kojoj je korisnik član.',
        'library_not_managed' => 'Nemate pristup ovoj biblioteci.',
        'user_not_library_member' => 'Korisnik nije član izabrane biblioteke.',
        'memberships_none_eligible' => 'Nijedan izabrani korisnik nema odgovarajuće članstvo u aktivnoj biblioteci.',
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
        'current_password' => 'trenutna lozinka',
        'role' => 'uloga',
        'libraries' => 'biblioteke',
        'bar_code' => 'bar-kod',
        'jmbg' => 'JMBG',
        'libraries.*' => 'biblioteka',
    ],

];
