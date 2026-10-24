<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RewriteLockedLinks;
use App\Http\Requests\StoreVaultKeyRequest;
use App\Models\Vault;
use App\Services\NoteLocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final readonly class VaultKeyController
{
    /**
     * Unlock the vault's locked notes: the key for a passphrase, which the app keeps in memory
     * and sends with its requests until it locks again.
     */
    public function store(StoreVaultKeyRequest $request, Vault $vault, NoteLocks $locks, RewriteLockedLinks $rewriteLockedLinks): JsonResponse
    {
        $key = $locks->unlock($vault, $request->string('passphrase')->toString());

        throw_if($key === null, ValidationException::withMessages([
            'passphrase' => __('That passphrase is not right.'),
        ]));

        $rewriteLockedLinks->handle($vault, $key);

        return response()->json(['key' => base64_encode($key)]);
    }
}
