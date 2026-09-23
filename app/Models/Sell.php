<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sell extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['customer_name', 'customer_phone', 'customer_address', 'grand_total','invoice'];

    public function sellDetails()
    {
        return $this->hasMany(SellDetail::class);
    }
}
