<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Jadwal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
   public function processPayment(Request $request)
{
    $validator = Validator::make($request->all(), [
        'film_id' => 'required|exists:films,id',
        'jadwal_id' => 'required|exists:jadwals,id',
        'kursi' => 'required|array|min:1',
        'method' => 'required|in:cash,transfer,gopay,dana,ovo,bca,mandiri',
        'total_amount' => 'required|numeric|min:0'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        DB::beginTransaction();

        $user = auth()->user();

        // Ambil jadwal
        $jadwal = Jadwal::findOrFail($request->jadwal_id);

        // Hitung subtotal
        $subtotal = 0;
        foreach ($request->kursi as $seat) {
            $seatType = strtoupper(substr($seat, 0, 1)) === 'V' ? 'vip' : 'regular';
            $seatPrice = $seatType === 'vip' ? $jadwal->price + 20000 : $jadwal->price;
            $subtotal += $seatPrice;
        }

        // Admin fee
        $adminFee = $this->getAdminFee($request->method);
        $totalAmount = $subtotal + $adminFee;

        if ($request->total_amount < $totalAmount) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah pembayaran tidak mencukupi',
                'expected_amount' => $totalAmount
            ], 422);
        }

        // Simpan ke tabel payment
        $payment = Payment::create([
            'film_id' => $request->film_id,
            'user_id' => $user->id,
            'jadwal_id' => $jadwal->id,
            'kursi' => $request->kursi,
            'ticket_count' => count($request->kursi),
            'subtotal' => $subtotal,
            'total_amount' => $totalAmount,
            'method' => $request->method,
            'status' => 'success',
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil diproses',
            'data' => $payment
        ]);
    } 
    catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Payment Processing Error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Gagal memproses pembayaran: ' . $e->getMessage()
        ], 500);
    }
}


    private function getAdminFee($method)
    {
        $fees = [
            'cash' => 0,
            'transfer' => 2500,
            'gopay' => 0,
            'dana' => 0,
            'ovo' => 0,
            'bca' => 2500,
            'mandiri' => 2500
        ];

        return $fees[$method] ?? 0;
    }

    // Ambil history pembayaran user
    public function getPaymentHistory()
    {
        $payments = Payment::with('jadwal.movie')  // relasi jadwal -> movie
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    // Update status payment
    public function updatePaymentStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,success,failed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $payment = Payment::find($id);
        
        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran tidak ditemukan'
            ], 404);
        }

        $payment->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Status pembayaran berhasil diupdate',
            'data' => $payment
        ]);
    }
}
