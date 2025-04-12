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

    protected $fillable = [
        'name',                         // Transport nomi (masalan, "Damas", "MAN yuk mashinasi")
        'state_number',                 // Avtomobil davlat raqami (masalan, "50 000 AAA")
        'driver_full_name',            // Haydovchining to‘liq ismi
        'phone',                        // Haydovchining asosiy telefon raqami
        'phone_2',                      // Qo‘shimcha telefon raqami (ixtiyoriy)
        'capacity',                     // Yuk sig‘imi (masalan, tonna yoki boshqa birlik)
        'branch_id',                    // Qaysi filialga tegishli ekanligi (foreign key)
        'region_id',                    // Ro‘yxatdan o‘tgan hudud IDsi (foreign key)
        'is_active',                    // Holati: aktiv yoki aktiv emas (true/false)
        'vin_number',                   // VIN-kod (unikal avtomobil identifikatori)
        'tech_passport_number',         // Texnik pasport raqami
        'engine_number',                // Dvigatel raqami
        'year',                         // Ishlab chiqarilgan yil (raqam sifatida, masalan 2018)
        'color',                        // Transport vositasining rangi
        'registration_date',            // Ro‘yxatga olingan sanasi (date)
        'insurance_expiry',             // Sug‘urta muddati tugash sanasi (date)
        'inspection_expiry',            // Texnik ko‘rik muddati tugash sanasi (date)
        'driver_passport_number',       // Haydovchining pasport raqami
        'driver_license_number',        // Haydovchilik guvohnomasi raqami
        'driver_experience_years',      // Haydovchilik tajribasi (yil bilan)
        'salary',                       // Haydovchining kunlik maoshi
        'fuel_bonus',                   // Haydovchiga beriladigan yoqilg‘i bonusi
    ];

    /**
     * Ma'lumot turlarini avtomatik convert qilish
     */
    protected $casts = [
        'is_active' => 'boolean',
        'driver_experience_years' => 'integer',
        'registration_date' => 'date',
        'insurance_expiry' => 'date',
        'inspection_expiry' => 'date',
    ];

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function region(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}