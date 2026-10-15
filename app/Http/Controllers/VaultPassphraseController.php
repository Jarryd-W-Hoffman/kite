<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreVaultPassphraseRequest;
use App\Models\Vault;
use App\Services\NoteLocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final readonly class VaultPassphraseController
{
    /**
     * Choose the passphrase that locks notes in this vault, unlocking it straight away.
     */
    public function store(StoreVaultPassphraseRequest $request, Vault $vault, NoteLocks $locks): JsonResponse
    {
        throw_if($locks->isSetUp($vault), ValidationException::withMessages([
            'passphrase' => __('This vault already has a passphrase.'),
        ]));

        $key = $locks->setUp($vault, $request->string('passphrase')->toString());

        return response()->json(['key' => base64_encode($key)], 201);
    }
}
