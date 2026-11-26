<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        return UserResource::collection(User::orderBy('name')->get());
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = new User(Arr::only($data, ['name', 'email', 'password']));
        $user->role = Role::from($data['role']);
        $user->is_active = true;
        $user->save();

        return (new UserResource($user->refresh()))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();
        $isSelf = $request->user()->is($user);

        if ($isSelf && array_key_exists('role', $data) && $data['role'] !== $user->role->value) {
            throw ValidationException::withMessages(['role' => 'Kamu tidak bisa mengubah peranmu sendiri.']);
        }

        if ($isSelf && array_key_exists('is_active', $data) && ! $data['is_active']) {
            throw ValidationException::withMessages(['is_active' => 'Kamu tidak bisa menonaktifkan akunmu sendiri.']);
        }

        $user->fill(Arr::only($data, ['name', 'email']));
        $revokeTokens = false;

        if (! empty($data['password'])) {
            $user->password = $data['password'];
            $revokeTokens = true;
        }

        if (array_key_exists('role', $data) && $data['role'] !== $user->role->value) {
            $user->role = Role::from($data['role']);
            $revokeTokens = true;
        }

        if (array_key_exists('is_active', $data)) {
            $user->is_active = (bool) $data['is_active'];
            $revokeTokens = $revokeTokens || ! $user->is_active;
        }

        $user->save();

        if ($revokeTokens && ! $isSelf) {
            $user->tokens()->delete();
        }

        return new UserResource($user->refresh());
    }
}