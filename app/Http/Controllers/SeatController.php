<?php

namespace App\Http\Controllers;

use App\Models\Kursi;
use App\Models\Studio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SeatController extends Controller
{
    // METHOD INDEX YANG DIPANGGIL OLEH ROUTE
    public function index($studioId)
    {
        try {
            $studio = Studio::find($studioId);
            
            if (!$studio) {
                return response()->json([
                    'success' => false,
                    'message' => 'Studio tidak ditemukan'
                ], 404);
            }

            $seats = Kursi::where('studio_id', $studioId)
                ->orderByRaw('LENGTH(kursi_no), kursi_no')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'studio' => $studio,
                    'seats' => $seats
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kursi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // UPDATE SEAT STATUS
    public function updateStatus(Request $request, $seatId)
    {
        try {
            $seat = Kursi::findOrFail($seatId);

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:available,maintenance,vip'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $seat->update([
                'status' => $request->status
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status kursi berhasil diperbarui',
                'data' => $seat
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status kursi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // BULK UPDATE SEATS
    public function bulkUpdate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'studio_id' => 'required|exists:studios,id',
                'action' => 'required|in:reset_all,set_vip_rows,maintenance_mode',
                'rows' => 'sometimes|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $studioId = $request->studio_id;
            $action = $request->action;

            switch ($action) {
                case 'reset_all':
                    Kursi::where('studio_id', $studioId)->update(['status' => 'available']);
                    $message = 'Semua kursi berhasil direset ke status normal';
                    break;

                case 'set_vip_rows':
                    $rows = $request->rows ?? ['A', 'B'];
                    foreach ($rows as $row) {
                        Kursi::where('studio_id', $studioId)
                            ->where('kursi_no', 'LIKE', $row . '%')
                            ->update(['status' => 'vip']);
                    }
                    $message = 'Baris ' . implode(', ', $rows) . ' berhasil dijadikan VIP';
                    break;

                case 'maintenance_mode':
                    Kursi::where('studio_id', $studioId)->update(['status' => 'maintenance']);
                    $message = 'Semua kursi masuk mode maintenance';
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Aksi tidak valid'
                    ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan update bulk',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // GET STUDIO STATISTICS
    public function getStatistics($studioId)
    {
        try {
            $totalSeats = Kursi::where('studio_id', $studioId)->count();
            $availableSeats = Kursi::where('studio_id', $studioId)->where('status', 'available')->count();
            $vipSeats = Kursi::where('studio_id', $studioId)->where('status', 'vip')->count();
            $maintenanceSeats = Kursi::where('studio_id', $studioId)->where('status', 'maintenance')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $totalSeats,
                    'available' => $availableSeats,
                    'vip' => $vipSeats,
                    'maintenance' => $maintenanceSeats,
                    'capacity_percentage' => $totalSeats > 0 ? round(($availableSeats + $vipSeats) / $totalSeats * 100) : 0
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // GENERATE SEATS FOR STUDIO - 20 KURSI PER BARIS
    public function generateSeats($studioId)
    {
        try {
            $studio = Studio::findOrFail($studioId);
            
            // Cek apakah kursi sudah ada
            $existingSeats = Kursi::where('studio_id', $studioId)->count();
            if ($existingSeats > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kursi untuk studio ini sudah ada. Gunakan regenerate untuk membuat ulang.'
                ], 400);
            }

            $kapasitas = $studio->capacity; // Ambil kapasitas dari database
            
            // Hitung jumlah baris berdasarkan kapasitas dengan 20 kursi per baris
            $rows = ceil($kapasitas / 20);
            $seatsPerRow = 20; // Tetap 20 kursi per baris

            $rowLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T'];
            
            // Batasi maksimal 20 baris
            if ($rows > 20) {
                $rows = 20;
            }

            $totalSeats = 0;

            for ($i = 0; $i < $rows; $i++) {
                // Untuk baris terakhir, hitung berapa kursi yang harus dibuat
                $currentRowSeats = ($i === $rows - 1) ? ($kapasitas - ($i * $seatsPerRow)) : $seatsPerRow;
                
                for ($j = 1; $j <= $currentRowSeats; $j++) {
                    $seatNumber = $rowLetters[$i] . $j;
                    $seatType = ($i < 2) ? 'vip' : 'regular'; // Baris A dan B VIP

                    Kursi::create([
                        'studio_id' => $studioId,
                        'kursi_no' => $seatNumber,
                        'kursi_type' => $seatType,
                        'status' => 'available'
                    ]);
                    $totalSeats++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Kursi berhasil digenerate ($totalSeats kursi dari $kapasitas kapasitas) - $rows baris x 20 kursi",
                'data' => [
                    'kapasitas_database' => $kapasitas,
                    'rows' => $rows,
                    'seats_per_row' => $seatsPerRow,
                    'total_seats' => $totalSeats
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate kursi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // REGENERATE SEATS - 20 KURSI PER BARIS
    public function regenerateSeats($studioId)
    {
        try {
            $studio = Studio::findOrFail($studioId);
            
            // Hapus semua kursi yang ada
            Kursi::where('studio_id', $studioId)->delete();

            $kapasitas = $studio->capacity; // Ambil kapasitas dari database
            
            // Hitung jumlah baris berdasarkan kapasitas dengan 20 kursi per baris
            $rows = ceil($kapasitas / 20);
            $seatsPerRow = 20; // Tetap 20 kursi per baris

            $rowLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T'];
            
            // Batasi maksimal 20 baris
            if ($rows > 20) {
                $rows = 20;
            }

            $totalSeats = 0;

            for ($i = 0; $i < $rows; $i++) {
                // Untuk baris terakhir, hitung berapa kursi yang harus dibuat
                $currentRowSeats = ($i === $rows - 1) ? ($kapasitas - ($i * $seatsPerRow)) : $seatsPerRow;
                
                for ($j = 1; $j <= $currentRowSeats; $j++) {
                    $seatNumber = $rowLetters[$i] . $j;
                    $seatType = ($i < 2) ? 'vip' : 'regular'; // Baris A dan B VIP

                    Kursi::create([
                        'studio_id' => $studioId,
                        'kursi_no' => $seatNumber,
                        'kursi_type' => $seatType,
                        'status' => 'available'
                    ]);
                    $totalSeats++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Kursi berhasil digenerate ulang ($totalSeats kursi dari $kapasitas kapasitas) - $rows baris x 20 kursi",
                'data' => [
                    'kapasitas_database' => $kapasitas,
                    'rows' => $rows,
                    'seats_per_row' => $seatsPerRow,
                    'total_seats' => $totalSeats
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal regenerate kursi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // METHOD UNTUK MENDAPATKAN SEMUA KURSI (jika diperlukan)
    public function getAllSeats()
    {
        try {
            $seats = Kursi::with('studio')
                ->orderBy('studio_id')
                ->orderByRaw('LENGTH(kursi_no), kursi_no')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $seats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kursi',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}