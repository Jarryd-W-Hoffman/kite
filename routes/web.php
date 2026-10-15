<?php

declare(strict_types=1);

use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\CanvasCardController;
use App\Http\Controllers\CanvasController;
use App\Http\Controllers\CaptureController;
use App\Http\Controllers\ColourGroupController;
use App\Http\Controllers\ColourGroupOrderController;
use App\Http\Controllers\ColourGroupPreviewController;
use App\Http\Controllers\DailyNoteController;
use App\Http\Controllers\DiagnosticsController;
use App\Http\Controllers\DiagnosticsLogController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NoteCommentController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NoteExportController;
use App\Http\Controllers\NoteExtractionController;
use App\Http\Controllers\NoteFrontmatterController;
use App\Http\Controllers\NoteGraphController;
use App\Http\Controllers\NoteLockController;
use App\Http\Controllers\NoteMentionController;
use App\Http\Controllers\NoteMergeController;
use App\Http\Controllers\NotePaneController;
use App\Http\Controllers\NotePathController;
use App\Http\Controllers\NotePropertiesController;
use App\Http\Controllers\NotePublicationController;
use App\Http\Controllers\NoteStarController;
use App\Http\Controllers\NoteTaskController;
use App\Http\Controllers\NoteVersionController;
use App\Http\Controllers\NoteVersionRestoreController;
use App\Http\Controllers\NotionImportController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TableViewController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TemplateController;
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
use App\Http\Controllers\VaultKeyController;
use App\Http\Controllers\VaultPassphraseController;
use App\Http\Controllers\VaultPreviewController;
use App\Http\Controllers\VaultRecoveryKeyController;
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
Route::put('appearance', [AppearanceController::class, 'update'])->name('appearance.update');
Route::get('diagnostics', [DiagnosticsController::class, 'show'])->name('diagnostics.show');
Route::post('diagnostics/logs', [DiagnosticsLogController::class, 'store'])->name('diagnostics-logs.store');

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
Route::post('vaults/{vault}/templates', [TemplateController::class, 'store'])->name('templates.store');
Route::get('vaults/{vault}/templates/{path}', [TemplateController::class, 'show'])->where('path', '.*')->name('templates.show');
Route::put('vaults/{vault}/folders', [FolderController::class, 'update'])->name('folders.update');
Route::delete('vaults/{vault}/folders', [FolderController::class, 'destroy'])->name('folders.destroy');
Route::post('vaults/{vault}/attachments', [VaultAttachmentController::class, 'store'])->name('vault-attachments.store');
Route::get('vaults/{vault}/colour-groups/preview', [ColourGroupPreviewController::class, 'show'])->name('colour-group-previews.show');
Route::post('vaults/{vault}/colour-groups', [ColourGroupController::class, 'store'])->name('colour-groups.store');
Route::put('vaults/{vault}/colour-groups/order', [ColourGroupOrderController::class, 'update'])->name('colour-group-order.update');
Route::post('vaults/{vault}/views', [TableViewController::class, 'store'])->name('table-views.store');
Route::post('vaults/{vault}/passphrase', [VaultPassphraseController::class, 'store'])->name('vault-passphrase.store');
Route::middleware('throttle:vault-passphrase')->group(function (): void {
    Route::put('vaults/{vault}/passphrase', [VaultPassphraseController::class, 'update'])->name('vault-passphrase.update');
    Route::post('vaults/{vault}/key', [VaultKeyController::class, 'store'])->name('vault-key.store');
    Route::post('vaults/{vault}/recovery-key', [VaultRecoveryKeyController::class, 'store'])->name('vault-recovery-key.store');
});
Route::post('vaults/{vault}/imports/notion', [NotionImportController::class, 'store'])->name('notion-imports.store');
Route::get('vaults/{vault}/tasks', [TaskController::class, 'index'])->middleware(WatchVault::class)->name('tasks.index');
Route::get('vaults/{vault}/graph', [VaultGraphController::class, 'show'])->middleware(WatchVault::class)->name('vault-graph.show');
Route::post('vaults/{vault}/canvases', [CanvasController::class, 'store'])->name('canvases.store');
Route::get('vaults/{vault}/canvases/{path}', [CanvasController::class, 'show'])->where('path', '.*')->middleware(WatchVault::class)->name('canvases.show');
Route::put('vaults/{vault}/canvases/{path}', [CanvasController::class, 'update'])->where('path', '.*')->name('canvases.update');
Route::delete('vaults/{vault}/canvases/{path}', [CanvasController::class, 'destroy'])->where('path', '.*')->name('canvases.destroy');
Route::get('vaults/{vault}/canvas-cards', [CanvasCardController::class, 'index'])->name('canvas-cards.index');

