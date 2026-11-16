<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Lab;
use App\Models\User;

function akunFasilitas(Role $role, ?Lab $lab = null): User
{
    $user = User::factory()->create();
    $user->role = $role;
    $user->lab_id = $lab?->id;
    $user->save();

    return $user;
}

function labFasilitas(string $code): Lab
{
    return Lab::create(['name' => "Lab {$code}", 'code' => $code]);
}

test('asisten lab dapat menambah fasilitas dengan nama bebas di lab-nya', function () {
    $lab = labFasilitas('DS');
    $asisten = akunFasilitas(Role::AsistenLab, $lab);

    foreach ([['Unit PC', 40], ['TV', 2], ['Bangku', 45], ['Meja', 5]] as [$nama, $jumlah]) {
        $this->actingAs($asisten)
            ->post("/labs/{$lab->id}/facilities", ['name' => $nama, 'quantity' => $jumlah])
            ->assertSessionHasNoErrors();
    }

    expect($lab->facilities()->count())->toEqual(4);
    expect($lab->facilities()->where('name', 'Unit PC')->first()->quantity)->toEqual(40);
});

test('asisten lab tidak dapat mengubah fasilitas lab lain', function () {
    $ds = labFasilitas('DS');
    $sd = labFasilitas('SD');
    $asistenSd = akunFasilitas(Role::AsistenLab, $sd);
    $fasilitas = $ds->facilities()->create(['name' => 'TV', 'quantity' => 2]);

    $this->actingAs($asistenSd)
        ->post("/labs/{$ds->id}/facilities", ['name' => 'PC', 'quantity' => 1])
        ->assertForbidden();
    $this->actingAs($asistenSd)
        ->put("/facilities/{$fasilitas->id}", ['name' => 'TV', 'quantity' => 9])
        ->assertForbidden();
    $this->actingAs($asistenSd)
        ->delete("/facilities/{$fasilitas->id}")
        ->assertForbidden();

    expect($fasilitas->fresh()->quantity)->toEqual(2);
});

test('dosen dapat melihat tetapi tidak dapat mengubah fasilitas', function () {
    $lab = labFasilitas('DS');
    $dosen = akunFasilitas(Role::Dosen);

    $this->actingAs($dosen)->get('/facilities')->assertOk();
    $this->actingAs($dosen)
        ->post("/labs/{$lab->id}/facilities", ['name' => 'PC', 'quantity' => 1])
        ->assertForbidden();
});

test('nama fasilitas unik per lab tetapi boleh sama antar lab', function () {
    $ds = labFasilitas('DS');
    $sd = labFasilitas('SD');
    $ketua = akunFasilitas(Role::KetuaLab, $ds);

    $this->actingAs($ketua)
        ->post("/labs/{$ds->id}/facilities", ['name' => 'TV', 'quantity' => 2])
        ->assertSessionHasNoErrors();
    $this->actingAs($ketua)
        ->post("/labs/{$ds->id}/facilities", ['name' => ' TV ', 'quantity' => 1])
        ->assertSessionHasErrors('name');
    $this->actingAs($ketua)
        ->post("/labs/{$sd->id}/facilities", ['name' => 'TV', 'quantity' => 1])
        ->assertSessionHasNoErrors();
});

test('jumlah tidak boleh negatif', function () {
    $lab = labFasilitas('DS');

    $this->actingAs(akunFasilitas(Role::AsistenLab, $lab))
        ->post("/labs/{$lab->id}/facilities", ['name' => 'PC', 'quantity' => -1])
        ->assertSessionHasErrors('quantity');
});

test('perubahan dan penghapusan fasilitas tercatat di audit log', function () {
    $lab = labFasilitas('DS');
    $asisten = akunFasilitas(Role::AsistenLab, $lab);

    $this->actingAs($asisten)->post("/labs/{$lab->id}/facilities", ['name' => 'Meja', 'quantity' => 5]);
    $fasilitas = $lab->facilities()->first();

    $this->actingAs($asisten)->put("/facilities/{$fasilitas->id}", ['name' => 'Meja', 'quantity' => 6]);
    $this->actingAs($asisten)->delete("/facilities/{$fasilitas->id}");

    expect(AuditLog::where('action', 'facility.created')->exists())->toBeTrue();
    expect(AuditLog::where('action', 'facility.updated')->exists())->toBeTrue();
    expect(AuditLog::where('action', 'facility.deleted')->exists())->toBeTrue();
    expect($lab->facilities()->count())->toEqual(0);
});