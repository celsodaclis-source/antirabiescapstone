<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        $thresholds = [
            'inventory_low_stock' => SystemSetting::get('inventory_low_stock_threshold', env('INVENTORY_LOW_STOCK_THRESHOLD', 10)),
            'inventory_expiry_warning' => SystemSetting::get('inventory_expiry_warning_days', env('INVENTORY_EXPIRY_WARNING_DAYS', 30)),
            'sms_reminder_lead' => SystemSetting::get('sms_reminder_lead_hours', env('SMS_REMINDER_LEAD_HOURS', 24)),
        ];

        return view('settings.index', compact('thresholds'));
    }

    public function users(): View
    {
        $users = User::orderBy('role')->orderBy('name')->paginate(15);
        return view('settings.users', compact('users'));
    }

    public function createUser(): View
    {
        $roles = User::roles();
        return view('settings.users-create', compact('roles'));
    }

    public function editUser(User $user): View
    {
        $roles = User::roles();
        return view('settings.users-edit', compact('user', 'roles'));
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:'.implode(',', User::roles())],
            'contact_number' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'contact_number' => $data['contact_number'],
        ]);

        Audit::log('user.create', "Created user {$user->name} ({$user->role})", $user);

        return redirect()->route('settings.users')->with('status', "User {$user->name} created.");
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'role' => ['required', 'in:'.implode(',', User::roles())],
            'contact_number' => ['nullable', 'string', 'max:20'],
        ]);

        if ($request->user()->is($user) && $data['role'] !== 'Admin') {
            return back()->withErrors(['role' => 'You cannot remove Admin access from your own account.'])->withInput();
        }

        if ($user->role === 'Admin' && $data['role'] !== 'Admin' && User::where('role', 'Admin')->count() <= 1) {
            return back()->withErrors(['role' => 'The system must retain at least one Admin account.'])->withInput();
        }

        $user->update($data);
        Audit::log('user.update', "Updated user {$user->name} ({$user->role})", $user);

        return redirect()->route('settings.users')->with('status', "User {$user->name} updated.");
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return redirect()->route('settings.users')->withErrors(['user' => 'You cannot delete your own account.']);
        }

        if ($user->role === 'Admin' && User::where('role', 'Admin')->count() <= 1) {
            return redirect()->route('settings.users')->withErrors(['user' => 'The system must retain at least one Admin account.']);
        }

        Audit::log('user.delete', "Deleted user {$user->name} ({$user->role})", $user);
        $user->delete();

        return redirect()->route('settings.users')->with('status', "User {$user->name} deleted.");
    }

    /** Reset a user's password. This route is protected by the Admin-only settings group. */
    public function resetUserPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);
        Audit::log('user.password_reset', "Reset password for user {$user->name}", $user);

        return redirect()->route('settings.users')
            ->with('status', "Password reset for {$user->name}.");
    }

    public function audit(Request $request): View
    {
        $query = AuditLog::with('user')->latest('created_at');

        if ($action = $request->input('action')) {
            $query->where('action', 'like', "%{$action}%");
        }

        $logs = $query->paginate(25)->withQueryString();
        return view('settings.audit', compact('logs', 'action'));
    }

    public function config(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'inventory_low_stock' => ['required', 'integer', 'min:0'],
            'inventory_expiry_warning' => ['required', 'integer', 'min:0'],
            'sms_reminder_lead' => ['required', 'integer', 'min:0'],
        ]);

        SystemSetting::set('inventory_low_stock_threshold', $data['inventory_low_stock']);
        SystemSetting::set('inventory_expiry_warning_days', $data['inventory_expiry_warning']);
        SystemSetting::set('sms_reminder_lead_hours', $data['sms_reminder_lead']);

        Audit::log('settings.config', 'Updated system thresholds');

        return redirect()->route('settings.index')->with('status', 'System settings saved.');
    }
}
