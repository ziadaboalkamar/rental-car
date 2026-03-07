<?php

namespace App\Services\Maintenance;

use App\Enums\CarStatus;
use App\Enums\MaintenanceRecordStatus;
use App\Enums\UserRole;
use App\Models\Car;
use App\Models\CarMaintenance;
use App\Models\User;
use App\Notifications\MaintenanceScheduleNotification;
use Illuminate\Support\Carbon;

class MaintenanceScheduleService
{
    /**
     * @return array<string, int>
     */
    public function run(bool $dryRun = false): array
    {
        $startedCount = 0;
        $completedCount = 0;
        $upcomingNotifiedCount = 0;

        $startedCount = $this->startDueMaintenances($dryRun);
        $completedCount = $this->completeFinishedMaintenances($dryRun);
        $upcomingNotifiedCount = $this->notifyUpcomingMaintenances($dryRun);

        return [
            'started' => $startedCount,
            'completed' => $completedCount,
            'upcoming_notified' => $upcomingNotifiedCount,
        ];
    }

    private function startDueMaintenances(bool $dryRun): int
    {
        $today = Carbon::today()->toDateString();

        $dueRecords = CarMaintenance::query()
            ->withoutGlobalScope('tenant')
            ->where('status', MaintenanceRecordStatus::SCHEDULED->value)
            ->whereNotNull('scheduled_date')
            ->whereDate('scheduled_date', '<=', $today)
            ->get();

        $count = 0;
        foreach ($dueRecords as $record) {
            $count++;
            if ($dryRun) {
                continue;
            }

            $record->status = MaintenanceRecordStatus::IN_PROGRESS;
            $record->started_at = $record->started_at ?? now();
            $record->save();

            $car = Car::query()->withoutGlobalScope('tenant')->find($record->car_id);
            if ($car && $car->status !== CarStatus::MAINTENANCE) {
                $car->update(['status' => CarStatus::MAINTENANCE->value]);
            }

            $carLabel = $this->carLabel($record);
            $this->notifyTenantAdmins(
                $record,
                kind: 'maintenance_started',
                title: 'Maintenance started',
                message: "Maintenance started for {$carLabel}."
            );
        }

        return $count;
    }

    private function completeFinishedMaintenances(bool $dryRun): int
    {
        $now = now();

        $finishedRecords = CarMaintenance::query()
            ->withoutGlobalScope('tenant')
            ->where('status', MaintenanceRecordStatus::IN_PROGRESS->value)
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', $now)
            ->get();

        $count = 0;
        foreach ($finishedRecords as $record) {
            $count++;
            if ($dryRun) {
                continue;
            }

            $record->status = MaintenanceRecordStatus::COMPLETED;
            $record->save();

            $car = Car::query()->withoutGlobalScope('tenant')->find($record->car_id);
            if ($car && $car->status === CarStatus::MAINTENANCE) {
                $hasAnotherInProgress = CarMaintenance::query()
                    ->withoutGlobalScope('tenant')
                    ->where('car_id', $record->car_id)
                    ->where('id', '!=', $record->id)
                    ->where('status', MaintenanceRecordStatus::IN_PROGRESS->value)
                    ->exists();

                if (!$hasAnotherInProgress) {
                    $car->update(['status' => CarStatus::AVAILABLE->value]);
                }
            }

            $carLabel = $this->carLabel($record);
            $this->notifyTenantAdmins(
                $record,
                kind: 'maintenance_completed',
                title: 'Maintenance completed',
                message: "Maintenance completed for {$carLabel}."
            );
        }

        return $count;
    }

    private function notifyUpcomingMaintenances(bool $dryRun): int
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        $upcomingRecords = CarMaintenance::query()
            ->withoutGlobalScope('tenant')
            ->where('status', MaintenanceRecordStatus::SCHEDULED->value)
            ->whereDate('scheduled_date', $tomorrow)
            ->get();

        $count = 0;
        foreach ($upcomingRecords as $record) {
            if ($dryRun) {
                $count++;
                continue;
            }

            $carLabel = $this->carLabel($record);
            $sent = $this->notifyTenantAdmins(
                $record,
                kind: 'maintenance_due_tomorrow',
                title: 'Maintenance reminder',
                message: "Maintenance is scheduled tomorrow for {$carLabel}.",
                oncePerDay: true
            );

            if ($sent > 0) {
                $count += $sent;
            }
        }

        return $count;
    }

    private function carLabel(CarMaintenance $record): string
    {
        if (!$record->relationLoaded('car') || !$record->car) {
            return 'car #'.$record->car_id;
        }

        return trim("{$record->car->year} {$record->car->make} {$record->car->model} ({$record->car->license_plate})");
    }

    private function notifyTenantAdmins(
        CarMaintenance $record,
        string $kind,
        string $title,
        string $message,
        bool $oncePerDay = false
    ): int {
        $admins = User::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $record->tenant_id)
            ->where('role', UserRole::ADMIN)
            ->where('is_active', true)
            ->get();

        $sent = 0;

        foreach ($admins as $admin) {
            if ($oncePerDay && $this->alreadySentToday($admin, $record->id, $kind)) {
                continue;
            }

            $admin->notify(new MaintenanceScheduleNotification($record, $kind, $title, $message));
            $sent++;
        }

        return $sent;
    }

    private function alreadySentToday(User $user, int $maintenanceId, string $kind): bool
    {
        return $user->notifications()
            ->where('type', MaintenanceScheduleNotification::class)
            ->where('data->maintenance_id', $maintenanceId)
            ->where('data->kind', $kind)
            ->whereDate('created_at', today())
            ->exists();
    }
}
