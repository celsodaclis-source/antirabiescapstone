<?php

namespace App\Http\Controllers;

use App\Models\FileMaintenance;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FileMaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        $query = FileMaintenance::query()->with(['creator', 'updater']);

        $search = $request->input('search', '');
        $filterCategory = $request->input('category', '');
        $filterStatus = $request->input('status', '');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('file_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($filterCategory) {
            $query->where('category', $filterCategory);
        }

        if ($filterStatus) {
            $query->where('status', $filterStatus);
        }

        $sortField = $request->input('sort', 'created_at');
        $sortDir = $request->input('direction', 'desc');
        $allowedSorts = ['file_number', 'title', 'category', 'status', 'effective_date', 'expiry_date', 'created_at'];
        if (!in_array($sortField, $allowedSorts)) {
            $sortField = 'created_at';
        }

        $files = $query->orderBy($sortField, $sortDir)->paginate(15)->appends(request()->query());

        $categories = FileMaintenance::select('category')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('category')
            ->get();

        return view('files.index', compact('files', 'search', 'filterCategory', 'filterStatus', 'categories'));
    }

    public function create(): View
    {
        return view('files.create');
    }

    public function store(Request $request): RedirectResponse
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
            'tags' => ['nullable', 'string'],
        ]);

        if ($request->filled('tags')) {
            $data['tags'] = array_filter(array_map('trim', explode(',', $data['tags'])));
        }

        DB::transaction(function () use ($data, &$file) {
            $file = FileMaintenance::create($data);
            Audit::log('file_maintenance.create', "Created file: {$file->file_number} - {$file->title}", $file);
        });

        return redirect()->route('files.index')->with('success', 'File created successfully.');
    }

    public function show(FileMaintenance $file): View
    {
        $file->load(['creator', 'updater']);
        return view('files.show', compact('file'));
    }

    public function edit(FileMaintenance $file): View
    {
        return view('files.edit', compact('file'));
    }

    public function update(Request $request, FileMaintenance $file): RedirectResponse
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
            'tags' => ['nullable', 'string'],
        ]);

        if (isset($data['tags']) && $data['tags'] !== null) {
            $data['tags'] = array_filter(array_map('trim', explode(',', $data['tags'])));
        }

        DB::transaction(function () use ($file, $data) {
            $file->update($data);
            Audit::log('file_maintenance.update', "Updated file: {$file->file_number} - {$file->title}", $file);
        });

        return redirect()->route('files.index')->with('success', 'File updated successfully.');
    }

    public function destroy(FileMaintenance $file): RedirectResponse
    {
        DB::transaction(function () use ($file) {
            Audit::log('file_maintenance.delete', "Deleted file: {$file->file_number} - {$file->title}", $file);
            $file->delete();
        });

        return redirect()->route('files.index')->with('success', 'File deleted successfully.');
    }
}