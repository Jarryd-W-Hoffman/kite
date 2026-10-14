<?php

declare(strict_types=1);

use App\Http\Controllers\CaptureController;
use App\Http\Controllers\DailyNoteController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NoteMentionController;
use App\Http\Controllers\NotePaneController;
use App\Http\Controllers\NotePathController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserEmailResetNotificationController;
use App\Http\Controllers\UserEmailVerificationController;
use App\Http\Controllers\UserEmailVerificationNotificationController;
use App\Http\Controllers\UserPasskeyController;
use App\Http\Controllers\UserPasswordController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\UserTwoFactorAuthenticationController;
use App\Http\Controllers\VaultAttachmentController;
use App\Http\Controllers\VaultController;
use App\Http\Controllers\VaultFileController;
use App\Http\Controllers\VaultFolderController;
use App\Http\Controllers\VaultGraphController;
use App\Http\Controllers\VaultPreviewController;
use App\Http\Controllers\VaultSearchController;
use App\Http\Controllers\VaultStatusController;
use App\Http\Controllers\VaultSyncController;
use App\Http\Controllers\VaultTagController;
use App\Http\Middleware\WatchVault;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', HomeController::class)->name('home');

// Preferences...
Route::put('preferences', [PreferencesController::class, 'update'])->name('preferences.update');

// Quick capture...
Route::get('capture', [CaptureController::class, 'create'])->name('capture.create');
Route::post('capture', [CaptureController::class, 'store'])->name('capture.store');

// Vaults...
Route::get('vaults', [VaultController::class, 'index'])->name('vaults.index');
Route::post('vaults', [VaultController::class, 'store'])->name('vaults.store');
Route::post('vaults/folder', [VaultFolderController::class, 'store'])->name('vault-folder.store');
Route::get('vaults/{vault}', [VaultController::class, 'show'])->middleware(WatchVault::class)->name('vaults.show');
Route::delete('vaults/{vault}', [VaultController::class, 'destroy'])->name('vaults.destroy');
Route::get('vaults/{vault}/tags/{tag}', [VaultTagController::class, 'show'])->where('tag', '.*')->middleware(WatchVault::class)->name('vault-tags.show');
Route::post('vaults/{vault}/daily', [DailyNoteController::class, 'store'])->name('daily-notes.store');
Route::get('vaults/{vault}/status', [VaultStatusController::class, 'show'])->name('vault-status.show');
Route::post('vaults/{vault}/sync', [VaultSyncController::class, 'store'])->name('vault-sync.store');
Route::get('vaults/{vault}/search', [VaultSearchController::class, 'index'])->name('vault-search.index');
Route::post('vaults/{vault}/preview', [VaultPreviewController::class, 'store'])->name('vault-preview.store');
Route::get('vaults/{vault}/files/{path}', [VaultFileController::class, 'show'])->where('path', '.*')->name('vault-files.show');
Route::post('vaults/{vault}/folders', [FolderController::class, 'store'])->name('folders.store');
Route::put('vaults/{vault}/folders', [FolderController::class, 'update'])->name('folders.update');
Route::delete('vaults/{vault}/folders', [FolderController::class, 'destroy'])->name('folders.destroy');
Route::post('vaults/{vault}/attachments', [VaultAttachmentController::class, 'store'])->name('vault-attachments.store');
Route::get('vaults/{vault}/graph', [VaultGraphController::class, 'show'])->middleware(WatchVault::class)->name('vault-graph.show');

// Notes...
Route::scopeBindings()->group(function (): void {
    Route::post('vaults/{vault}/notes', [NoteController::class, 'store'])->name('notes.store');
    Route::get('vaults/{vault}/notes/{note}', [NoteController::class, 'show'])->middleware(WatchVault::class)->name('notes.show');
    Route::put('vaults/{vault}/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::get('vaults/{vault}/notes/{note}/pane', [NotePaneController::class, 'show'])->name('note-panes.show');
    Route::post('vaults/{vault}/notes/{note}/mentions', [NoteMentionController::class, 'store'])->name('note-mentions.store');
    Route::put('vaults/{vault}/notes/{note}/path', [NotePathController::class, 'update'])->name('note-path.update');
    Route::delete('vaults/{vault}/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', fn () => Inertia::render('dashboard'))->name('dashboard');
});

Route::middleware('auth')->group(function (): void {
    // User Profile...
    Route::get('settings', fn () => to_route('user-profile.edit'))->name('settings');
    Route::get('settings/profile', [UserProfileController::class, 'edit'])->name('user-profile.edit');
    Route::patch('settings/profile', [UserProfileController::class, 'update'])->name('user-profile.update');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    // User...
    Route::delete('user', [UserController::class, 'destroy'])->name('user.destroy');

    // User Password...
    Route::get('settings/password', [UserPasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [UserPasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    // Appearance...
    Route::get('settings/appearance', fn () => Inertia::render('appearance/update'))->name('appearance.edit');

    // User Two-Factor Authentication...
    Route::get('settings/two-factor', [UserTwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    // User Passkeys...
    Route::get('settings/passkeys', [UserPasskeyController::class, 'index'])
        ->name('user-passkey.index');
});

Route::middleware('guest')->group(function (): void {
    // User...
    Route::get('register', [UserController::class, 'create'])
        ->name('register');
    Route::post('register', [UserController::class, 'store'])
        ->name('register.store');

    // User Password...
    Route::get('reset-password/{token}', [UserPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [UserPasswordController::class, 'store'])
        ->name('password.store');

    // User Email Reset Notification...
    Route::get('forgot-password', [UserEmailResetNotificationController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [UserEmailResetNotificationController::class, 'store'])
        ->name('password.email');

    // Session...
    Route::get('login', [SessionController::class, 'create'])
        ->name('login');
    Route::post('login', [SessionController::class, 'store'])
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    // User Email Verification...
    Route::get('verify-email', [UserEmailVerificationNotificationController::class, 'create'])
        ->name('verification.notice');
    Route::post('email/verification-notification', [UserEmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // User Email Verification...
    Route::get('verify-email/{id}/{hash}', [UserEmailVerificationController::class, 'update'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Session...
    Route::post('logout', [SessionController::class, 'destroy'])
        ->name('logout');
});

// Passkey Endpoints...
Route::get('.well-known/passkey-endpoints', fn () => response()->json([
    'enroll' => route('user-passkey.index'),
    'manage' => route('user-passkey.index'),
]))->name('well-known.passkeys');
