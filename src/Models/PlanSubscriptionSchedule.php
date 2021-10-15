<?php

namespace Bpuig\Subby\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PlanSubscriptionSchedule
 * @package Bpuig\Subby\Models
 *
 * @property integer $id
 * @property integer $subscription_id
 * @property integer $plan_id;
 * @property string $service
 * @property \Carbon\Carbon|null $scheduled_at
 * @property \Carbon\Carbon|null $failed_at
 * @property \Carbon\Carbon|null $succeeded_at
 */
class PlanSubscriptionSchedule extends Model
{
    public $timestamps = false;

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'subscription_id',
        'plan_id',
        'service',
        'scheduled_at'
    ];

    /**
     * {@inheritdoc}
     */
    protected $casts = [
        'subscription_id' => 'integer',
        'plan_id' => 'integer',
        'service' => 'string',
        'scheduled_at' => 'datetime',
        'failed_at' => 'datetime',
        'succeeded_at' => 'datetime'
    ];

    /**
     * Create a new Eloquent model instance.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('subby.tables.plan_subscription_schedules'));
    }

    /**
     * Get validation rules
     * @return string[]
     */
    public function getRules(): array
    {
        return [
            'subscription_id' => 'required|integer|exists:' . config('subby.tables.plan_subscriptions') . ',id',
            'plan_id' => 'required|integer|exists:' . config('subby.tables.plans') . ',id',
            'service' => 'string',
            'scheduled_at' => 'date'
        ];
    }

    /**
     * Subscription Schedule belongs to Subscription
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription()
    {
        return $this->belongsTo(config('subby.models.plan_subscription'), 'subscription_id', 'id');
    }

    /**
     * Subscription schedule belongs to plan
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function plan()
    {
        return $this->belongsTo(config('subby.models.plan'), 'plan_id', 'id');
    }

    /**
     * Pending subscription changes
     *
     * @param $query
     * @param Carbon|null $date
     * @return mixed
     */
    public function scopePending($query, ?Carbon $date = null)
    {
        if (!$date) {
            $date = Carbon::now();
        }

        return $query->where('scheduled_at', '<=', $date)->unprocessed();
    }

    /**
     * Not processed schedules
     * @param $query
     *
     * @return mixed
     */
    public function scopeUnprocessed($query)
    {
        return $query->whereNull('succeeded_at')->whereNull('failed_at');
    }

    /**
     * Process scheduled plan change
     * @param bool $clearUsage Clear subscription usage
     * @param bool $syncInvoicing Synchronize billing frequency or leave it unchanged
     * @return PlanSubscriptionSchedule
     */
    public function executeChange(bool $clearUsage = true, bool $syncInvoicing = true)
    {
        $this->subscription->changePlan($this->plan, $clearUsage, $syncInvoicing);
        $this->succeed();

        return $this;
    }

    /**
     * Flag the schedule as failed
     * @return PlanSubscriptionSchedule
     */
    public function fail()
    {
        $this->failed_at = Carbon::now();
        $this->succeeded_at = null;
        $this->save();

        return $this;
    }

    /**
     * Flag the schedule as succeeded
     * @return PlanSubscriptionSchedule
     */
    public function succeed()
    {
        $this->failed_at = null;
        $this->succeeded_at = Carbon::now();
        $this->save();

        return $this;
    }
}