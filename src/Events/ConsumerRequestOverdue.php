<?php

namespace JeffersonGoncalves\UsPrivacyRequests\Events;

use JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest;

class ConsumerRequestOverdue
{
    public function __construct(public ConsumerRequest $request) {}
}
