<?php

namespace App\Http\Controllers;

use App\Models\Roles;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Roles::where('is_active', 'true')->get(['id', 'name', 'description']);
        return response()->json($roles, 200);
    }
}
