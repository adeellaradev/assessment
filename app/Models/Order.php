<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    const STATUS_OPEN = 1;
    const STATUS_FILLED = 2;
    const STATUS_CANCELLED = 3;

    const SIDE_BUY = 'buy';
    const SIDE_SELL = 'sell';

    protected $fillable = [
        'user_id',
        'symbol',
        'side',
        'price',
        'amount',
        'filled_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:8',
            'amount' => 'decimal:8',
            'filled_amount' => 'decimal:8',
            'status' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function buyTrades(): HasMany
    {
        return $this->hasMany(Trade::class, 'buy_order_id');
    }

    public function sellTrades(): HasMany
    {
        return $this->hasMany(Trade::class, 'sell_order_id');
    }

    public function getRemainingAmountAttribute(): string
    {
        return bcsub($this->amount, $this->filled_amount, 8);
    }

    public function isBuy(): bool
    {
        return $this->side === self::SIDE_BUY;
    }

    public function isSell(): bool
    {
        return $this->side === self::SIDE_SELL;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isFilled(): bool
    {
        return $this->status === self::STATUS_FILLED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
