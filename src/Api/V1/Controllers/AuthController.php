<?php

namespace Meva\Api\V1\Controllers;

use Meva\Api\V1\Requests\User\UserLoginRequest;
use Meva\Entities\User\Services\UserLoginService;

class AuthController extends Controller
{

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @param UserLoginRequest $request
     * @param UserLoginService $userLoginService
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(UserLoginRequest $request, UserLoginService $userLoginService)
    {
        $credentials = $request->only(['email', 'password']);

        if (! $token = $userLoginService->handle($credentials)) {
            return response()->json(['error' => 'Check your credentials!'], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     *
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function user()
    {
        $user = auth()->user();

        return response()->json(['data' => array_merge(
            $user->toArray(),
            [
                'roles' => $user->getRoleNames()->values()->all(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            ]
        )]);
    }

    /**
     * Change the signed-in user's own name, e-mail or password.
     */
    public function updateProfile(\Meva\Api\V1\Requests\User\ProfileUpdateRequest $request)
    {
        $user = auth()->user();

        $user->fill($request->only(['first_name', 'last_name', 'email']));

        if ($request->filled('password')) {
            if (! \Illuminate\Support\Facades\Hash::check($request->input('current_password'), $user->password)) {
                return response()->json(['message' => 'Trenutna lozinka nije tačna.'], 422);
            }

            $user->password = $request->input('password');
        }

        $user->save();

        return $this->user();
    }



    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }


    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        $user = auth()->user();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => $user ? array_merge($user->only(['id', 'first_name', 'last_name', 'email']), [
                'roles' => $user->getRoleNames()->values()->all(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            ]) : null,
        ]);
    }
}
