<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class UserController extends Controller
{
    /**
     * ✅ Ambil semua user (khusus admin)
     */
    public function index()
{
    $users = User::where('role', 'user')->get();
    return response()->json([
        'data' => $users
    ]);
}


    /**
     * ✅ Ambil data user yang sedang login
     */
    public function getUser(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()
        ], 200);
    }

    /**
     * ✅ Update data user yang sedang login (termasuk ubah password)
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'profile' => 'nullable|string',
            'old_password' => 'nullable|string|min:6',
            'new_password' => 'nullable|string|min:6|confirmed',
        ]);

        // 🔒 Jika user ingin ganti password
        if ($request->filled('old_password') && $request->filled('new_password')) {
            if (!Hash::check($request->old_password, $user->password)) {
                throw ValidationException::withMessages([
                    'old_password' => ['Password lama tidak sesuai.']
                ]);
            }

            $user->update([
                'password' => Hash::make($request->new_password)
            ]);
        }

        // 🔄 Update data user lainnya
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'profile' => $request->profile ?? $user->profile,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui',
            'user' => $user
        ], 200);
    }

    /**
     * ✅ Admin ubah status user (active / inactive)
     */
    public function updateStatus(Request $request, $id)
    {
        // Hanya admin yang bisa ubah status
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'status' => 'required|in:active,inactive'
        ]);

        $user = User::findOrFail($id);
        $user->status = strtolower($request->status);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Status pengguna berhasil diperbarui',
            'user' => $user
        ], 200);
    }
}
