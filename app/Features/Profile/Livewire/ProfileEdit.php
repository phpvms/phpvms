<?php

declare(strict_types=1);

namespace App\Features\Profile\Livewire;

use App\Features\Profile\Actions\SearchAirports;
use App\Features\Profile\Livewire\Forms\ProfileEditForm;
use App\Http\Data\ProfileEditData;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Laracasts\Flash\Flash;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The pilot's own "Edit profile" card.
 *
 * Saves through `UpdateProfile`, the action behind `PUT /profile`. The home
 * airport is picked from a search (two characters or more, debounced in the
 * view) over the same query as `GET /api/airports/search`.
 */
class ProfileEdit extends Component
{
    use WithFileUploads;

    public ProfileEditForm $form;

    public string $airportSearch = '';

    public string $airportLabel = '';

    public function mount(UserService $users): void
    {
        $user = $this->pilot();
        $edit = ProfileEditData::fromModel($user, $users->getUserFields($user), []);

        $this->form->fillFrom($edit);
        $this->airportLabel = (string) $edit->homeAirport?->label;
    }

    /**
     * Option lists and field definitions for the form.
     */
    #[Computed]
    public function edit(): ProfileEditData
    {
        $user = $this->pilot();

        return ProfileEditData::fromModel($user, app(UserService::class)->getUserFields($user), []);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    #[Computed]
    public function airportResults(): array
    {
        return app(SearchAirports::class)->handle($this->airportSearch, $this->edit()->hubsOnly);
    }

    public function chooseAirport(string $airportId): void
    {
        $match = collect($this->airportResults())->firstWhere('value', $airportId);

        if ($match === null) {
            return;
        }

        $this->form->home_airport_id = $match['value'];
        $this->airportLabel = $match['label'];
        $this->airportSearch = '';
    }

    public function clearAirport(): void
    {
        $this->form->home_airport_id = null;
        $this->airportLabel = '';
        $this->airportSearch = '';
    }

    public function save(): void
    {
        $this->form->save($this->pilot());

        Flash::success('Profile updated successfully!');

        $this->redirectRoute('frontend.profile.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.profile.profile-edit');
    }

    private function pilot(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
