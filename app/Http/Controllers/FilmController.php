<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Film;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;

class FilmController extends Controller
{
    // ✅ Ambil semua film
    public function index()
    {
        try {
            $films = Film::orderBy('created_at', 'desc')->get();
            
            return response()->json([
                'success' => true,
                'data' => $films,
                'count' => $films->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data film',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ Simpan film baru
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:200',
                'genre' => 'required|string|max:100',
                'duration' => 'required|integer|min:1',
                'rating' => 'nullable|numeric|between:0,10',
                'release_date' => 'nullable|date',
                'status' => 'required|string|max:50',
                'studio' => 'required|string|max:100',
                'poster' => 'nullable|string|max:500',
                'synopsis' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $film = Film::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Film berhasil ditambahkan',
                'data' => $film
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan film',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ Ambil satu film
    public function show($id)
    {
        try {
            $film = Film::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $film
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Film tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data film',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ Update film
    public function update(Request $request, $id)
    {
        try {
            $film = Film::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:200',
                'genre' => 'sometimes|required|string|max:100',
                'duration' => 'sometimes|required|integer|min:1',
                'rating' => 'nullable|numeric|between:0,10',
                'release_date' => 'nullable|date',
                'status' => 'sometimes|required|string|max:50',
                'studio' => 'sometimes|required|string|max:100',
                'poster' => 'nullable|string|max:500',
                'synopsis' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $film->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Film berhasil diperbarui',
                'data' => $film
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Film tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui film',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ Hapus film
    public function destroy($id)
    {
        try {
            $film = Film::findOrFail($id);
            $film->delete();

            return response()->json([
                'success' => true,
                'message' => 'Film berhasil dihapus'
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Film tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus film',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}