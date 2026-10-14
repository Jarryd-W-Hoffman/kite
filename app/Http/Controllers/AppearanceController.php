<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ApplyAppearance;
use App\Http\Requests\UpdateAppearanceRequest;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;

final readonly class AppearanceController
{
    public function update(UpdateAppearanceRequest $request, Settings $settings, ApplyAppearance $applyAppearance): JsonResponse
    {
        $appearance = $request->string('appearance')->toString();

        $settings->update(['appearance' => $appearance]);
        $applyAppearance->handle($appearance);

        return response()->json(['appearance' => $appearance]);
    }
}
