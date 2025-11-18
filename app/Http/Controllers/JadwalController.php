<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Film;
use App\Models\Studio;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class JadwalController extends Controller
{
    public function index()
    {
        $jadwals = Jadwal::with(['film', 'studio'])->get();
        return response()->json($jadwals);
    }

    // ✅ Method BARU: Get schedules by movie ID
    public function getSchedulesByMovie($movieId, Request $request)
    {
        try {
            Log::info('Get schedules by movie request:', [
                'movie_id' => $movieId,
                'date' => $request->date
            ]);
            
            $query = Jadwal::with(['film', 'studio'])
                ->where('film_id', $movieId);
            
            // Filter berdasarkan tanggal jika ada
            if ($request->has('date') && $request->date) {
                $query->whereDate('show_date', $request->date);
            } else {
                // Default ke hari ini
                $query->whereDate('show_date', Carbon::today());
            }
            
            $jadwals = $query->orderBy('show_time')->get();
            
            Log::info('Found jadwals for movie:', [
                'movie_id' => $movieId,
                'count' => $jadwals->count()
            ]);
            
            // Format data khusus untuk movie detail page
            $formattedSchedules = $jadwals->groupBy('studio_id')->map(function ($schedules, $studioId) {
                $firstSchedule = $schedules->first();
                $studio = $firstSchedule->studio;
                
                return [
                    'id' => $firstSchedule->id,
                    'studio' => [
                        'id' => $studio->id,
                        'studio' => $studio->studio,
                        'description' => $studio->description
                    ],
                    'times' => $schedules->pluck('show_time')->toArray(),
                    'price' => $firstSchedule->price
                ];
            })->values();
            
            return response()->json([
                'success' => true,
                'data' => $formattedSchedules
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in getSchedulesByMovie: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching schedules for movie',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Method untuk mendapatkan jadwal berdasarkan tanggal dan studio (existing)
    public function getSchedules(Request $request)
    {
        try {
            Log::info('Get schedules request:', $request->all());
            
            $query = Jadwal::with(['film', 'studio']);
            
            if ($request->has('date') && $request->date) {
                $query->whereDate('show_date', $request->date);
            } else {
                $query->whereDate('show_date', Carbon::today());
            }
            
            if ($request->has('studio_id') && $request->studio_id !== 'all') {
                $query->where('studio_id', $request->studio_id);
            }
            
            $jadwals = $query->get();
            
            Log::info('Found jadwals:', ['count' => $jadwals->count()]);
            
            if ($jadwals->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }
            
            $formattedSchedules = $jadwals->groupBy('film_id')->map(function ($schedules, $filmId) {
                $firstSchedule = $schedules->first();
                $film = $firstSchedule->film;
                
                return [
                    'id' => $filmId,
                    'movie' => $film ? $film->title : 'Unknown Movie',
                    'genre' => $film ? $film->genre : 'Unknown Genre',
                    'duration' => $film ? $film->duration : 0,
                    'rating' => $film ? $film->rating : 'Unknown',
                    'poster' => $film ? $film->poster : 'https://via.placeholder.com/150x225?text=No+Image',
                    'showtimes' => $schedules->map(function ($schedule) {
                        return [
                            'time' => $schedule->show_time,
                            'theater' => 'studio' . $schedule->studio_id,
                            'studio_id' => $schedule->studio_id,
                            'studio_name' => $schedule->studio ? $schedule->studio->studio : 'Unknown Studio',
                            'price' => $schedule->price,
                            'available' => 100,
                            'schedule_id' => $schedule->id
                        ];
                    })
                ];
            })->values();
            
            return response()->json([
                'success' => true,
                'data' => $formattedSchedules
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in getSchedules: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching schedules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Method untuk mendapatkan studios
    public function getStudios()
    {
        try {
            $studios = Studio::all();
            
            return response()->json([
                'success' => true,
                'data' => $studios
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching studios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ Method store untuk membuat jadwal baru
    public function store(Request $request)
    {
        try {
            Log::info('Store jadwal request:', $request->all());
            
            $validated = $request->validate([
                'film_id' => 'required|exists:films,id',
                'studio_id' => 'required|exists:studios,id',
                'show_date' => 'required|date',
                'show_time' => 'required',
                'price' => 'required|numeric|min:0',
            ]);

            Log::info('Validated data:', $validated);

            $jadwal = Jadwal::create($validated);
            $jadwal->load(['film', 'studio']);

            Log::info('Jadwal created successfully:', ['id' => $jadwal->id]);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal berhasil ditambahkan',
                'data' => $jadwal
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in store:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan jadwal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ Method update untuk mengupdate jadwal
    public function update(Request $request, $id)
    {
        try {
            Log::info('Update jadwal request:', [
                'id' => $id,
                'data' => $request->all()
            ]);

            $jadwal = Jadwal::findOrFail($id);
            Log::info('Found jadwal:', $jadwal->toArray());

            $validated = $request->validate([
                'film_id' => 'required|exists:films,id',
                'studio_id' => 'required|exists:studios,id',
                'show_date' => 'required|date',
                'show_time' => 'required',
                'price' => 'required|numeric|min:0',
            ]);

            Log::info('Validated update data:', $validated);

            $jadwal->update($validated);
            $jadwal->load(['film', 'studio']);

            Log::info('Jadwal updated successfully:', $jadwal->toArray());

            return response()->json([
                'success' => true,
                'message' => 'Jadwal berhasil diperbarui',
                'data' => $jadwal
            ]);

        } catch (ModelNotFoundException $e) {
            Log::error('Jadwal not found for update:', ['id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'Jadwal tidak ditemukan'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in update:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in update: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui jadwal: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ Method destroy untuk menghapus jadwal
    public function destroy($id)
    {
        try {
            Log::info('Delete jadwal request:', ['id' => $id]);

            $jadwal = Jadwal::findOrFail($id);
            $jadwal->delete();

            Log::info('Jadwal deleted successfully:', ['id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal berhasil dihapus'
            ]);

        } catch (ModelNotFoundException $e) {
            Log::error('Jadwal not found for delete:', ['id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'Jadwal tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error in destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus jadwal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ Method show untuk mendapatkan satu jadwal
    public function show($id)
    {
        try {
            $jadwal = Jadwal::with(['film', 'studio'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $jadwal
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jadwal',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}