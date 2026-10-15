<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreVaultPassphraseRequest;
use App\Http\Requests\UpdateVaultPassphraseRequest;
use App\Models\Vault;
use App\Services\NoteLocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final readonly class VaultPassphraseController
{
    /**
     * Choose the passphrase that locks notes in this vault, unlocking it straight away. The
     * recovery key made with it is returned this once, to be saved somewhere safe.
     */
    public function store(StoreVaultPassphraseRequest $request, Vault $vault, NoteLocks $locks): JsonResponse
    {
        throw_if($locks->isSetUp($vault), ValidationException::withMessages([
            'passphrase' => __('This vault already has a passphrase.'),
        ]));

        $key = $locks->setUp($vault, $request->string('passphrase')->toString());

        return response()->json([
            'key' => base64_encode($key),
            'recovery_key' => $locks->createRecoveryKey($vault, $key),
        ], 201);
    }

    /**
     * Change the passphrase, given the current one or, when it's forgotten, the recovery key. A
     * recovery key that has been used is replaced with a new one, returned this once.
     */
    public function update(UpdateVaultPassphraseRequest $request, Vault $vault, NoteLocks $locks): JsonResponse
    {
        $recoveryKey = $request->string('recovery_key')->toString();
        $recovering = $recoveryKey !== '';

        $key = $recovering
            ? $locks->unlockWithRecoveryKey($vault, $recoveryKey)
            : $locks->unlock($vault, $request->string('current_passphrase')->toString());

        throw_if($key === null, ValidationException::withMessages($recovering
            ? ['recovery_key' => __('That recovery key is not right.')]
            : ['current_passphrase' => __('That passphrase is not right.')]));

        $locks->changePassphrase($vault, $key, $request->string('passphrase')->toString());

        return response()->json([
            'key' => base64_encode($key),
            'recovery_key' => $recovering ? $locks->createRecoveryKey($vault, $key) : null,
        ]);
    }
}
