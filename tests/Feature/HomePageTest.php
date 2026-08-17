<?php

test('the landing page renders for guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->has('destacados', 6)
            ->where('totalStock', 22)
            ->where('site.ciudad', 'Rivera')
            ->where('site.whatsapp', config('alfa.whatsapp'))
        );
});

test('the featured vehicles carry everything the cards render', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('destacados.0', fn ($vehiculo) => $vehiculo
                ->where('slug', 'strada-freedom-24')
                ->where('marca', 'Fiat')
                ->where('precio', 23900)
                ->where('moneda', 'USD')
                ->where('estado', 'publicado')
                ->etc()
            )
        );
});
