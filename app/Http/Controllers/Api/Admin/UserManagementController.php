<?php
/**
 * FILE: backend/app/Http/Controllers/Api/Admin/UserManagementController.php
 * STATUS: DIUBAH (cegah suspend admin)
 */


namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->with('tutorProfile');

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return UserResource::collection($query->latest()->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['siswa', 'tutor'])],
            'status' => ['sometimes', Rule::in(['active', 'suspended', 'pending'])],
            'phone' => ['nullable', 'string', 'max:15'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => $validated['status'] ?? 'active',
            'phone' => $validated['phone'] ?? null,
            'email_verified_at' => now(),
        ]);

        UserSetting::create(['user_id' => $user->id]);

        return new UserResource($user->load('settings', 'tutorProfile'));
    }

    public function show(User $user)
    {
        return new UserResource($user->load('tutorProfile', 'settings'));
    }

    public function updateStatus(Request $request, User $user)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'pending'])],
        ]);

        if ($user->role === 'admin' && $validated['status'] === 'suspended') {
            return response()->json([
                'message' => 'Akun admin tidak dapat disuspend lewat halaman ini.',
            ], 422);
        }

        $user->update($validated);

        return new UserResource($user->fresh());
    }
}
