<?php

test('the privacy policy renders for guests', function () {
    $this->get(route('politica-privacidad'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('politica-privacidad')
            ->where('site.nombre', 'Alfa Automotores')
            ->where('site.email', 'alfa@alfaautomotores.net')
        );
});

test('every public page shares the contact email', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('site.email', config('alfa.email'))
            ->etc()
        );
});
