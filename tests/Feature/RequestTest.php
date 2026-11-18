<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use JeffersonGoncalves\UsPrivacyRequests\Enums\RequestStatus;
use JeffersonGoncalves\UsPrivacyRequests\Enums\RequestType;
use JeffersonGoncalves\UsPrivacyRequests\Events\AppealDecided;
use JeffersonGoncalves\UsPrivacyRequests\Events\AppealOverdue;
use JeffersonGoncalves\UsPrivacyRequests\Events\ConsumerRequestAppealed;
use JeffersonGoncalves\UsPrivacyRequests\Events\ConsumerRequestClosed;
use JeffersonGoncalves\UsPrivacyRequests\Events\ConsumerRequestOpened;
use JeffersonGoncalves\UsPrivacyRequests\Events\ConsumerRequestOverdue;
use JeffersonGoncalves\UsPrivacyRequests\Facades\UsPrivacyRequests;
use JeffersonGoncalves\UsPrivacyRequests\Tests\Fixtures\Order;
use JeffersonGoncalves\UsPrivacyRequests\Tests\Fixtures\User;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-17 10:00:00'));

    $this->user = User::create(['name' => 'Mary Smith', 'email' => 'mary@example.com', 'tax_id' => '123']);
    Order::create(['user_id' => $this->user->id, 'shipping_address' => 'Main St 1', 'total' => 100]);
    Order::create(['user_id' => $this->user->id, 'shipping_address' => 'Main St 2', 'total' => 200]);

    $other = User::create(['name' => 'John', 'email' => 'john@example.com']);
    Order::create(['user_id' => $other->id, 'shipping_address' => 'Main St 3', 'total' => 300]);
});

it('opens a request with the deadline of the consumer\'s state', function () {
    Event::fake([ConsumerRequestOpened::class]);

    $va = UsPrivacyRequests::open(RequestType::Access, requester: ['email' => 'mary@example.com'], state: 'va');
    $ia = UsPrivacyRequests::open(RequestType::Access, $this->user, state: 'IA');
    $unknown = UsPrivacyRequests::open(RequestType::Access, $this->user);

    expect($va->status)->toBe(RequestStatus::Pending)
        ->and($va->state)->toBe('VA')
        ->and($va->due_at->toDateString())->toBe('2027-01-01')
        ->and($ia->due_at->toDateString())->toBe('2027-02-15')
        ->and($unknown->due_at->toDateString())->toBe('2027-01-01')
        ->and($unknown->rules()->code)->toBe('US');

    Event::assertDispatchedTimes(ConsumerRequestOpened::class, 3);
});

it('knows whether the state grants the right', function () {
    expect(UsPrivacyRequests::open(RequestType::Correct, $this->user, state: 'VA')->isRightGranted())->toBeTrue()
        ->and(UsPrivacyRequests::open(RequestType::Correct, $this->user, state: 'IA')->isRightGranted())->toBeFalse()
        ->and(UsPrivacyRequests::open(RequestType::ThirdParties, $this->user, state: 'OR')->isRightGranted())->toBeTrue()
        ->and(UsPrivacyRequests::open(RequestType::Profiling, $this->user, state: 'TX')->isRightGranted())->toBeFalse();
});

it('exports only the subject data, keyed by table', function () {
    expect(UsPrivacyRequests::export($this->user))->toBe([
        'users' => [['name' => 'Mary Smith', 'email' => 'mary@example.com', 'tax_id' => '123']],
        'orders' => [['shipping_address' => 'Main St 1'], ['shipping_address' => 'Main St 2']],
    ]);
});

it('refuses to fulfil an unverified request', function () {
    UsPrivacyRequests::fulfil(UsPrivacyRequests::open(RequestType::Access, $this->user));
})->throws(LogicException::class, 'Verify');

it('fulfils access and portability with a JSON export on the private disk', function () {
    Storage::fake('local');
    Event::fake([ConsumerRequestClosed::class]);

    $request = UsPrivacyRequests::open(RequestType::Portability, state: 'CO');
    UsPrivacyRequests::verify($request, $this->user);
    UsPrivacyRequests::fulfil($request, 'Sent by email');

    $request->refresh();

    expect($request->status)->toBe(RequestStatus::Completed)
        ->and($request->export_path)->toBe("us-privacy/requests/request-{$request->id}.json")
        ->and(json_decode(Storage::disk('local')->get($request->export_path), true)['orders'])->toHaveCount(2);

    Event::assertDispatched(ConsumerRequestClosed::class);
});

