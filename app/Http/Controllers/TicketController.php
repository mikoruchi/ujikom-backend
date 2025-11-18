<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Jadwal;
use App\Models\Kursi;
use App\Models\Studio;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TicketController extends Controller
{
    public function index()
    {
        $tickets = Ticket::with(['studio', 'jadwal.movie', 'user', 'kursi', 'payment'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tickets
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jadwal_id' => 'required|exists:jadwals,id',
            'kursi_ids' => 'required|array|min:1',
            'kursi_ids.*' => 'required|exists:kursis,id'
        ], [
            'jadwal_id.required' => 'ID jadwal harus diisi',
            'jadwal_id.exists' => 'Jadwal tidak ditemukan',
            'kursi_ids.required' => 'Pilih minimal 1 kursi',
            'kursi_ids.array' => 'Format kursi tidak valid',
            'kursi_ids.min' => 'Pilih minimal 1 kursi',
            'kursi_ids.*.required' => 'ID kursi harus diisi',
            'kursi_ids.*.exists' => 'Kursi tidak ditemukan'
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

            $jadwal = Jadwal::with(['movie', 'studio'])->find($request->jadwal_id);
            
            if (!$jadwal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal tidak ditemukan'
                ], 404);
            }

            $tickets = [];
            $errors = [];

            foreach ($request->kursi_ids as $kursi_id) {
                $kursi = Kursi::find($kursi_id);
                if (!$kursi) {
                    $errors[] = "Kursi dengan ID {$kursi_id} tidak ditemukan";
                    continue;
                }

                // Cek apakah kursi sudah dipesan untuk jadwal ini
                $existingTicket = Ticket::where('jadwal_id', $request->jadwal_id)
                    ->where('kursi_id', $kursi_id)
                    ->whereIn('status', ['booked', 'paid'])
                    ->first();

                if ($existingTicket) {
                    $errors[] = "Kursi {$kursi->kursi_no} sudah dipesan";
                    continue;
                }

                if ($kursi->status === 'maintenance') {
                    $errors[] = "Kursi {$kursi->kursi_no} sedang dalam perbaikan";
                    continue;
                }

                try {
                    // Generate QR Code
                    $qrCodeData = [
                        'movie' => $jadwal->movie->title ?? 'Unknown Movie',
                        'time' => $jadwal->time,
                        'date' => $jadwal->date,
                        'studio' => $jadwal->studio->name ?? 'Unknown Studio',
                        'kursi' => $kursi->kursi_no,
                        'user' => auth()->user()->name,
                        'timestamp' => now()->toISOString()
                    ];

                    $qrCode = QrCode::format('png')->size(200)->generate(json_encode($qrCodeData));
                    $qrCodeFileName = 'qr_' . uniqid() . '_' . time() . '.png';
                    $qrCodePath = 'qrcodes/' . $qrCodeFileName;
                    
                    \Storage::disk('public')->put($qrCodePath, $qrCode);

                    // Buat tiket
                    $ticket = Ticket::create([
                        'studio_id' => $jadwal->studio_id,
                        'jadwal_id' => $request->jadwal_id,
                        'user_id' => auth()->id(),
                        'kursi_id' => $kursi_id,
                        'qr_code' => $qrCodePath,
                        'status' => 'booked'
                    ]);

                    $ticket->load(['studio', 'jadwal.movie', 'kursi']);
                    $tickets[] = $ticket;

                } catch (\Exception $e) {
                    $errors[] = "Gagal membuat tiket untuk kursi {$kursi->kursi_no}: " . $e->getMessage();
                    continue;
                }
            }

            if (!empty($errors) && empty($tickets)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Semua kursi gagal dipesan',
                    'errors' => $errors
                ], 422);
            }

            if (!empty($errors)) {
                // Ada beberapa yang berhasil, beberapa gagal
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => count($tickets) . ' tiket berhasil dibuat, beberapa kursi gagal',
                    'data' => $tickets,
                    'errors' => $errors,
                    'total_price' => $this->calculateTotalPrice($tickets, $jadwal)
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($tickets) . ' tiket berhasil dibuat',
                'data' => $tickets,
                'total_price' => $this->calculateTotalPrice($tickets, $jadwal)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Ticket Creation Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat tiket: ' . $e->getMessage()
            ], 500);
        }
    }

    private function calculateTotalPrice($tickets, $jadwal)
    {
        $basePrice = $jadwal->price ?? 50000;
        $total = 0;

        foreach ($tickets as $ticket) {
            $seatPrice = $ticket->kursi->kursi_type === 'vip' ? $basePrice + 20000 : $basePrice;
            $total += $seatPrice;
        }

        return $total;
    }

    public function show($id)
    {
        $ticket = Ticket::with(['studio', 'jadwal.movie', 'kursi', 'user', 'payment'])
            ->where('user_id', auth()->id())
            ->find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Tiket tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $ticket
        ]);
    }

    public function getBookedSeats($jadwalId)
    {
        $validator = Validator::make(['jadwal_id' => $jadwalId], [
            'jadwal_id' => 'required|exists:jadwals,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'ID jadwal tidak valid'
            ], 422);
        }

        $bookedSeats = Ticket::where('jadwal_id', $jadwalId)
            ->whereIn('status', ['booked', 'paid'])
            ->with('kursi')
            ->get()
            ->pluck('kursi')
            ->filter()
            ->map(function($kursi) {
                return [
                    'id' => $kursi->id,
                    'kursi_no' => $kursi->kursi_no,
                    'kursi_type' => $kursi->kursi_type,
                    'status' => $kursi->status
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $bookedSeats,
            'count' => $bookedSeats->count()
        ]);
    }

    public function getAvailableSeats($jadwalId)
    {
        $validator = Validator::make(['jadwal_id' => $jadwalId], [
            'jadwal_id' => 'required|exists:jadwals,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'ID jadwal tidak valid'
            ], 422);
        }

        $jadwal = Jadwal::with('studio.kursis')->find($jadwalId);
        
        if (!$jadwal) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal tidak ditemukan'
            ], 404);
        }

        // Ambil kursi yang sudah dipesan
        $bookedKursiIds = Ticket::where('jadwal_id', $jadwalId)
            ->whereIn('status', ['booked', 'paid'])
            ->pluck('kursi_id');

        // Ambil semua kursi di studio yang tersedia
        $availableSeats = $jadwal->studio->kursis()
            ->whereNotIn('id', $bookedKursiIds)
            ->where('status', '!=', 'maintenance')
            ->get()
            ->map(function($kursi) use ($jadwal) {
                $basePrice = $jadwal->price ?? 50000;
                $price = $kursi->kursi_type === 'vip' ? $basePrice + 20000 : $basePrice;
                
                return [
                    'id' => $kursi->id,
                    'kursi_no' => $kursi->kursi_no,
                    'kursi_type' => $kursi->kursi_type,
                    'status' => $kursi->status,
                    'price' => $price,
                    'is_available' => true
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'seats' => $availableSeats,
                'studio' => $jadwal->studio,
                'total_available' => $availableSeats->count()
            ]
        ]);
    }

    public function getUserTickets()
    {
        $tickets = Ticket::with(['studio', 'jadwal.movie', 'kursi', 'payment'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tickets,
            'count' => $tickets->count()
        ]);
    }

    public function cancel($id)
    {
        $ticket = Ticket::where('user_id', auth()->id())
            ->where('status', 'booked')
            ->find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Tiket tidak ditemukan atau tidak dapat dibatalkan'
            ], 404);
        }

        $ticket->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Tiket berhasil dibatalkan'
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:booked,paid,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $ticket = Ticket::find($id);
        
        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Tiket tidak ditemukan'
            ], 404);
        }

        $ticket->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Status tiket berhasil diupdate',
            'data' => $ticket
        ]);
    }
}