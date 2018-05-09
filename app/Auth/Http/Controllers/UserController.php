<?php

namespace App\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\User;
use App\Auth\Models\Users;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Register a new user
     *
     * @param   request     $request
     * @return  mixed
     */
    protected function signUp(Request $request) {
        if(!$request) {
            return response()->json(['error' => 'No request data sent...'], 200);
        }
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required'
        ]);

        $user = new User([
           'name' => $request->input('name'),
           'email' => $request->input('email'),
           'password' => bcrypt($request->input('password'))
        ]);

        $user->save();
        return response()->json([
            'message' => 'User created successfully!'
        ], 201);
    }

    /**
     * Get current authenticated user
     *
     * @return  mixed
     */
    protected function getAuthUser() {
        return response()->json(Auth::user(), 200);
    }

    /**
     * Get all users
     * To get all users without current authenticated user,
     * set '$except_current_user' to true in request.
     *
     * @param   boolean     $except_current_user
     * @return  mixed
     */
    protected function getUsers($except_current_user = null) {
        if ($except_current_user || $except_current_user == 'true') {
            return response()->json(Users::select('id', 'name', 'email')->where('id', '!=', Auth::id())->get(), 200);
        }
        return response()->json(Users::all(), 200);
    }

    /**
     * Get permission of the current authenticated user
     *
     * @return  mixed
     */
    protected function getUserPermissions() {
        return explode(',', Auth::user()['permissions']);
    }
}