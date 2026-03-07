<?php

namespace App\Notifications;

use App\Models\CarMaintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MaintenanceScheduleNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly CarMaintenance $maintenance,
        private readonly string $kind,
        private readonly string $title,
        private readonly string $message
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            'maintenance_id' => $this->maintenance->id,
            'car_id' => $this->maintenance->car_id,
            'status' => $this->maintenance->status?->value ?? (string) $this->maintenance->status,
            'scheduled_date' => optional($this->maintenance->scheduled_date)?->toDateString(),
            'url' => '/admin/maintenance-records/'.$this->maintenance->id.'/edit',
            'created_at' => now()->toDateTimeString(),
        ];
    }
}

