<?php

namespace App\Http\Controllers;

use App\Contracts\SettingsRepository;
use App\Http\Requests\AttendanceCheckInRequest;
use App\Models\Member;
use App\Services\AttendanceService;
use App\Support\AppConfig;
use App\Support\Data;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Handle the public, signed attendance check-in form.
 */
class AttendanceCheckInController extends Controller
{
    public function __construct(private readonly SettingsRepository $settingsRepository) {}

    public function create(): View
    {
        $settings = $this->settingsRepository->get();

        return view('attendance.check-in', [
            'gymName' => Data::string(
                data_get($settings, 'general.gym_name'),
                AppConfig::string('app.name', 'Gymie'),
            ),
        ]);
    }

    public function store(
        AttendanceCheckInRequest $request,
        AttendanceService $attendanceService,
    ): RedirectResponse {
        $memberCode = $request->string('member_code')->trim()->toString();
        $member = Member::query()->where('code', $memberCode)->first();

        if ($member === null) {
            return $this->failed($request);
        }

        try {
            $attendanceService->checkIn(
                $member,
                null,
                Carbon::now(AppConfig::timezone()),
            );
        } catch (ValidationException $exception) {
            $message = data_get(
                $exception->errors(),
                'checked_in_at.0',
                __('app.attendance.public_check_in_failed'),
            );

            return redirect()->to($request->fullUrl())
                ->withErrors([
                    'member_code' => Data::string($message, __('app.attendance.public_check_in_failed')),
                ])
                ->withInput();
        } catch (DomainException) {
            return $this->failed($request);
        }

        return redirect()->to($request->fullUrl())
            ->with('attendance_success', __('app.attendance.public_check_in_success'));
    }

    /**
     * Return a generic failure so public responses do not expose member state.
     */
    private function failed(AttendanceCheckInRequest $request): RedirectResponse
    {
        return redirect()->to($request->fullUrl())
            ->withErrors([
                'member_code' => __('app.attendance.public_check_in_failed'),
            ])
            ->withInput($request->safe()->only(['member_code']));
    }
}
