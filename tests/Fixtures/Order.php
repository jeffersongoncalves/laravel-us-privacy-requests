<?php

namespace JeffersonGoncalves\UsPrivacyRequests\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\UsPrivacy\Attributes\PersonalData;
use JeffersonGoncalves\UsPrivacy\Concerns\InteractsWithPersonalData;
use JeffersonGoncalves\UsPrivacy\Contracts\HasPersonalData;
use JeffersonGoncalves\UsPrivacyRequests\Contracts\ResolvesConsumer;

#[PersonalData('shipping_address')]
class Order extends Model implements HasPersonalData, ResolvesConsumer
{
    use InteractsWithPersonalData;

    protected $guarded = [];

    public $timestamps = false;

    public static function consumerQuery(Model $subject): Builder
    {
        return static::query()->where('user_id', $subject->getKey());
    }
}
