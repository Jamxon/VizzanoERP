<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @method static orderBy(string $string, string $string1)
 * @method static create(array $data)
 * @method static findOrFail($id)
 * @method static where(string $string, $branch_id)
 */
class Transport extends Model
{
    use HasFactory;

    protected $table = 'transport';

    protected $fillable = [
        'name',                         // Transport nomi (masalan, "Damas", "MAN yuk mashinasi")
        'state_number',                 // Avtomobil davlat raqami (masalan, "50 000 AAA")
        'driver_full_name',             // Haydovchining to‘liq ismi
        'phone',                        // Haydovchining asosiy telefon raqami
        'phone_2',                      // Qo‘shimcha telefon raqami (ixtiyoriy)
        'capacity',                     // Yuk sig‘imi (masalan, tonna yoki boshqa birlik)
        'branch_id',                    // Qaysi filialga tegishli ekanligi (foreign key)
        'region_id',                    // Ro‘yxatdan o‘tgan hudud IDsi (foreign key)
        'is_active',                    // Holati: aktiv yoki aktiv emas (true/false)
        'salary',                       // Haydovchining kunlik maoshi
        'fuel_bonus',                   // Haydovchiga beriladigan yoqilg‘i bonusi
        'balance',                      // Transport balansidagi mablag‘ (default 0)
        'distance'
    ];

    /**
     * Ma'lumot turlarini avtomatik convert qilish
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function region(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function transportAttendances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TransportAttendance::class);
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TransportTransaction::class, 'transport_id');
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_transport');
    }

    public function dailyEmployees()
    {
        return $this->hasMany(EmployeeTransportDaily::class);
    }

}