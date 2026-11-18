<?php

namespace JeffersonGoncalves\UsPrivacyRequests\Events;

use JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest;

class ConsumerRequestOpened
{
    public function __construct(public ConsumerRequest $request) {}
}
