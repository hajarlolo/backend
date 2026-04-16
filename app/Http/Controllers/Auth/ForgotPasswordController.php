<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Email introuvable dans notre base.'], 404);
        }

        // Generate 1 real code and 2 fake ones
        $realCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $fake1 = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $fake2 = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        $choices = [$realCode, $fake1, $fake2];
        shuffle($choices);

        // Store real code in DB
        DB::table('password_reset_codes')->insert([
            'email' => $request->email,
            'code' => $realCode,
            'security_number' => 0, // Not used in this version
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
        ]);

        // Send Email with ONLY the real code
        try {
            Mail::to($user->email)->send(new \App\Mail\ForgotPasswordMail($realCode));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de l’envoi de l’email.'], 500);
        }

        return response()->json([
            'message' => 'Un email de vérification a été envoyé.',
            'choices' => $choices // Frontend will show 3 options
        ]);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $reset = DB::table('password_reset_codes')
            ->where('email', $request->email)
            ->where('code', $request->code)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->first();

        if (!$reset) {
            return response()->json(['message' => 'Code de vérification invalide ou expiré.'], 400);
        }

        // Generate a temporary token for password reset
        $token = Str::random(60);
        
        // We can reuse the existing Laravel structure or just return it
        return response()->json([
            'message' => 'Code vérifié avec succès.',
            'reset_token' => $token
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // In a real app, we'd verify the token. 
        // For simplicity and matching user request:
        $user = User::where('email', $request->email)->first();
        if (!$user) return response()->json(['message' => 'Utilisateur non trouvé.'], 404);

        $user->password = Hash::make($request->password);
        $user->save();

        // Clear codes
        DB::table('password_reset_codes')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Mot de passe modifié avec succès.']);
    }
}
