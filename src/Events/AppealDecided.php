<?php

namespace JeffersonGoncalves\UsPrivacyRequests\Events;

use JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest;

class AppealDecided
{
    public function __construct(public ConsumerRequest $request) {}
}
