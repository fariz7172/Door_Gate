<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\Api\DoorApiController;
use App\Http\Controllers\Api\UsersProfileApiController;
use App\Http\Controllers\Api\UserlogApiController;

Route::post('/scan', [ScanController::class,'store']);
Route::get('/logs/latest', [ScanController::class,'latest']);
Route::post('/doors/{door}/open', [DoorApiController::class,'open']);

// Endpoint Photo bersifat publik agar bisa dibuka langsung di browser / tag <img>
Route::get('/usersprofile/{id}/photo', [UsersProfileApiController::class,'photo'])->name('api.usersprofile.photo');

// UsersProfile (Master Karyawan / Member) — CRUD & Sync diproteksi Token
// Proteksi: Bearer Token / X-API-Key (generate via: php artisan token:create)
Route::middleware('api.token')->group(function () {
    Route::get('/usersprofile',            [UsersProfileApiController::class,'index'])->name('api.usersprofile.index');
    Route::post('/usersprofile',           [UsersProfileApiController::class,'store'])->name('api.usersprofile.store');
    Route::post('/usersprofile/bulk',      [UsersProfileApiController::class,'bulkStore'])->name('api.usersprofile.bulkStore');
    Route::get('/usersprofile/{id}',       [UsersProfileApiController::class,'show'])->name('api.usersprofile.show');
    Route::put('/usersprofile/{id}',       [UsersProfileApiController::class,'update'])->name('api.usersprofile.update');
    Route::delete('/usersprofile/bulk',    [UsersProfileApiController::class,'bulkDestroy'])->name('api.usersprofile.bulkDestroy');
    Route::delete('/usersprofile/{id?}',   [UsersProfileApiController::class,'destroy'])->name('api.usersprofile.destroy');
    Route::post('/usersprofile/{id}/sync-device', [UsersProfileApiController::class,'syncToDevice'])->name('api.usersprofile.syncDevice');

    // TblUserlog (Log Akses / Tap Turnstile) — Proteksi Token
    Route::get('/userlog',          [UserlogApiController::class, 'index'])->name('api.userlog.index');
    Route::get('/userlogs',         [UserlogApiController::class, 'index'])->name('api.userlogs.index');
    Route::get('/userlog/latest',   [UserlogApiController::class, 'latest'])->name('api.userlog.latest');
    Route::get('/userlog/{id}',     [UserlogApiController::class, 'show'])->name('api.userlog.show');
});
