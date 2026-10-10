<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FileMaintenance;
use App\Services\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FileMaintenanceApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = FileMaintenance::query()->with(['creator', 'updater']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('file_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $sortField = $request->input('sort', 'created_at');
        $sortDir = $request->input('direction', 'desc');
        $allowedSorts = ['file_number', 'title', 'category', 'status', 'effective_date', 'expiry_date', 'created_at'];
        if (!in_array($sortField, $allowedSorts)) {
            $sortField = 'created_at';
        }

        $files = $query->orderBy($sortField, $sortDir)->paginate($request->input('per_page', 15));

        return response()->json($files);
    }

    public function show(FileMaintenance $file): JsonResponse
    {
        $file->load(['creator', 'updater']);
        return response()->json(['data' => $file]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file_number' => ['required', 'string', 'max:50', 'unique:file_maintenance,file_number'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:100'],
            'status' => ['required', 'in:active,expired,archived,draft'],
            'file_path' => ['nullable', 'string', 'max:500'],
            'file_type' => ['nullable', 'string', 'max:50'],
            'file_size' => ['nullable', 'integer', 'min:0'],
            'effective_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:effective_date'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ]);

        DB::transaction(function () use ($request, $data, &$file) {
            $file = FileMaintenance::create($data);
            Audit::log('file_maintenance.create', "Created file: {$file->file_number} - {$file->title}", $file);
        });

        return response()->json(['data' => $file, 'message' => 'File created.'], 201);
    }

    public function update(Request $request, FileMaintenance $file): JsonResponse
    {
        $data = $request->validate([
            'file_number' => ['sometimes', 'string', 'max:50', 'unique:file_maintenance,file_number,' . $file->id],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'in:active,expired,archived,draft'],
            'file_path' => ['nullable', 'string', 'max:500'],
            'file_type' => ['nullable', 'string', 'max:50'],
            'file_size' => ['nullable', 'integer', 'min:0'],
            'effective_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:effective_date'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ]);

        DB::transaction(function () use ($request, $file, $data) {
            $file->update($data);
            Audit::log('file_maintenance.update', "Updated file: {$file->file_number} - {$file->title}", $file);
        });

        return response()->json(['data' => $file, 'message' => 'File updated.']);
    }

    public function destroy(FileMaintenance $file): JsonResponse
    {
        DB::transaction(function () use ($file) {
            Audit::log('file_maintenance.delete', "Deleted file: {$file->file_number} - {$file->title}", $file);
            $file->delete();
        });

        return response()->json(['message' => 'File deleted.']);
    }

    public function search(Request $request): JsonResponse
    {
        $term = $request->input('term', '');
        $files = FileMaintenance::where('file_number', 'like', "%{$term}%")
            ->orWhere('title', 'like', "%{$term}%")
            ->limit(10)
            ->get(['id', 'file_number', 'title', 'category', 'status']);

        return response()->json($files->map(fn ($f) => [
            'id' => $f->id,
            'file_number' => $f->file_number,
            'title' => $f->title,
            'category' => $f->category,
            'status' => $f->status,
        ]));
    }

    public function categories(Request $request): JsonResponse
    {
        $categories = FileMaintenance::select('category')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('category')
            ->get();

        return response()->json($categories);
    }
}