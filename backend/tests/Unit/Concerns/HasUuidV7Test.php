<?php

declare(strict_types=1);

use App\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

it('produces UUID v7 ids (version nibble = 7)', function () {
    $model = new class extends Model
    {
        use HasUuidV7;
    };

    $id = $model->newUniqueId();

    // RFC 9562 — 14th hex character is the version nibble.
    expect($id)
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

it('produces time-sortable ids (later id sorts after earlier)', function () {
    $model = new class extends Model
    {
        use HasUuidV7;
    };

    $first = $model->newUniqueId();
    usleep(2000);
    $second = $model->newUniqueId();

    expect(strcmp($first, $second))->toBeLessThan(0);
});
