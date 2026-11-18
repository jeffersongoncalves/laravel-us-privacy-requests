<?php

namespace JeffersonGoncalves\UsPrivacyRequests\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \JeffersonGoncalves\UsPrivacyRequests\UsPrivacyRequests
 */
class UsPrivacyRequests extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-us-privacy-requests';
    }
}
