<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function authenticate(Request $request)
    {        
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]); 

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Email ou senha inválidos.'
            ], 401);
        }

        if (! $user->active) {
            return response()->json([
                'message' => 'Sua conta está inativa. Entre em contato com o suporte.'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // VERIFICAÇÃO DO TURNO ATIVO 
        $activeShift = Shift::with('desk')
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->first();

        $activeShiftData = null;
        if ($activeShift) {
            $activeShiftData = [
                'role' => $activeShift->role,
                'desk' => [
                    'id' => (string) $activeShift->operation_desk_id,
                    'name' => $activeShift->desk ? $activeShift->desk->name : 'Mesa Operacional',
                    'nome' => $activeShift->desk ? $activeShift->desk->name : 'Mesa Operacional',
                    'code' => $activeShift->desk ? $activeShift->desk->code : null,
                ]
            ];
        }

        return response()->json([
            'message' => 'Login realizado com sucesso',
            'token' => $token,
            'usuario' => $user,
            'active_shift' => $activeShiftData // Retorna null ou os dados do turno
        ]);
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao realizar logout: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'message' => 'Até amanhã!'
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ], [
            'email.exists' => 'Não encontramos nenhum usuário com este e-mail.'
        ]);

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => $token, 
                'created_at' => Carbon::now()
            ]
        );

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $resetLink = $frontendUrl . '/reset-password?token=' . $token . '&email=' . $request->email;

        return response()->json([
            'message' => 'Link de recuperação gerado com sucesso.',    
            'debug_link' => $resetLink // REMOVER EM PRODUÇÃO
        ]);
    }

    public function resetPassword(Request $request)
    {   
        $request->validate([    
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'min:6', 'confirmed'], 
        ]);

        $resetRequest = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (! $resetRequest) {
            return response()->json([
                'message' => 'Token inválido ou não encontrado.'
            ], 400);
        }

        $tokenIsExpired = Carbon::parse($resetRequest->created_at)->addMinutes(60)->isPast();
        if ($tokenIsExpired) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'Este link expirou. Por favor, solicite um novo.'
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Sua senha foi redefinida com sucesso! Você já pode fazer login.'
        ]);
    }
}