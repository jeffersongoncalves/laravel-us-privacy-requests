<?php

namespace JeffersonGoncalves\UsPrivacyRequests\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\UsPrivacy\Attributes\PersonalData;
use JeffersonGoncalves\UsPrivacy\Concerns\InteractsWithPersonalData;
use JeffersonGoncalves\UsPrivacy\Contracts\HasPersonalData;
use JeffersonGoncalves\UsPrivacy\Enums\AnonymizeStrategy;
use JeffersonGoncalves\UsPrivacy\StateRules;
use JeffersonGoncalves\UsPrivacy\UsPrivacy;
use JeffersonGoncalves\UsPrivacyRequests\Enums\RequestStatus;
use JeffersonGoncalves\UsPrivacyRequests\Enums\RequestType;

/**
 * @property RequestType $type
 * @property RequestStatus $status
 * @property string|null $state
 * @property string|null $export_path
 * @property Carbon $due_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $extended_at
 * @property string|null $extension_reason
 * @property Carbon|null $completed_at
 * @property Carbon|null $appealed_at
 * @property string|null $appeal_reason
 * @property Carbon|null $appeal_due_at
 * @property Carbon|null $appeal_extended_at
 * @property string|null $appeal_result granted or denied
 * @property string|null $appeal_response
 * @property Carbon|null $appeal_decided_at
 * @property Model|null $subject
 */
#[PersonalData('requester_name', purpose: 'Answer consumer requests and appeals', anonymize: AnonymizeStrategy::Mask)]
#[PersonalData('requester_email', purpose: 'Answer consumer requests and appeals', anonymize: AnonymizeStrategy::Hash)]
class ConsumerRequest extends Model implements HasPersonalData
{
    use InteractsWithPersonalData;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('us-privacy-requests.table', 'us_privacy_consumer_requests');
    }

    protected function casts(): array
    {
        return [
            'type' => RequestType::class,
            'status' => RequestStatus::class,
            'due_at' => 'datetime',
            'verified_at' => 'datetime',
            'extended_at' => 'datetime',
            'completed_at' => 'datetime',
            'appealed_at' => 'datetime',
            'appeal_due_at' => 'datetime',
            'appeal_extended_at' => 'datetime',
            'appeal_decided_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** The law of the consumer's state, or the strictest combination when it is unknown. */
    public function rules(): ?StateRules
    {
        return app(UsPrivacy::class)->rulesFor($this->state);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', RequestStatus::open());
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', RequestStatus::Pending)->where('due_at', '<', now());
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeAppealOverdue(Builder $query): void
    {
        $query->where('status', RequestStatus::Appealed)->where('appeal_due_at', '<', now());
    }

    public function isOpen(): bool
    {
        return in_array($this->status, RequestStatus::open(), true);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /** Whether the consumer's state law grants this right (you may still honor it voluntarily). */
    public function isRightGranted(): bool
    {
        return $this->rules()?->hasRight($this->type->right()) ?? false;
    }
}
