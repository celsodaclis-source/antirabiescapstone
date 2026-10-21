<?php

namespace App\Http\Controllers;

use App\Models\NurseStation;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class NurseStationController extends Controller
{
    public function assign(NurseStation $station): RedirectResponse
    {
        $user = request()->user();

        try {
            DB::transaction(function () use ($station, $user) {
                $lockedStation = NurseStation::whereKey($station->id)->lockForUpdate()->firstOrFail();

                if ($user->nurseStation()->exists()) {
                    throw new StationAssignmentException('You are already assigned to a station. Release it before choosing another.');
                }
                if ($lockedStation->user_id !== null) {
                    throw new StationAssignmentException('That station has just been assigned to another nurse.');
                }

                $lockedStation->update(['user_id' => $user->id]);
            });
        } catch (StationAssignmentException $exception) {
            return back()->withErrors(['station' => $exception->getMessage()]);
        } catch (QueryException $exception) {
            // The unique user_id constraint is the final guard against simultaneous assignments.
            if (NurseStation::where('user_id', $user->id)->exists()) {
                return back()->withErrors(['station' => 'You are already assigned to a station. Release it before choosing another.']);
            }

            throw $exception;
        }

        return back()->with('status', 'You are now on duty at '.$station->name.'.');
    }

    public function release(): RedirectResponse
    {
        $user = request()->user();

        DB::transaction(function () use ($user) {
            $station = NurseStation::where('user_id', $user->id)->lockForUpdate()->first();
            $station?->update(['user_id' => null]);
        });

        return back()->with('status', 'You are off duty and your station is available.');
    }
}

class StationAssignmentException extends \RuntimeException {}
