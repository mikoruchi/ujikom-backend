<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Studio;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StudioController extends Controller
{
    public function index()
    {
        $studios = Studio::all();
        return response()->json([
            'success' => true,
            'data' => $studios
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'studio' => 'required|string|max:100',
            'capacity' => 'required|integer|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        $studio = Studio::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Studio berhasil ditambahkan',
            'data' => $studio
        ], 201);
    }

    public function show($id)
    {
        try {
            $studio = Studio::findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $studio
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Studio tidak ditemukan'
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $studio = Studio::findOrFail($id);

            $validated = $request->validate([
                'studio' => 'sometimes|required|string|max:100',
                'capacity' => 'sometimes|required|integer|min:1',
                'description' => 'nullable|string|max:255',
            ]);

            $studio->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Studio berhasil diperbarui',
                'data' => $studio
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Studio tidak ditemukan'
            ], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $studio = Studio::findOrFail($id);
            $studio->delete();

            return response()->json([
                'success' => true,
                'message' => 'Studio berhasil dihapus'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Studio tidak ditemukan'
            ], 404);
        }
    }
}
