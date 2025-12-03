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

    // UPDATE SEAT STATUS (available/maintenance)
    public function updateStatus(Request $request, $seatId)
    {
        try {
            $seat = Kursi::findOrFail($seatId);

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:available,maintenance'
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

    // UPDATE SEAT TYPE (regular/vip) - TAMBAHKAN METHOD BARU
    public function updateType(Request $request, $seatId)
    {
        try {
            $seat = Kursi::findOrFail($seatId);

            $validator = Validator::make($request->all(), [
                'kursi_type' => 'required|in:regular,vip'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $seat->update([
                'kursi_type' => $request->kursi_type
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tipe kursi berhasil diperbarui',
                'data' => $seat
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui tipe kursi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // BULK UPDATE SEATS - DIPERBARUI
    public function bulkUpdate(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            'studio_id' => 'required|exists:studios,id',
            'action' => 'required|in:reset_all,set_vip_rows,set_regular_rows,maintenance_mode,available_mode,set_all_vip,set_all_regular', // TAMBAHKAN INI
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
                // Reset semua ke regular dan available
                Kursi::where('studio_id', $studioId)->update([
                    'kursi_type' => 'regular',
                    'status' => 'available'
                ]);
                $message = 'Semua kursi berhasil direset ke tipe regular dan status available';
                break;

            case 'set_all_vip': // TAMBAHKAN INI
                Kursi::where('studio_id', $studioId)->update([
                    'kursi_type' => 'vip'
                ]);
                $message = 'Semua kursi berhasil dijadikan VIP';
                break;

            case 'set_all_regular': // TAMBAHKAN INI
                Kursi::where('studio_id', $studioId)->update([
                    'kursi_type' => 'regular'
                ]);
                $message = 'Semua kursi berhasil dijadikan Regular';
                break;

            case 'set_vip_rows':
                $rows = $request->rows ?? ['A', 'B'];
                foreach ($rows as $row) {
                    Kursi::where('studio_id', $studioId)
                        ->where('kursi_no', 'LIKE', $row . '%')
                        ->update(['kursi_type' => 'vip']);
                }
                $message = 'Baris ' . implode(', ', $rows) . ' berhasil dijadikan VIP';
                break;

            case 'set_regular_rows':
                $rows = $request->rows ?? ['A', 'B'];
                foreach ($rows as $row) {
                    Kursi::where('studio_id', $studioId)
                        ->where('kursi_no', 'LIKE', $row . '%')
                        ->update(['kursi_type' => 'regular']);
                }
                $message = 'Baris ' . implode(', ', $rows) . ' berhasil dijadikan Regular';
                break;

            case 'maintenance_mode':
                Kursi::where('studio_id', $studioId)->update(['status' => 'maintenance']);
                $message = 'Semua kursi masuk mode maintenance';
                break;

            case 'available_mode':
                Kursi::where('studio_id', $studioId)->update(['status' => 'available']);
                $message = 'Semua kursi masuk mode available';
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

    // GET STUDIO STATISTICS - DIPERBARUI
    public function getStatistics($studioId)
    {
        try {
            $totalSeats = Kursi::where('studio_id', $studioId)->count();
            $availableSeats = Kursi::where('studio_id', $studioId)->where('status', 'available')->count();
            $maintenanceSeats = Kursi::where('studio_id', $studioId)->where('status', 'maintenance')->count();
            
            // Statistik tipe kursi
            $regularSeats = Kursi::where('studio_id', $studioId)->where('kursi_type', 'regular')->count();
            $vipSeats = Kursi::where('studio_id', $studioId)->where('kursi_type', 'vip')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $totalSeats,
                    'available' => $availableSeats,
                    'maintenance' => $maintenanceSeats,
                    'regular' => $regularSeats,
                    'vip' => $vipSeats,
                    'capacity_percentage' => $totalSeats > 0 ? round(($availableSeats) / $totalSeats * 100) : 0
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

    // GENERATE SEATS FOR STUDIO - SEMUA DEFAULT REGULAR
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

            $kapasitas = $studio->capacity;
            
            // Hitung jumlah baris berdasarkan kapasitas dengan 20 kursi per baris
            $rows = ceil($kapasitas / 20);
            $seatsPerRow = 20;

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
                    
                    // SEMUA KURSI DEFAULT REGULAR
                    $seatType = 'regular';
                    
                    // Untuk studio 150 kursi, 10 kursi terakhir jadi maintenance
                    $status = 'available';
                    if ($kapasitas === 150 && $totalSeats >= 140) {
                        $status = 'maintenance';
                    }

                    Kursi::create([
                        'studio_id' => $studioId,
                        'kursi_no' => $seatNumber,
                        'kursi_type' => $seatType, // DEFAULT REGULAR
                        'status' => $status
                    ]);
                    $totalSeats++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Kursi berhasil digenerate ($totalSeats kursi dari $kapasitas kapasitas) - Semua tipe regular",
                'data' => [
                    'kapasitas_database' => $kapasitas,
                    'rows' => $rows,
                    'seats_per_row' => $seatsPerRow,
                    'total_seats' => $totalSeats,
                    'default_type' => 'regular'
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

    // REGENERATE SEATS - SEMUA DEFAULT REGULAR
    public function regenerateSeats($studioId)
    {
        try {
            $studio = Studio::findOrFail($studioId);
            
            // Hapus semua kursi yang ada
            Kursi::where('studio_id', $studioId)->delete();

            $kapasitas = $studio->capacity;
            
            // Hitung jumlah baris berdasarkan kapasitas dengan 20 kursi per baris
            $rows = ceil($kapasitas / 20);
            $seatsPerRow = 20;

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
                    
                    // SEMUA KURSI DEFAULT REGULAR
                    $seatType = 'regular';
                    
                    // Untuk studio 150 kursi, 10 kursi terakhir jadi maintenance
                    $status = 'available';
                    if ($kapasitas === 150 && $totalSeats >= 140) {
                        $status = 'maintenance';
                    }

                    Kursi::create([
                        'studio_id' => $studioId,
                        'kursi_no' => $seatNumber,
                        'kursi_type' => $seatType, // DEFAULT REGULAR
                        'status' => $status
                    ]);
                    $totalSeats++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Kursi berhasil digenerate ulang ($totalSeats kursi dari $kapasitas kapasitas) - Semua tipe regular",
                'data' => [
                    'kapasitas_database' => $kapasitas,
                    'rows' => $rows,
                    'seats_per_row' => $seatsPerRow,
                    'total_seats' => $totalSeats,
                    'default_type' => 'regular'
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
}