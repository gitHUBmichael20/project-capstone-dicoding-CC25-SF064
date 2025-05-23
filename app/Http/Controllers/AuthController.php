<?php

namespace App\Http\Controllers;

use App\Models\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'nama_pengguna' => 'required|string|max:255',
            'nomor_telepon' => 'required|string|max:15',
            'email' => 'required|string|email|max:255|unique:pengguna',
            'password' => 'required|string|min:8',
        ]);

        $pengguna = Pengguna::create([
            'nama_pengguna' => $validated['nama_pengguna'],
            'nomor_telepon' => $validated['nomor_telepon'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('login')->with('success', 'Register berhasil! Silahkan login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);
    
        $role = null;
        $guard = null;
        $user = null;
    
        // Coba autentikasi sebagai pengguna biasa (guard 'web')
        if (Auth::guard('web')->attempt($credentials)) {
            $guard = 'web';
            $role = 'pengguna';
            $user = Auth::guard('web')->user();
        }
    
        // Jika gagal, coba autentikasi sebagai admin (guard 'admin')
        if (!$user && Auth::guard('admin')->attempt($credentials)) {
            $guard = 'admin';
            $role = 'admin';
            $user = Auth::guard('admin')->user();
        }
    
        // Jika keduanya gagal, kembalikan pesan error (sesuai kode asli)
        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Email atau Password salah'], 401);
            }
            $failedMessage = 'Email atau Password salah';
            return redirect()->route('login')->with('failed', $failedMessage);
        }
    
        // Buat token API dan simpan di session (sesuai kode asli)
        $token = $user->createToken('auth_token')->plainTextToken;
        session(['api_token' => $token]);
        session(['role' => $role]); // Tambahan: simpan role untuk redirect
    
        if ($request->expectsJson()) {
            return response()->json([
                'token' => $token,
                'message' => 'Login successful',
                'role' => $role, // Tambahan: beri tahu role di response JSON
            ], 200);
        }
    
        // Redirect berdasarkan role
        if ($role === 'admin') {
            return redirect()->route('admin.dashboard')->with('success', 'Login berhasil sebagai Admin!');
        }
    
        return redirect()->route('landing')->with('success', 'Login berhasil!');
    }

    public function logout(Request $request)
    {
        \Log::info('Logout function called for user');
    
        $user = Auth::guard('web')->user();
        $sessionId = $request->session()->getId();
        \Log::info('Session ID before logout: ' . $sessionId);
    
        if ($user) {
            // Hapus token API
            $user->tokens()->delete();
            \Log::info('User tokens deleted for user ID: ' . $user->id);
    
            // Logout user
            Auth::guard('web')->logout();
            \Log::info('User logged out');
        } else {
            \Log::info('No authenticated user found');
        }
    
        // Hapus entri session dari tabel sessions, meskipun user tidak terautentikasi
        $deleted = DB::table('sessions')->where('id', $sessionId)->delete();
        \Log::info('Session deletion result: ' . $deleted . ' rows deleted for session ID: ' . $sessionId);
    
        // Hapus session
        $request->session()->invalidate();
        \Log::info('Session invalidated');
    
        $request->session()->regenerateToken();
        \Log::info('Session token regenerated');
    
        if ($request->expectsJson()) {
            \Log::info('Returning JSON response for logout');
            return response()->json(['message' => 'Logout success!'], 200);
        }
    
        \Log::info('Redirecting to login page');
        return redirect()->route('login')->with('success', 'Logout success!');
    }

    public function showLoginForm()
    {
        return view('login');
    }

    public function showSignupForm()
    {
        return view('signup');
    }

    public function apiUser(Request $request)
    {
        return response()->json($request->user());
    }
}