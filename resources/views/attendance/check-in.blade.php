<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ __('app.attendance.public_title') }} · {{ $gymName }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="min-h-screen bg-gray-50 font-sans text-gray-950 antialiased dark:bg-gray-950 dark:text-white">
        <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-5 py-10">
            <section class="w-full rounded-3xl bg-white p-6 shadow-xl shadow-gray-950/5 ring-1 ring-gray-950/5 sm:p-8 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-col gap-7">
                    <header class="flex flex-col items-center gap-4 text-center">
                        <div class="flex size-14 items-center justify-center rounded-2xl bg-gymie-600 text-white shadow-lg shadow-gymie-600/20">
                            <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <p class="text-sm font-medium text-gymie-700 dark:text-gymie-200">{{ $gymName }}</p>
                            <h1 class="text-2xl font-semibold tracking-tight">{{ __('app.attendance.public_title') }}</h1>
                            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                                {{ __('app.attendance.public_description') }}
                            </p>
                        </div>
                    </header>

                    @if (session('attendance_success'))
                        <div role="status" class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-600/15 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-400/20">
                            {{ session('attendance_success') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ request()->fullUrl() }}" class="flex flex-col gap-5">
                        @csrf

                        <div class="flex flex-col gap-2">
                            <label for="member_code" class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                {{ __('app.attendance.member_id') }}
                            </label>

                            <input
                                id="member_code"
                                name="member_code"
                                type="text"
                                value="{{ old('member_code') }}"
                                maxlength="50"
                                autocomplete="off"
                                autocapitalize="characters"
                                spellcheck="false"
                                autofocus
                                required
                                placeholder="{{ __('app.attendance.member_id_placeholder') }}"
                                class="h-12 w-full rounded-xl border border-gray-300 bg-white px-4 text-base shadow-sm outline-none transition placeholder:text-gray-400 focus:border-gymie-600 focus:ring-4 focus:ring-gymie-600/10 dark:border-gray-700 dark:bg-gray-950 dark:placeholder:text-gray-500"
                            >

                            <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                                {{ __('app.attendance.member_id_help') }}
                            </p>

                            @error('member_code')
                                <p role="alert" class="text-sm font-medium text-red-600 dark:text-red-400">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <button type="submit" class="inline-flex h-12 items-center justify-center rounded-xl bg-gymie-600 px-5 text-sm font-semibold text-white shadow-lg shadow-gymie-600/20 transition hover:bg-gymie-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gymie-600 active:bg-gymie-800">
                            {{ __('app.actions.check_in') }}
                        </button>
                    </form>

                    <p class="text-center text-xs leading-5 text-gray-500 dark:text-gray-400">
                        {{ __('app.attendance.public_privacy_note') }}
                    </p>
                </div>
            </section>
        </main>
    </body>
</html>
