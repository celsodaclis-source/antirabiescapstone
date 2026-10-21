<?php

namespace App\Http\Controllers;

use App\Models\DoctorStation;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class DoctorStationController extends Controller
{
    public function assign(DoctorStation $station): RedirectResponse
    {
        $doctor = request()->user();

        try {
            DB::transaction(function () use ($station, $doctor) {
                $lockedStation = DoctorStation::whereKey($station->id)->lockForUpdate()->firstOrFail();

                if (DoctorStation::where('user_id', $doctor->id)->exists()) {
                    throw new DoctorStationAssignmentException('You are already assigned to a station. Release it before choosing another.');
                }
                if ($lockedStation->user_id !== null) {
                    throw new DoctorStationAssignmentException('That station has just been assigned to another doctor.');
                }

                $lockedStation->update(['user_id' => $doctor->id]);
            });
        } catch (DoctorStationAssignmentException $exception) {
            return back()->withErrors(['station' => $exception->getMessage()]);
        } catch (QueryException $exception) {
            if (DoctorStation::where('user_id', $doctor->id)->exists()) {
                return back()->withErrors(['station' => 'You are already assigned to a station. Release it before choosing another.']);
            }

            if (DoctorStation::whereKey($station->id)->whereNotNull('user_id')->exists()) {
                return back()->withErrors(['station' => 'That station has just been assigned to another doctor.']);
            }

            throw $exception;
        }

        return redirect()->route('dashboard')->with('status', 'You are now on duty at '.$station->name.'. You can continue to the patient queue.');
    }

    public function release(): RedirectResponse
    {
        $doctor = request()->user();

        DB::transaction(function () use ($doctor) {
            $station = DoctorStation::where('user_id', $doctor->id)->lockForUpdate()->first();
            $station?->update(['user_id' => null]);
        });

        return back()->with('status', 'You are off duty and your station is available.');
    }
}

class DoctorStationAssignmentException extends \RuntimeException {}
