<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    //
    public function login (Request $request){
        
        //validar primeiramente meus dados
        $request->validate([
            'email'=>'required|email',
            'password'=>'required'
        ]);

        $user = User::where('email',$request->email())->first();

        if(!$user || !Hash::check($request->password, $user->password)){
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
     
        $validator = Validator::make($request->all(), [
            'name'      => 'required',
            'email'     => 'required|email',
            'password'  => 'required',
            'cpassword' => 'required|same:password',
            'role' => 'required',


        ]);


        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }


        $input             = $request->all();
        $input['password'] = bcrypt($input['password']);
        $user              = User::create($input);
        $success['token']  = $user->createToken('MyApp')->plainTextToken;
        $success['name']   = $user->name;


        return  response()->json(['token'=>$success]);
    }


}
