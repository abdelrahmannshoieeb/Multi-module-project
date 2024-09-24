<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class VendorEmployee extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['remember_token'];

    protected $casts = [
        'employee_role_id' => 'integer',
        'vendor_id' => 'integer',
        'store_id' => 'integer',
        // 'status' => 'integer',
        'is_logged_in' => 'boolean',
    ];

    protected $hidden = [
        'password',
        'auth_token',
        'remember_token',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function role(){
        return $this->belongsTo(EmployeeRole::class,'employee_role_id');
    }
}
