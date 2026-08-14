{{--
    Dry-run results for an award's criteria: the pilots the tree matches who do
    not already hold it. A compilation failure is shown here rather than as a
    notification, because it is the answer to the question the button asked.
--}}
<div class="fi-section-content">
    @if ($error !== null)
        <p class="fi-color-danger text-sm">{{ $error }}</p>
    @elseif ($users->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('filament.award_run_none') }}
        </p>
    @else
        <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">
            {{ trans_choice('filament.award_run_test_count', $users->count(), ['count' => $users->count()]) }}
        </p>

        <div class="fi-ta-ctn overflow-hidden rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
            <table class="fi-ta-table w-full text-start text-sm">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-3 py-2 text-start font-medium">{{ trans_choice('common.pilot', 1) }}</th>
                        <th class="px-3 py-2 text-start font-medium">{{ __('common.name') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($users as $user)
                        <tr>
                            <td class="whitespace-nowrap px-3 py-2 font-mono">{{ $user->ident }}</td>
                            <td class="px-3 py-2">{{ $user->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