it('fulfils deletion by anonymizing every record of the subject only', function () {
    UsPrivacyRequests::fulfil(UsPrivacyRequests::verify(UsPrivacyRequests::open(RequestType::Delete, $this->user, state: 'TX')));

    $user = $this->user->fresh();

    expect($user->name)->toBe('M*********')
        ->and($user->tax_id)->toBe('123') // Keep: legal retention
        ->and(Order::where('user_id', $user->id)->pluck('shipping_address')->all())->toBe([null, null])
        ->and(Order::whereNotNull('shipping_address')->pluck('shipping_address')->all())->toBe(['Main St 3']);
});

it('leaves manual types to complete()', function () {
    UsPrivacyRequests::fulfil(UsPrivacyRequests::verify(UsPrivacyRequests::open(RequestType::Correct, $this->user)));
})->throws(LogicException::class, 'by hand');

it('extends once by the state extension, within the initial period', function () {
    $request = UsPrivacyRequests::open(RequestType::Access, $this->user, state: 'CO');

    UsPrivacyRequests::extend($request, 'Several systems');

    expect($request->fresh()->due_at->toDateString())->toBe('2027-02-15')
        ->and(fn () => UsPrivacyRequests::extend($request, 'again'))->toThrow(LogicException::class, 'already extended');

    $late = UsPrivacyRequests::open(RequestType::Access, $this->user, state: 'CO');
    $this->travel(46)->days();

    expect(fn () => UsPrivacyRequests::extend($late, 'Late'))->toThrow(LogicException::class, 'initial period');
});

it('takes an appeal of a refusal and decides it within the state window', function () {
    Event::fake([ConsumerRequestAppealed::class, AppealDecided::class]);
    $request = UsPrivacyRequests::reject(UsPrivacyRequests::open(RequestType::Delete, $this->user, state: 'VA'), 'Could not authenticate');

    UsPrivacyRequests::appeal($request, 'I sent my ID');

    expect($request->status)->toBe(RequestStatus::Appealed)
        ->and($request->isOpen())->toBeTrue()
        ->and($request->appeal_due_at->toDateString())->toBe('2027-01-16'); // Virginia: 60 days

    UsPrivacyRequests::decideAppeal($request, granted: false, response: 'Identity still not verified. You may contact the Virginia Attorney General.');

    expect($request->status)->toBe(RequestStatus::Rejected)
        ->and($request->appeal_result)->toBe('denied')
        ->and(fn () => UsPrivacyRequests::appeal($request))->toThrow(LogicException::class, 'already appealed');

    Event::assertDispatched(ConsumerRequestAppealed::class);
    Event::assertDispatched(AppealDecided::class);
});

it('reopens the request when the appeal is granted', function () {
    $request = UsPrivacyRequests::reject(UsPrivacyRequests::open(RequestType::Delete, $this->user, state: 'CO'), 'No');
    UsPrivacyRequests::appeal($request);
    UsPrivacyRequests::decideAppeal($request, granted: true, response: 'We will delete your data.');

    expect($request->status)->toBe(RequestStatus::Pending)
        ->and($request->due_at->toDateString())->toBe('2027-01-01'); // Colorado: 45 days

    UsPrivacyRequests::fulfil(UsPrivacyRequests::verify($request));

    expect($request->fresh()->status)->toBe(RequestStatus::Completed);
});

it('extends an appeal only where the state allows it', function () {
    $co = UsPrivacyRequests::appeal(UsPrivacyRequests::reject(UsPrivacyRequests::open(RequestType::Access, $this->user, state: 'CO'), 'No'));
    UsPrivacyRequests::extendAppeal($co, 'Complex');

    expect($co->appeal_due_at->toDateString())->toBe('2027-03-02'); // 45 + 60

    $va = UsPrivacyRequests::appeal(UsPrivacyRequests::reject(UsPrivacyRequests::open(RequestType::Access, $this->user, state: 'VA'), 'No'));

    expect(fn () => UsPrivacyRequests::extendAppeal($va, 'Complex'))->toThrow(LogicException::class, 'cannot be extended');
});

it('has no appeal in Utah', function () {
    UsPrivacyRequests::appeal(UsPrivacyRequests::reject(UsPrivacyRequests::open(RequestType::Access, $this->user, state: 'UT'), 'No'));
})->throws(LogicException::class, 'no appeal process');

it('dispatches an event per overdue request and appeal', function () {
    Event::fake([ConsumerRequestOverdue::class, AppealOverdue::class]);
    UsPrivacyRequests::open(RequestType::Access, $this->user, state: 'VA');
    UsPrivacyRequests::complete(UsPrivacyRequests::open(RequestType::Access, $this->user, state: 'VA'));
    UsPrivacyRequests::appeal(UsPrivacyRequests::reject(UsPrivacyRequests::open(RequestType::Access, $this->user, state: 'CO'), 'No'));

    $this->travel(46)->days();
    Artisan::call('us-privacy:requests-overdue');

    Event::assertDispatchedTimes(ConsumerRequestOverdue::class, 1);
    Event::assertDispatchedTimes(AppealOverdue::class, 1);
});