// Notes...
Route::scopeBindings()->group(function (): void {
    Route::put('vaults/{vault}/colour-groups/{colourGroup}', [ColourGroupController::class, 'update'])->whereNumber('colourGroup')->name('colour-groups.update');
    Route::delete('vaults/{vault}/colour-groups/{colourGroup}', [ColourGroupController::class, 'destroy'])->whereNumber('colourGroup')->name('colour-groups.destroy');
    Route::post('vaults/{vault}/notes', [NoteController::class, 'store'])->name('notes.store');
    Route::get('vaults/{vault}/notes/{note}', [NoteController::class, 'show'])->middleware(WatchVault::class)->name('notes.show');
    Route::put('vaults/{vault}/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::get('vaults/{vault}/notes/{note}/pane', [NotePaneController::class, 'show'])->name('note-panes.show');
    Route::get('vaults/{vault}/notes/{note}/graph', [NoteGraphController::class, 'show'])->name('note-graphs.show');
    Route::post('vaults/{vault}/notes/{note}/mentions', [NoteMentionController::class, 'store'])->name('note-mentions.store');
    Route::put('vaults/{vault}/notes/{note}/path', [NotePathController::class, 'update'])->name('note-path.update');
    Route::post('vaults/{vault}/notes/{note}/extractions', [NoteExtractionController::class, 'store'])->name('note-extractions.store');
    Route::put('vaults/{vault}/notes/{note}/lock', [NoteLockController::class, 'update'])->name('note-lock.update');
    Route::delete('vaults/{vault}/notes/{note}/lock', [NoteLockController::class, 'destroy'])->name('note-lock.destroy');
    Route::post('vaults/{vault}/notes/{note}/merges', [NoteMergeController::class, 'store'])->name('note-merges.store');
    Route::get('vaults/{vault}/views/{tableView}', [TableViewController::class, 'show'])->whereNumber('tableView')->middleware(WatchVault::class)->name('table-views.show');
    Route::put('vaults/{vault}/views/{tableView}', [TableViewController::class, 'update'])->whereNumber('tableView')->name('table-views.update');
    Route::delete('vaults/{vault}/views/{tableView}', [TableViewController::class, 'destroy'])->whereNumber('tableView')->name('table-views.destroy');
    Route::put('vaults/{vault}/notes/{note}/star', [NoteStarController::class, 'update'])->name('note-star.update');
    Route::put('vaults/{vault}/notes/{note}/frontmatter', [NoteFrontmatterController::class, 'update'])->name('note-frontmatter.update');
    Route::put('vaults/{vault}/notes/{note}/tasks/{line}', [NoteTaskController::class, 'update'])->whereNumber('line')->name('note-tasks.update');
    Route::put('vaults/{vault}/notes/{note}/properties', [NotePropertiesController::class, 'update'])->name('note-properties.update');
    Route::delete('vaults/{vault}/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
    Route::get('vaults/{vault}/notes/{note}/export/{format}', [NoteExportController::class, 'show'])->whereIn('format', ['html', 'pdf'])->name('note-exports.show');
    Route::post('vaults/{vault}/notes/{note}/publish', [NotePublicationController::class, 'store'])->name('note-publications.store');
    Route::get('vaults/{vault}/notes/{note}/versions', [NoteVersionController::class, 'index'])->name('note-versions.index');
    Route::get('vaults/{vault}/notes/{note}/versions/{version}', [NoteVersionController::class, 'show'])->whereNumber('version')->name('note-versions.show');
    Route::post('vaults/{vault}/notes/{note}/versions/{version}/restore', [NoteVersionRestoreController::class, 'store'])->whereNumber('version')->name('note-version-restores.store');
    Route::post('vaults/{vault}/notes/{note}/comments', [NoteCommentController::class, 'store'])->name('note-comments.store');
    Route::put('vaults/{vault}/notes/{note}/comments/{comment}', [NoteCommentController::class, 'update'])->where('comment', '[0-9a-z]{26}')->name('note-comments.update');
    Route::delete('vaults/{vault}/notes/{note}/comments/{comment}', [NoteCommentController::class, 'destroy'])->where('comment', '[0-9a-z]{26}')->name('note-comments.destroy');
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
