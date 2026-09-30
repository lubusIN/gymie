<?php

use App\Enums\Status;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('only opens the public attendance form through its signed URL', function (): void {
    $this->get('/attendance/check-in')->assertForbidden();

    $url = URL::signedRoute('attendance.check-in.create', absolute: false);

    $this->get($url)
        ->assertOk()
        ->assertSee(__('app.attendance.public_title'))
        ->assertSee('name="member_code"', escape: false);
});

it('requires a member ID without losing the signed form URL', function (): void {
    $url = URL::signedRoute('attendance.check-in.create', absolute: false);

    $this->from($url)
        ->post($url)
        ->assertRedirect($url)
        ->assertSessionHasErrors('member_code');

    expect(Attendance::query()->exists())->toBeFalse();
});

it('records a public check-in for an active member with a current membership', function (): void {
    $this->travelTo('2026-09-29 08:30:00');
    $member = Member::factory()->create(['status' => Status::Active]);
    Subscription::factory()->for($member)->current()->create();
    $url = URL::signedRoute('attendance.check-in.create', absolute: false);

    $this->post($url, ['member_code' => $member->code])
        ->assertRedirect($url)
        ->assertSessionHas('attendance_success', __('app.attendance.public_check_in_success'));

    $attendance = Attendance::query()->whereBelongsTo($member)->sole();

    expect($attendance->checked_in_by_id)->toBeNull()
        ->and($attendance->visited_on->toDateString())->toBe('2026-09-29');
});

it('does not reveal whether a submitted member code exists', function (): void {
    $inactiveMember = Member::factory()->create(['status' => Status::Inactive]);
    $url = URL::signedRoute('attendance.check-in.create', absolute: false);
    $message = __('app.attendance.public_check_in_failed');

    $this->post($url, ['member_code' => 'UNKNOWN-MEMBER'])
        ->assertRedirect($url)
        ->assertSessionHasErrors(['member_code' => $message]);

    $this->post($url, ['member_code' => $inactiveMember->code])
        ->assertRedirect($url)
        ->assertSessionHasErrors(['member_code' => $message]);

    expect(Attendance::query()->exists())->toBeFalse();
});

it('rejects an active member whose only subscription has expired', function (): void {
    $this->travelTo('2026-09-29 08:30:00');
    $member = Member::factory()->create(['status' => Status::Active]);
    Subscription::factory()->for($member)->create([
        'start_date' => '2026-08-01',
        'end_date' => '2026-09-28',
        'status' => Status::Expired,
    ]);
    $url = URL::signedRoute('attendance.check-in.create', absolute: false);

    $this->post($url, ['member_code' => $member->code])
        ->assertRedirect($url)
        ->assertSessionHasErrors([
            'member_code' => __('app.attendance.public_check_in_failed'),
        ]);

    expect(Attendance::query()->whereBelongsTo($member)->exists())->toBeFalse();
});

it('shows a useful message when attendance was already recorded today', function (): void {
    $this->travelTo('2026-09-29 08:30:00');
    $member = Member::factory()->create(['status' => Status::Active]);
    Subscription::factory()->for($member)->current()->create();
    Attendance::factory()->for($member)->create([
        'checked_in_at' => '2026-09-29 07:00:00',
    ]);
    $url = URL::signedRoute('attendance.check-in.create', absolute: false);

    $this->post($url, ['member_code' => $member->code])
        ->assertRedirect($url)
        ->assertSessionHasErrors([
            'member_code' => __('app.validation.attendance_already_recorded_today'),
        ]);

    expect(Attendance::query()->whereBelongsTo($member)->count())->toBe(1);
});

it('rate limits repeated public check-in attempts by IP address', function (): void {
    $url = URL::signedRoute('attendance.check-in.create', absolute: false);
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50']);

    foreach (range(1, 10) as $attempt) {
        $this->post($url, ['member_code' => "UNKNOWN-{$attempt}"])
            ->assertRedirect($url);
    }

    $this->post($url, ['member_code' => 'UNKNOWN-11'])
        ->assertTooManyRequests();
});
