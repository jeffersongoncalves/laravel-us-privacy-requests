<?php

namespace JeffersonGoncalves\UsPrivacyRequests\Commands;

use Illuminate\Console\Command;
use JeffersonGoncalves\UsPrivacyRequests\Events\AppealOverdue;
use JeffersonGoncalves\UsPrivacyRequests\Events\ConsumerRequestOverdue;
use JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest;

class OverdueCommand extends Command
{
    protected $signature = 'us-privacy:requests-overdue';

    protected $description = 'Dispatch an event for every consumer request and appeal past its legal deadline';

    public function handle(): int
    {
        $requests = 0;
        $appeals = 0;

        ConsumerRequest::query()->overdue()->lazyById()->each(function (ConsumerRequest $request) use (&$requests): void {
            event(new ConsumerRequestOverdue($request));
            $requests++;
        });

        ConsumerRequest::query()->scopes('appealOverdue')->lazyById()->each(function (ConsumerRequest $request) use (&$appeals): void {
            event(new AppealOverdue($request));
            $appeals++;
        });

        $requests + $appeals === 0
            ? $this->info('No overdue consumer requests or appeals.')
            : $this->warn("{$requests} overdue consumer request(s), {$appeals} overdue appeal(s).");

        return self::SUCCESS;
    }
}
