<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ticket_ids' => 'required|array',
            'ticket_ids.*' => 'exists:tickets,id',
            'method' => 'required|in:cash,transfer,gopay,dana,ovo,bca,mandiri',
            'amount' => 'required|numeric|min:0'
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

            // Verifikasi semua tiket milik user dan status booked
            $tickets = Ticket::whereIn('id', $request->ticket_ids)
                ->where('user_id', auth()->id())
                ->where('status', 'booked')
                ->get();

            if ($tickets->count() !== count($request->ticket_ids)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Beberapa tiket tidak valid atau sudah diproses'
                ], 422);
            }

            // Hitung total amount yang seharusnya
            $expectedAmount = 0;
            foreach ($tickets as $ticket) {
                $basePrice = $ticket->jadwal->price ?? 50000;
                $seatPrice = $ticket->kursi->kursi_type === 'vip' ? $basePrice + 20000 : $basePrice;
                $expectedAmount += $seatPrice;
            }

            // Tambahkan biaya admin untuk metode tertentu
            $adminFee = $this->getAdminFee($request->method);
            $expectedAmount += $adminFee;

            if ($request->amount < $expectedAmount) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Jumlah pembayaran tidak mencukupi',
                    'expected_amount' => $expectedAmount
                ], 422);
            }

            // Buat payment record
            $payment = Payment::create([
                'ticket_id' => $request->ticket_ids[0], // Simpan salah satu ticket_id
                'amount' => $expectedAmount,
                'method' => $request->method,
                'status' => 'pending'
            ]);

            // Update status tiket menjadi paid
            Ticket::whereIn('id', $request->ticket_ids)->update(['status' => 'paid']);

            // Simpan relationship many-to-many jika diperlukan
            foreach ($tickets as $ticket) {
                $ticket->payment_id = $payment->id;
                $ticket->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran berhasil diproses',
                'data' => [
                    'payment' => $payment->load('ticket'),
                    'tickets' => $tickets->load(['jadwal.movie', 'kursi', 'studio'])
                ]
            ]);

        } catch (\Exception $e) {
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

    public function getPaymentHistory()
    {
        $payments = Payment::with(['ticket.jadwal.movie', 'ticket.kursi', 'ticket.studio'])
            ->whereHas('ticket', function($query) {
                $query->where('user_id', auth()->id());
            })
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

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