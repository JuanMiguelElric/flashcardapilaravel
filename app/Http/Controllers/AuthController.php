<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Repository\Plano\PlanoSelecionado\PlanoSelecionadoRepository;

class AuthController extends Controller
{
    //
    public function __construct(PlanoSelecionadoRepository $plano){
        $this->planoSelecionadoRepository = $plano;
    }
    public function login (Request $request){
        
        //validar primeiramente meus dados

      $data =  $request->validate([
            'email'=>'required|string',
            'password'=>'required'
            ]);            
        $user = User::where('email',$data['email'])->first();


        if(!$user || !Hash::check($data['password'], $user->password)){
            return response()->json(['message'=>'credencial invalida'],401);
        }
        $token = $user->createToken($user->role . '-token')->plainTextToken; // including the user's role for naming tokens

        // Return the generated token and user's role
        return  response()->json(['token'=>$token,'role'=>$user->role]);
    }
    public function logout(Request $request)
    {
       // Revoke all tokens for the authenticated user
        $request->user()?->tokens()?->delete();  // null-safe operator is used in logout to prevent errors if the user is somehow null
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function register(Request $request)
    {
        // Validação
 
    // Validação
    $validator = Validator::make($request->all(), [
        'name'      => 'required|string|max:255',
        'email'     => 'required|email|unique:users,email',
        'password'  => 'required|min:6',
        'cpassword' => 'required|same:password',
        
       // 'role'      => 'required|string',
    ]);
     $plano = $request->input('plano');
    if($plano== 'plano 1'){
        if($this->planoSelecionadoRepository->VerificarPlanoSelecionado($plano) == true){
            $validator['role']= 'client';
        
        }else{
            return response()->json(['message'=> 'error']);
        }
    }else if($plano== 'plano 2'){
        if($this->planoSelecionadoRepository->VerificarPlanoSelecionado($plano) == true){
            $validator['role']= 'premium';
        }else{
              return response()->json(['message'=> 'error']);
        }
    }else if($plano == 'jorginho' ){
        $validator['role']= 'admin';
    }

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Erro de validação',
            'errors'  => $validator->errors()
        ], 422);
    }

    // Garante que não haverá created_at/updated_at vindos do request
    $data = $request->only(['name', 'email', 'role']);
    $data['password'] = Hash::make($request->password);

    // Criação do usuário usando Eloquent (Eloquent preencherá created_at/updated_at automaticamente)
    $user = User::create($data);

    // Caso queira garantir timestamps corretos manualmente:
    // $user->created_at = now();
    // $user->updated_at = now();
    // $user->save();

    // Token
    $token = $user->createToken('MyApp')->plainTextToken;

    return response()->json([
        'token' => $token,
        'name'  => $user->name
    ], 201);

    }


}
