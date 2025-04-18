<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class HikvisionPersonService
{
    protected $ip;
    protected $username;
    protected $password;

    public function __construct()
    {
        $this->ip = '192.168.118.156';
        $this->username = 'admin';
        $this->password = 'vizzano2025';
    }

    public function addPerson($personId, $name, $gender = 'male')
    {
        $url = "http://{$this->ip}/ISAPI/AccessControl/UserInfo/Record?format=json";

        $response = Http::withBasicAuth($this->username, $this->password)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post($url, [
                'UserInfo' => [
                    'employeeNo' => $personId,
                    'name' => $name,
                    'userType' => 'normal',
                    'gender' => $gender,
                    'doorRight' => '1',
                    'floorNumber' => 0,
                    'maxOpenDoorTime' => 0,
                    'valid' => [
                        'enable' => true,
                        'beginTime' => '2024-01-01T00:00:00',
                        'endTime' => '2030-01-01T00:00:00',
                    ],
                    'password' => '',
                    'roomNumber' => '',
                    'userVerifyMode' => 1,
                ]
            ]);

        return $response->json();
    }
}
