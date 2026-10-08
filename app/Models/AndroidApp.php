<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AndroidApp extends Model
{
    protected $fillable = ['developer_id', 'name', 'package_name', 'opt_in_url'];

    public function developer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'developer_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function latestCampaign(): ?Campaign
    {
        return $this->campaigns()->latest('id')->first();
    }

    /** رابط الاشتراك في الاختبار المغلق، وإن لم يضعه المطوّر نستخدم الصيغة القياسية */
    public function optInUrl(): string
    {
        return $this->opt_in_url ?: 'https://play.google.com/apps/testing/'.$this->package_name;
    }

    public function playStoreUrl(): string
    {
        return 'https://play.google.com/store/apps/details?id='.$this->package_name;
    }
}
