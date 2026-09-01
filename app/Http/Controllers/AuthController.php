<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string',
            'password' => 'required',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'credencial invalida'], 401);
        }

        $token = $user->createToken($user->role.'-token')->plainTextToken;

        return response()->json(['token' => $token, 'role' => $user->role]);
    }

    public function logout(Request $request)
    {
        $request->user()?->tokens()?->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'cpassword' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        // O React só exibe a escolha de plano DEPOIS do cadastro (ver
        // PlanSelectionModal em memory-spark) - nenhum plano é enviado
        // neste payload. O usuário nasce com o role default do banco
        // ('client'); associar um plano/role diferente é um fluxo
        // separado, ainda não integrado ao backend (ver relatório de
        // gaps - Payment.tsx é mock hoje, sem chamada real à API).
        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'password' => Hash::make($request->string('password')),
            'role' => 'client',
        ]);

        $token = $user->createToken($user->role.'-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'name' => $user->name,
            'role' => $user->role,
        ], 201);
    }
}
