<?php

return [

    'table' => 'us_privacy_consumer_requests',

    /*
     * Deadlines, extensions and appeal windows come from the consumer's state law
     * (laravel-us-privacy). This one applies only to a state without a law in the config.
     */
    'fallback_deadline' => '45 days',

    /*
     * Where exports (JSON, access and portability) are written. Keep it a private disk:
     * the file holds the consumer's personal data.
     */
    'disk' => 'local',
    'directory' => 'us-privacy/requests',

];
