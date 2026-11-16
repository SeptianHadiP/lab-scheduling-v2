<?php

use App\Enums\Role;
use App\Models\Lab;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('public laboratory page displays active laboratories and their public details', function () {
    $lab = Lab::create([
        'name' => 'Laboratorium Data Science',
        'code' => 'DS',
    ]);
    $lab->facilities()->create([
        'name' => 'Komputer',
        'quantity' => 30,
        'unit' => 'unit',
        'note' => 'PC untuk praktikum',
    ]);

    $head = User::factory()->create(['name' => 'Kepala Laboratorium']);
    $head->role = Role::KetuaLab;
    $head->lab()->associate($lab);
    $head->save();

    $assistant = User::factory()->create(['name' => 'Asisten Laboratorium']);
    $assistant->role = Role::AsistenLab;
    $assistant->lab()->associate($lab);
    $assistant->save();

    $inactiveAssistant = User::factory()->create(['name' => 'Asisten Nonaktif']);
    $inactiveAssistant->role = Role::AsistenLab;
    $inactiveAssistant->is_active = false;
    $inactiveAssistant->lab()->associate($lab);
    $inactiveAssistant->save();

    $this->get('/laboratorium')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('laboratorium')
            ->has('labs', 1)
            ->where('labs.0.name', 'Laboratorium Data Science')
            ->where('labs.0.facilities.0.name', 'Komputer')
            ->where('labs.0.facilities.0.quantity', 30)
            ->where('labs.0.head', 'Kepala Laboratorium')
            ->where('labs.0.assistants', ['Asisten Laboratorium'])
            ->etc(),
        );
});

test('public laboratory page does not display inactive laboratories', function () {
    Lab::create([
        'name' => 'Laboratorium Nonaktif',
        'code' => 'NA',
        'is_active' => false,
    ]);

    $this->get('/laboratorium')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('laboratorium')
            ->has('labs', 0)
            ->etc(),
        );
});
