<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FilmController;
use App\Http\Controllers\JadwalController;
use App\Http\Controllers\Api\V1\StudioController;
use App\Http\Controllers\Api\V1\TicketPriceController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\SeatController;
use App\Http\Controllers\PaymentController;

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | AUTH - PUBLIC
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | FILM - PUBLIC
    |--------------------------------------------------------------------------
    */
    Route::get('/films', [FilmController::class, 'index']);
    Route::get('/films/{id}', [FilmController::class, 'show']);

    /*
    |--------------------------------------------------------------------------
    | JADWAL - PUBLIC
    |--------------------------------------------------------------------------
    */
    Route::get('/jadwals/schedules', [JadwalController::class, 'getSchedules']);
    Route::get('/jadwals/movie/{movieId}', [JadwalController::class, 'getSchedulesByMovie']);
    Route::get('/studios-list', [JadwalController::class, 'getStudios']);

    /*
    |--------------------------------------------------------------------------
    | PROTECTED ROUTES (BUTUH LOGIN)
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {

        /*
        | USER
        */
        Route::get('/user', [UserController::class, 'getUser']);
        Route::get('/users', [UserController::class, 'index']);
        Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);
        Route::put('/user/update', [UserController::class, 'update']);

        /*
        | CASHIER
        */
        Route::prefix('cashiers')->group(function () {
            Route::get('/', [CashierController::class, 'index']);
            Route::post('/', [CashierController::class, 'store']);
            Route::put('/{id}/status', [CashierController::class, 'updateStatus']);
            Route::delete('/{id}', [CashierController::class, 'destroy']);
        });

        /*
        | SEATS
        */
        Route::prefix('seats')->group(function () {
            Route::get('/studio/{studioId}', [SeatController::class, 'index']);
            Route::get('/statistics/{studioId}', [SeatController::class, 'getStatistics']);
            Route::put('/{seatId}/status', [SeatController::class, 'updateStatus']);

            Route::post('/bulk-update', [SeatController::class, 'bulkUpdate']);
            Route::post('/generate/{studioId}', [SeatController::class, 'generateSeats']);
            Route::post('/regenerate/{studioId}', [SeatController::class, 'regenerateSeats']);
        });

        /*
        | JADWAL
        */
        Route::get('/jadwals', [JadwalController::class, 'index']);
        Route::post('/jadwals', [JadwalController::class, 'store']);
        Route::put('/jadwals/{id}', [JadwalController::class, 'update']);
        Route::delete('/jadwals/{id}', [JadwalController::class, 'destroy']);

        /*
        | FILM
        */
        Route::post('/films', [FilmController::class, 'store']);
        Route::put('/films/{id}', [FilmController::class, 'update']);
        Route::delete('/films/{id}', [FilmController::class, 'destroy']);

        /*
        | STUDIOS
        */
        Route::get('/studios', [StudioController::class, 'index']);
        Route::post('/studios', [StudioController::class, 'store']);
        Route::get('/studios/{id}', [StudioController::class, 'show']);
        Route::put('/studios/{id}', [StudioController::class, 'update']);
        Route::delete('/studios/{id}', [StudioController::class, 'destroy']);

        /*
        | TICKET PRICES
        */
        Route::get('/ticket-prices', [TicketPriceController::class, 'index']);
        Route::post('/ticket-prices', [TicketPriceController::class, 'store']);
        Route::put('/ticket-prices/{id}', [TicketPriceController::class, 'update']);
        Route::patch('/ticket-prices/{id}/toggle', [TicketPriceController::class, 'toggleStatus']);
        Route::delete('/ticket-prices/{id}', [TicketPriceController::class, 'destroy']);

        /*
        | PAYMENTS - PERBAIKAN ROUTE
        */
        Route::prefix('payments')->group(function () {
            Route::get('/', [PaymentController::class, 'getAllPayments']);
            Route::get('/stats', [PaymentController::class, 'getPaymentStats']);
            Route::post('/process', [PaymentController::class, 'processPayment']);
            Route::get('/history', [PaymentController::class, 'getPaymentHistory']);
            Route::get('/{id}/invoice', [PaymentController::class, 'getInvoiceData']);
            Route::put('/{id}/status', [PaymentController::class, 'updatePaymentStatus']);
            Route::post('/{id}/mark-printed', [PaymentController::class, 'markAsPrinted']);
            Route::get('/cashier/transactions', [PaymentController::class, 'getCashierTransactions']);
        });

    });

});