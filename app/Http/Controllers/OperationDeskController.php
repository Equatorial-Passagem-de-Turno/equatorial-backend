<?php

namespace App\Http\Controllers;

use App\Models\OperationDesk;
use Symfony\Component\HttpFoundation\JsonResponse;

class OperationDeskController extends Controller
{
    public function index(): JsonResponse
    {
        $desks = OperationDesk::where('is_active', true)->get(['id', 'code', 'name', 'location']);
        
        return response()->json($desks, 200);
    }
}
