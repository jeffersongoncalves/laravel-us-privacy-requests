<?php

namespace JeffersonGoncalves\UsPrivacyRequests\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Implemented by models in us-privacy.models that belong to a consumer
 * (e.g. Order: where('user_id', $subject->getKey())).
 * The subject's own model is resolved without it.
 */
interface ResolvesConsumer
{
    /**
     * @return Builder<covariant Model>
     */
    public static function consumerQuery(Model $subject): Builder;
}
