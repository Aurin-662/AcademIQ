<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CredentialController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\IssuerController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RequestLogController;
use App\Http\Controllers\Admin\VerificationKeyController;
use App\Http\Controllers\Employer\ReportController;
use App\Http\Controllers\Employer\BulkVerifyController;
use App\Http\Controllers\Employer\VerifyController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportVerifyController;
use App\Http\Controllers\Employer\RequestController as EmployerRequestController;
use App\Http\Controllers\External\TaskController;
use App\Http\Controllers\Student\ConsentController;
use App\Http\Controllers\Student\ProofController;
use App\Http\Controllers\Student\TokenController;
use App\Http\Controllers\Student\WalletController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.home')->name('home');
Route::view('/about', 'pages.about')->name('about');
Route::view('/how-it-works', 'pages.how-it-works')->name('how-it-works');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/students', 'pages.students')->name('students');
Route::view('/employers', 'pages.employers')->name('employers');
Route::view('/issuers', 'pages.issuers')->name('issuers');
Route::view('/contact', 'pages.contact')->name('contact');
Route::view('/verify-report', 'pages.verify-report')->name('report.verify.form');
Route::post('/verify-report', [ReportVerifyController::class, 'check'])->middleware('throttle:10,1')->name('report.verify');
Route::post('/contact', [PublicController::class, 'contact'])->middleware('throttle:5,1')->name('contact.send');

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::view('/forgot-password', 'auth.forgot')->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', fn (string $token) => view('auth.reset', ['token' => $token, 'email' => request('email')]))->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'home'])->name('dashboard');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{id?}', [NotificationController::class, 'read'])->name('notifications.read');

    Route::prefix('admin')->name('admin.')->middleware('role:admin,issuer')->group(function () {
        Route::get('/', [DashboardController::class, 'admin'])->name('dashboard');
        Route::get('/students', [StudentController::class, 'index'])->name('students');
        Route::get('/students/{ref}/lookup', [StudentController::class, 'lookup'])->where('ref', '[A-Za-z0-9\-_]+')->middleware('throttle:60,1')->name('students.lookup');
        Route::get('/import', [ImportController::class, 'index'])->name('import');
        Route::get('/import/template', [ImportController::class, 'template'])->name('import.template');
        Route::post('/import/preview', [ImportController::class, 'preview'])->middleware('throttle:10,1')->name('import.preview');
        Route::post('/import/commit', [ImportController::class, 'commit'])->middleware('throttle:10,1')->name('import.commit');
        Route::get('/import/batches/{batchId}', [ImportController::class, 'result'])->name('import.result');
        Route::get('/import/batches/{batchId}/errors.csv', [ImportController::class, 'errorReport'])->name('import.errors');
        Route::post('/import/revoke', [ImportController::class, 'revoke'])->middleware('throttle:10,1')->name('import.revoke');
        Route::get('/credentials', [CredentialController::class, 'index'])->name('credentials');
        Route::get('/credentials/new', [CredentialController::class, 'create'])->name('credentials.create');
        Route::post('/credentials', [CredentialController::class, 'store'])->name('credentials.store');
        Route::get('/credentials/{credentialId}', [CredentialController::class, 'show'])->name('credentials.show');
        Route::get('/credentials/{credentialId}/edit', [CredentialController::class, 'edit'])->name('credentials.edit');
        Route::put('/credentials/{credentialId}', [CredentialController::class, 'update'])->name('credentials.update');
        Route::post('/credentials/{credentialId}/revoke', [CredentialController::class, 'revoke'])->name('credentials.revoke');
        Route::middleware('role:admin')->group(function () {
            Route::get('/issuers', [IssuerController::class, 'index'])->name('issuers');
            Route::post('/issuers', [IssuerController::class, 'store'])->name('issuers.store');
            Route::post('/issuers/{issuer}/toggle', [IssuerController::class, 'toggle'])->name('issuers.toggle');
            Route::get('/keys', [VerificationKeyController::class, 'index'])->name('keys');
            Route::post('/keys/{key}/toggle', [VerificationKeyController::class, 'toggle'])->name('keys.toggle');
            Route::post('/issuers/{issuer}/rotate', [IssuerController::class, 'rotate'])->name('issuers.rotate');
            Route::get('/users', [UserController::class, 'index'])->name('users');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::post('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
            Route::get('/audit', [AuditLogController::class, 'index'])->name('audit');
            Route::get('/requests', [RequestLogController::class, 'index'])->name('requests');
        });
    });
    Route::get('/student', [DashboardController::class, 'student'])->middleware('role:student')->name('student.dashboard');
    Route::prefix('student/wallet')->name('student.wallet.')->middleware('role:student')->group(function () {
        Route::get('/', [WalletController::class, 'index'])->name('index');
        Route::get('/key', [WalletController::class, 'key'])->name('key');
        Route::post('/key', [WalletController::class, 'setKey'])->name('key.set');
        Route::get('/deliveries', [WalletController::class, 'deliveries'])->name('deliveries');
        Route::post('/deliveries/{id}/ack', [WalletController::class, 'ack'])->name('ack');
        Route::get('/credentials', [WalletController::class, 'credentials'])->name('credentials');
    });
    Route::middleware('role:student')->group(function () {
        Route::get('/student/verify/{requestId}', [ProofController::class, 'show'])->name('student.proof.show');
        Route::get('/student/verify/{requestId}/instruction', [ProofController::class, 'instruction'])->name('student.proof.instruction');
        Route::post('/student/proofs', [ProofController::class, 'submit'])->middleware('throttle:20,1')->name('student.proof.submit');
    });
    // Target of the QR code / shared link. Guests are sent to login and back (auth middleware); only verifiers may proceed.
    Route::get('/v/{tokenId}', fn (string $tokenId) => redirect()->route('employer.verify.show', $tokenId))->where('tokenId', 'ACQ(-[0-9A-Z]{4}){4}')->middleware('role:employer')->name('verify.link');
    Route::prefix('student/tokens')->name('student.tokens.')->middleware('role:student')->group(function () {
        Route::get('/', [TokenController::class, 'index'])->name('index');
        Route::get('/new', [TokenController::class, 'create'])->name('create');
        Route::post('/prepare', [TokenController::class, 'prepare'])->middleware('throttle:20,1')->name('prepare');
        Route::post('/submit', [TokenController::class, 'submit'])->middleware('throttle:20,1')->name('submit');
        Route::get('/{tokenId}', [TokenController::class, 'show'])->where('tokenId', 'ACQ(-[0-9A-Z]{4}){4}')->name('show');
        Route::post('/{tokenId}/revoke', [TokenController::class, 'revoke'])->where('tokenId', 'ACQ(-[0-9A-Z]{4}){4}')->name('revoke');
    });
    Route::get('/student/credentials/{credentialId}', [WalletController::class, 'show'])->middleware('role:student')->name('student.credential');
    Route::prefix('employer')->name('employer.')->middleware('role:employer')->group(function () {
        Route::get('/', [EmployerRequestController::class, 'index'])->name('dashboard');
        Route::get('/requests/new', [EmployerRequestController::class, 'create'])->name('requests.create');
        Route::post('/requests', [EmployerRequestController::class, 'store'])->middleware('throttle:20,1')->name('requests.store');
        Route::get('/requests/{requestId}', [EmployerRequestController::class, 'show'])->name('requests.show');
        Route::get('/bulk', [BulkVerifyController::class, 'index'])->name('bulk.index');
        Route::post('/bulk', [BulkVerifyController::class, 'store'])->middleware('throttle:6,1')->name('bulk.store');
        Route::get('/bulk/{batchId}', [BulkVerifyController::class, 'show'])->where('batchId', 'VB-[A-Z0-9]{10}')->name('bulk.show');
        Route::get('/bulk/{batchId}/progress', [BulkVerifyController::class, 'progress'])->where('batchId', 'VB-[A-Z0-9]{10}')->middleware('throttle:120,1')->name('bulk.progress');
        Route::post('/bulk/{batchId}/step', [BulkVerifyController::class, 'step'])->where('batchId', 'VB-[A-Z0-9]{10}')->middleware('throttle:120,1')->name('bulk.step');
        Route::get('/bulk/{batchId}/csv', [BulkVerifyController::class, 'csv'])->where('batchId', 'VB-[A-Z0-9]{10}')->middleware('throttle:20,1')->name('bulk.csv');
        Route::get('/bulk/{batchId}/pdf', [BulkVerifyController::class, 'pdf'])->where('batchId', 'VB-[A-Z0-9]{10}')->middleware('throttle:20,1')->name('bulk.pdf');
        Route::get('/verify', [VerifyController::class, 'index'])->name('verify.index');
        Route::post('/verify/open', [VerifyController::class, 'open'])->middleware('throttle:30,1')->name('verify.open');
        Route::get('/verify/results/{checkId}', [VerifyController::class, 'result'])->name('verify.result');
        Route::get('/verify/results/{checkId}/pdf', [VerifyController::class, 'pdf'])->middleware('throttle:30,1')->name('verify.pdf');
        Route::get('/verify/{tokenId}', [VerifyController::class, 'show'])->where('tokenId', 'ACQ(-[0-9A-Z]{4}){4}')->middleware('throttle:60,1')->name('verify.show');
        Route::post('/verify/{tokenId}', [VerifyController::class, 'check'])->where('tokenId', 'ACQ(-[0-9A-Z]{4}){4}')->middleware('throttle:60,1')->name('verify.check');
        Route::get('/reports/{reportId}', [ReportController::class, 'download'])->middleware('throttle:30,1')->name('reports.download');
    });
    Route::prefix('student/requests')->name('student.requests.')->middleware('role:student')->group(function () {
        Route::get('/', [ConsentController::class, 'index'])->name('index');
        Route::get('/{requestId}', [ConsentController::class, 'show'])->name('show');
        Route::post('/{requestId}/decision', [ConsentController::class, 'decide'])->name('decide');
        Route::post('/{requestId}/revoke', [ConsentController::class, 'revoke'])->name('revoke');
    });
    Route::prefix('external')->name('external.')->middleware('role:previous_employer,referee,police_verifier')->group(function () {
        Route::get('/', [TaskController::class, 'index'])->name('dashboard');
        Route::get('/tasks/{id}', [TaskController::class, 'show'])->name('task');
        Route::post('/tasks/{id}', [TaskController::class, 'respond'])->middleware('throttle:20,1')->name('respond');
    });
});
