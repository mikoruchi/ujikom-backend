<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Jadwal;
use App\Models\Film;
use App\Models\Studio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{

   public function getAllPayments()
{
    try {
        $payments = Payment::with([
            'user:id,name,email',
            'jadwal.film:id,title',
            'jadwal.studio:id,studio'
        ])->latest()->get();

        $formattedPayments = $payments->map(function($payment) {
            return [
                'id' => $payment->id,
                'ticket_number' => $payment->booking_code ?? 'TKT-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT),
                'customer_name' => $payment->user->name ?? 'N/A',
                'customer_email' => $payment->user->email ?? 'N/A',
                'movie_title' => $payment->jadwal->film->title ?? 'N/A',
                'studio' => $payment->jadwal->studio->studio ?? 'N/A',
                'show_date' => $payment->jadwal->show_date ?? 'N/A',
                'show_time' => $payment->jadwal->show_time ?? 'N/A',
                'seats' => json_decode($payment->kursi) ?? [],
                'ticket_count' => $payment->ticket_count,
                'subtotal' => (float) $payment->subtotal,
                'total_amount' => (float) $payment->total_amount,
                'payment_method' => $payment->method,
                'status' => $payment->status,
                'is_printed' => $payment->is_printed ?? false,
                'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedPayments,
            'count' => $formattedPayments->count()
        ]);

    } catch (\Exception $e) {
        \Log::error('Error getting all payments: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil data transaksi: ' . $e->getMessage()
        ], 500);
    }
}


    public function getCashierTransactions()
    {
        try {
            $payments = Payment::with([
                'user:id,name,email',
                'film:id,title',
                'jadwal.film:id,title',
                'jadwal.studio:id,nama'
            ])
            ->where('status', 'success')
            ->latest()
            ->get();

            $formattedPayments = $payments->map(function($payment) {
                $filmTitle = $payment->film->title ?? 
                           ($payment->jadwal->film->title ?? 'N/A');
                $studioName = $payment->jadwal->studio->nama ?? 'N/A';
                
                return [
                    'id' => $payment->id,
                    'ticket_number' => $payment->booking_code ?? 'TKT-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT),
                    'customer_name' => $payment->user->name ?? 'N/A',
                    'customer_email' => $payment->user->email ?? 'N/A',
                    'movie_title' => $filmTitle,
                    'studio' => $studioName,
                    'show_date' => $payment->jadwal->show_date ?? 'N/A',
                    'show_time' => $payment->jadwal->show_time ?? 'N/A',
                    'seats' => json_decode($payment->kursi) ?? [],
                    'ticket_count' => $payment->ticket_count,
                    'subtotal' => (float) $payment->subtotal,
                    'total_amount' => (float) $payment->total_amount,
                    'payment_method' => $payment->method,
                    'status' => $payment->status,
                    'is_printed' => $payment->is_printed ?? false,
                    'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedPayments
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting cashier transactions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data transaksi kasir'
            ], 500);
        }
    }

    public function markAsPrinted($id)
    {
        try {
            $payment = Payment::findOrFail($id);
            $payment->update(['is_printed' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Tiket berhasil ditandai sebagai tercetak',
                'data' => $payment
            ]);

        } catch (\Exception $e) {
            \Log::error('Error marking as printed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menandai tiket'
            ], 500);
        }
    }

    public function getPaymentStats()
    {
        try {
            $totalTransactions = Payment::count();
            $successTransactions = Payment::where('status', 'success')->get();
            
            $totalRevenue = $successTransactions->sum('total_amount');
            $totalTickets = $successTransactions->sum('ticket_count');
            $successRate = $totalTransactions > 0 ? ($successTransactions->count() / $totalTransactions) * 100 : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'totalTransactions' => $totalTransactions,
                    'totalRevenue' => $totalRevenue,
                    'totalTickets' => $totalTickets,
                    'successRate' => round($successRate, 1)
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting payment stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik'
            ], 500);
        }
    }
     
public function processPayment(Request $request)
{
    $validator = Validator::make($request->all(), [
        'film_id' => 'required|exists:films,id',
        'jadwal_id' => 'required|exists:jadwals,id',
        'kursi' => 'required|array|min:1',
        'method' => 'required|in:cash,transfer,gopay,dana,ovo,bca,mandiri'
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

        // Ambil jadwal dengan relasi film dan studio
        $jadwal = Jadwal::with(['film', 'studio'])->findOrFail($request->jadwal_id);

        // Generate booking code
        $bookingCode = 'BK' . date('YmdHis') . rand(100, 999);

        // Hitung subtotal
        $subtotal = 0;
        $seatDetails = [];
        foreach ($request->kursi as $seat) {
            $seatType = strtoupper(substr($seat, 0, 1)) === 'V' ? 'vip' : 'regular';
            $seatPrice = $seatType === 'vip' ? $jadwal->price + 20000 : $jadwal->price;
            $subtotal += $seatPrice;
            $seatDetails[] = [
                'seat' => $seat,
                'type' => $seatType,
                'price' => $seatPrice
            ];
        }

        // Admin fee
        $adminFee = $this->getAdminFee($request->method);

        // Total amount dihitung otomatis
        $totalAmount = $subtotal + $adminFee;

        // Simpan ke tabel payment
        $payment = Payment::create([
            'film_id' => $request->film_id,
            'user_id' => $user->id,
            'jadwal_id' => $jadwal->id,
            'kursi' => json_encode($request->kursi),
            'seat_details' => json_encode($seatDetails),
            'ticket_count' => count($request->kursi),
            'subtotal' => $subtotal,
            'admin_fee' => $adminFee,
            'total_amount' => $totalAmount, // otomatis
            'method' => $request->method,
            'booking_code' => $bookingCode,
            'status' => 'success',
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil diproses',
            'data' => $payment->load(['jadwal.film', 'jadwal.studio']),
            'total_amount' => $totalAmount // kirim ke frontend
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

    // Ambil history pembayaran user - PERBAIKAN DI SINI
    public function getPaymentHistory()
    {
        $payments = Payment::with(['jadwal.film', 'jadwal.studio'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        // Log untuk debugging
        \Log::info('Payment History Data:', [
            'count' => $payments->count(),
            'data' => $payments->map(function($payment) {
                return [
                    'id' => $payment->id,
                    'booking_code' => $payment->booking_code,
                    'jadwal' => $payment->jadwal ? [
                        'show_date' => $payment->jadwal->show_date,
                        'show_time' => $payment->jadwal->show_time,
                        'film' => $payment->jadwal->film ? $payment->jadwal->film->title : 'No Film',
                        'studio' => $payment->jadwal->studio ? $payment->jadwal->studio->studio : 'No Studio'
                    ] : 'No Jadwal'
                ];
            })->toArray()
        ]);

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    // Get Invoice Data (untuk tampilan di frontend) - PERBAIKAN DI SINI
    public function getInvoiceData($id)
    {
        try {
            $payment = Payment::with(['jadwal.film', 'jadwal.studio', 'user'])
                ->where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            // Log untuk debugging
            \Log::info('Invoice Data:', [
                'payment_id' => $payment->id,
                'booking_code' => $payment->booking_code,
                'jadwal_date' => $payment->jadwal->show_date ?? 'No date',
                'jadwal_time' => $payment->jadwal->show_time ?? 'No time',
                'film_title' => $payment->jadwal->film->title ?? 'No film',
                'studio_name' => $payment->jadwal->studio->studio ?? 'No studio'
            ]);

            return response()->json([
                'success' => true,
                'data' => $payment
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting invoice data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Invoice tidak ditemukan'
            ], 404);
        }
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