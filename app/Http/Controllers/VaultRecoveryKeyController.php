<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreVaultRecoveryKeyRequest;
use App\Models\Vault;
use App\Services\NoteLocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final readonly class VaultRecoveryKeyController
{
    /**
     * Make a new recovery key, so the one before it stops working. Asks for the passphrase again
     * rather than trusting an unlocked window.
     */
    public function store(StoreVaultRecoveryKeyRequest $request, Vault $vault, NoteLocks $locks): JsonResponse
    {
        $key = $locks->unlock($vault, $request->string('passphrase')->toString());

        throw_if($key === null, ValidationException::withMessages([
            'passphrase' => __('That passphrase is not right.'),
        ]));

        return response()->json(['recovery_key' => $locks->createRecoveryKey($vault, $key)], 201);
    }
}
