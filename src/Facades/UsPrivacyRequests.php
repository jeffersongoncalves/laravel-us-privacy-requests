<?php

namespace JeffersonGoncalves\UsPrivacyRequests\Facades;

use Illuminate\Support\Facades\Facade;
use JeffersonGoncalves\UsPrivacyRequests\RequestManager;

/**
 * @method static \JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest open(\JeffersonGoncalves\UsPrivacyRequests\Enums\RequestType $type, ?\Illuminate\Database\Eloquent\Model $subject = null, array $requester = [], ?string $state = null)
 * @method static \JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest extend(\JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest $request, string $reason)
 * @method static \JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest appeal(\JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest $request, ?string $reason = null)
 * @method static \JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest extendAppeal(\JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest $request, string $reason)
 * @method static \JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest decideAppeal(\JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest $request, bool $granted, string $response)
 * @method static \JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest verify(\JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest $request, ?\Illuminate\Database\Eloquent\Model $subject = null)
 * @method static \JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest fulfil(\JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest $request, ?string $response = null)
 * @method static \JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest complete(\JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest $request, ?string $response = null)
 * @method static \JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest reject(\JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest $request, string $reason)
 * @method static array<string, list<array<string, mixed>>> export(\Illuminate\Database\Eloquent\Model $subject)
 * @method static string exportToFile(\Illuminate\Database\Eloquent\Model $subject, string $filename)
 * @method static int erase(\Illuminate\Database\Eloquent\Model $subject)
 *
 * @see RequestManager
 */
class UsPrivacyRequests extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RequestManager::class;
    }
}
