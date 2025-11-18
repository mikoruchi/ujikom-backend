<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CashierController extends Controller
{
    // GET LIST KASIR
    public function index()
    {
        try {
            $cashiers = User::where('role', 'cashier')
                ->select('id', 'name', 'email', 'phone', 'shift', 'status', 'created_at')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $cashiers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kasir',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // CREATE KASIR BARU
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'shift' => 'required|in:Pagi,Siang,Malam',
            'password' => 'required|min:6|confirmed', // TAMBAH VALIDASI PASSWORD
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $cashier = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'phone'    => $request->phone,
                'shift'    => $request->shift,
                'role'     => 'cashier',
                'status'   => 'active',
                'password' => Hash::make($request->password), // GUNAKAN PASSWORD DARI INPUT
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kasir berhasil dibuat', 
                'data' => $cashier
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat kasir',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // UPDATE STATUS (Active/Inactive)
    public function updateStatus($id)
    {
        try {
            $cashier = User::where('role', 'cashier')->find($id);

            if (!$cashier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kasir tidak ditemukan'
                ], 404);
            }

            $cashier->status = $cashier->status === 'active' ? 'inactive' : 'active';
            $cashier->save();

            return response()->json([
                'success' => true,
                'message' => 'Status berhasil diperbarui',
                'data' => $cashier
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // DELETE KASIR
    public function destroy($id)
    {
        try {
            $cashier = User::where('role', 'cashier')->find($id);

            if (!$cashier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kasir tidak ditemukan'
                ], 404);
            }

            $cashier->delete();

            return response()->json([
                'success' => true,
                'message' => 'Kasir berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus kasir',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}