<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class EmployeeCheckedIn implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $employee;
    public $time;
    public $image;

    public function __construct($employee, $time, $image)
    {
        $this->employee = $employee;
        $this->time = $time;
        $this->image = $image;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('attendance');
    }

    public function broadcastAs(): string
    {
        return 'employee.checked_in';
    }
}
