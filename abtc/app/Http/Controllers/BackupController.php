<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Services\Audit;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class BackupController extends Controller
{
    public function __construct(protected BackupService $backupService) {}

    public function index(): View
    {
        $backups = $this->backupService->getBackups();
        $stats = $this->backupService->getStats();
        return view('settings.backup', compact('backups', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $backup = $this->backupService->create($request->input('notes'));
        Audit::log('backup.create', "Created database backup: {$backup->filename}", $backup);

        return redirect()->route('settings.backup')->with('status', 'Backup created successfully.');
    }

    public function download(Backup $backup): Response
    {
        $path = $this->backupService->getPath($backup);
        if (!File::exists($path)) {
            abort(404, 'Backup file not found.');
        }

        Audit::log('backup.download', "Downloaded backup: {$backup->filename}", $backup);

        return response()->download($path, $backup->filename);
    }

    public function destroy(Backup $backup): RedirectResponse
    {
        $filename = $backup->filename;
        $this->backupService->delete($backup);
        Audit::log('backup.delete', "Deleted backup: {$filename}", $backup);

        return redirect()->route('settings.backup')->with('status', 'Backup deleted.');
    }
}
