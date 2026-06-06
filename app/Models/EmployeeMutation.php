<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeMutation extends Model
{
    protected $table = 'employee_mutations';

    protected $fillable = [
        'employee_id',
        'from_company_id',
        'to_company_id',
        'from_nik',
        'to_nik',
        'mutated_at',
        'actor_user_id',
        'note',
    ];

    public $timestamps = false;

    protected $casts = [
        'mutated_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function fromCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'from_company_id');
    }

    public function toCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'to_company_id');
    }
}

