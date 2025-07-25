<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;

class Seller extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'business_name',
        'email',
        'description',
        'working_hours',
        'password',
        'address',
        'latitude',
        'longitude',
        'phone',
        'is_active',
        'total_points',
        'photo_url',
        'photo_caption',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'total_points' => 'integer',
    ];

    /**
     * Automatically hash password when setting it
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::make($value);
    }

    // Relationships
    public function pointTransactions()
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function qrCodes()
    {
        return $this->hasMany(QrCode::class);
    }

    public function rankHistories()
    {
        return $this->hasMany(SellerRankHistory::class);
    }

    public function photos()
    {
        return $this->hasMany(SellerPhoto::class);
    }

    // === FIXED POINT METHODS ===

    /**
     * Manually update seller's total_points after transactions
     * Call this after creating new transactions
     */
    public function updateTotalPoints()
    {
        $correctTotal = $this->pointTransactions()
            ->where('type', 'earn')
            ->sum('points') ?? 0;
            
        $this->update(['total_points' => $correctTotal]);
        return $correctTotal;
    }

    // === RANK METHODS ===

    public function getCurrentRank()
    {
        return Rank::where('min_points', '<=', $this->total_points)
            ->orderBy('min_points', 'desc')
            ->first();
    }

    public function getNextRank()
    {
        return Rank::where('min_points', '>', $this->total_points)
            ->orderBy('min_points', 'asc')
            ->first();
    }

    // === DASHBOARD METRICS ===

    public function getPointsGivenAttribute()
    {
        return $this->pointTransactions()
            ->where('type', 'earn')
            ->sum('points') ?? 0;
    }

    public function getPointsFromRedemptionsAttribute()
    {
        return $this->pointTransactions()
            ->where('type', 'spend')
            ->sum('points') ?? 0;
    }

    public function getTotalCustomersAttribute()
    {
        return $this->pointTransactions()
            ->distinct('consumer_id')
            ->count('consumer_id') ?? 0;
    }

    public function getTotalTransactionsAttribute()
    {
        return $this->pointTransactions()->count() ?? 0;
    }

    // === RANK UPDATE METHOD ===

    public function updateRank()
    {
        $newRank = $this->getCurrentRank();
        if ($newRank) {
            $existingHistory = SellerRankHistory::where('seller_id', $this->id)
                ->where('rank_id', $newRank->id)
                ->exists();

            if (!$existingHistory) {
                SellerRankHistory::create([
                    'seller_id' => $this->id,
                    'rank_id' => $newRank->id,
                    'achieved_at' => now(),
                ]);
            }
        }
    }
}