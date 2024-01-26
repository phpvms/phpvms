<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Http\Requests\CreateInviteRequest;
use App\Models\Invite;
use App\Notifications\Messages\InviteLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Laracasts\Flash\Flash;

class InviteController extends Controller
{
    public function index(): RedirectResponse|View
    {
        if (!setting('general.invite_only_registrations', false)) {
            Flash::error('Registration is not on invite only');
            return redirect(route('admin.users.index'));
        }

        $invites = Invite::all();

        return view('admin.invites.index', [
            'invites' => $invites,
        ]);
    }

    public function create(): RedirectResponse|View
    {
        if (!setting('general.invite_only_registrations', false)) {
            Flash::error('Registration is not on invite only');
            return redirect(route('admin.users.index'));
        }

        return view('admin.invites.create');
    }

    public function store(CreateInviteRequest $request): RedirectResponse
    {
        if (!setting('general.invite_only_registrations', false)) {
            Flash::error('Registration is not on invite only');
            return redirect(route('admin.users.index'));
        }

        $invite = Invite::create([
            'email'       => $request->get('email'),
            'token'       => sha1(hrtime(true).str_random()),
            'usage_count' => 0,
            'usage_limit' => !is_null($request->get('email')) ? 1 : $request->get('usage_limit'),
            'expires_at'  => $request->get('expires_at'),
        ]);

        if (!is_null($request->get('email')) && get_truth_state($request->get('email_link'))) {
            Notification::route('mail', $request->get('email'))
                ->notify(new InviteLink($invite));
        }

        Flash::success('Invite created successfully. The link is: '.$invite->link);

        return redirect(route('admin.invites.index'));
    }

    public function destroy(int $id): RedirectResponse
    {
        if (!setting('general.invite_only_registrations', false)) {
            Flash::error('Registration is not on invite only');
            return redirect(route('admin.users.index'));
        }

        $invite = Invite::find($id);

        if (!$invite) {
            Flash::error('Invite not found');
            return redirect(route('admin.invites.index'));
        }

        $invite->delete();

        Flash::success('Invite deleted successfully');
        return redirect(route('admin.invites.index'));
    }
}
