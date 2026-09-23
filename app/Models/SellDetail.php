<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SellDetail extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = ['sell_id', 'product_id', 'quantity', 'selling_price','imei'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function sell()
    {
        return $this->belongsTo(Sell::class);
    }
}
