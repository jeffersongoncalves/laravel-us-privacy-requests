<?php

namespace JeffersonGoncalves\UsPrivacyRequests\Events;

use JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest;

class ConsumerRequestAppealed
{
    public function __construct(public ConsumerRequest $request) {}
}
