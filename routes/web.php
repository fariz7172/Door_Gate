<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DoorController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ApiEndpointController;
use App\Http\Controllers\DeviceGateController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DepartemenController;
use App\Http\Controllers\DeviceGroupController;
use App\Http\Controllers\UsersProfileController;
use App\Http\Controllers\GateApilogController;
use App\Http\Controllers\AuthController;

// Auth (public)
Route::get('/login', [AuthController::class,'showLogin'])->name('login');
Route::post('/login', [AuthController::class,'login'])->name('login.post');
Route::post('/logout', [AuthController::class,'logout'])->name('logout');

Route::get('/', fn() => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));
// Kiosk public (scanner tanpa login)
Route::get('/kiosk/{door_code}', [KioskController::class,'show'])->name('kiosk.show');
Route::middleware(['auth'])->group(function () {
Route::get('/dashboard', [DashboardController::class,'index'])->name('dashboard');
// Legacy doors (deprecated — digantikan devicegate, tetap ada agar tidak 404)
Route::resource('doors', DoorController::class);
Route::get('/settings', [SettingsController::class,'edit'])->name('settings.edit');
Route::put('/settings', [SettingsController::class,'update'])->name('settings.update');

// Device API Endpoints (migrasi dari table `api` lama — Soyal / Tasoft / Dreampos)
Route::get('/endpoints/seed', [ApiEndpointController::class,'seed'])->name('endpoints.seed');
Route::post('/endpoints/{endpoint}/test', [ApiEndpointController::class,'test'])->name('endpoints.test');
Route::resource('endpoints', ApiEndpointController::class)->parameters(['endpoints'=>'endpoint']);

// DeviceGate — tabel devicegate (Face/Soyal/Finger) — hubungkan Tasoft API via DeviceGatewayService
Route::get('/devicegate/seed', [DeviceGateController::class,'seed'])->name('devicegate.seed');
Route::get('/devicegate/ping-all', [DeviceGateController::class,'pingAll'])->name('devicegate.pingAll');
Route::post('/devicegate/{devicegate}/open', [DeviceGateController::class,'open'])->name('devicegate.open');
Route::post('/devicegate/{devicegate}/cleanlogs', [DeviceGateController::class,'cleanLogs'])->name('devicegate.cleanlogs');
Route::post('/devicegate/{devicegate}/gettime', [DeviceGateController::class,'getTime'])->name('devicegate.gettime');
Route::post('/devicegate/{devicegate}/settime', [DeviceGateController::class,'setTime'])->name('devicegate.settime');
Route::post('/devicegate/{devicegate}/reboot', [DeviceGateController::class,'reboot'])->name('devicegate.reboot');
Route::post('/devicegate/toggle-all', [DeviceGateController::class,'toggleAll'])->name('devicegate.toggleAll');
Route::post('/devicegate/{devicegate}/enable', [DeviceGateController::class,'enable'])->name('devicegate.enable');
Route::post('/devicegate/{devicegate}/disable', [DeviceGateController::class,'disable'])->name('devicegate.disable');
Route::resource('devicegate', DeviceGateController::class)->parameters(['devicegate'=>'devicegate']);

// Branch
Route::resource('branch', BranchController::class)->parameters(['branch'=>'branch']);

// Departemen
Route::resource('departemen', DepartemenController::class)->parameters(['departemen'=>'departeman']);

// Device Group
Route::resource('devicegroup', DeviceGroupController::class)->parameters(['devicegroup'=>'devicegroup']);

// Users Profile
Route::post('/usersprofile/sync-devices/step', [UsersProfileController::class,'syncSingleUserStep'])->name('usersprofile.syncStep');
Route::post('/usersprofile/remove-devices/step', [UsersProfileController::class,'removeSingleUserStep'])->name('usersprofile.removeStep');
Route::get('/usersprofile/template/excel', [UsersProfileController::class,'downloadTemplate'])->name('usersprofile.downloadTemplate');
Route::post('/usersprofile/import/excel', [UsersProfileController::class,'importExcel'])->name('usersprofile.importExcel');
Route::get('/usersprofile/{usersprofile}/photo', [UsersProfileController::class,'photo'])->name('usersprofile.photo');
Route::post('/usersprofile/bulk-destroy', [UsersProfileController::class,'bulkDestroy'])->name('usersprofile.bulkDestroy');
Route::post('/usersprofile/sync-devices', [UsersProfileController::class,'syncToDevices'])->name('usersprofile.syncDevices');
Route::post('/usersprofile/remove-devices', [UsersProfileController::class,'removeFromDevices'])->name('usersprofile.removeDevices');
Route::get('/usersprofile/export/excel', [UsersProfileController::class,'exportExcel'])->name('usersprofile.exportExcel');
Route::resource('usersprofile', UsersProfileController::class)->parameters(['usersprofile'=>'usersprofile']);

// Gate API Log — live feed tbl_gate_apilog
Route::get('/gate-logs/export/excel', [GateApilogController::class,'exportExcel'])->name('gate-logs.exportExcel');
Route::get('/gate-logs/feed', [GateApilogController::class,'feed'])->name('gate-logs.feed');
Route::get('/gate-logs', [GateApilogController::class,'index'])->name('gate-logs.index');
});
