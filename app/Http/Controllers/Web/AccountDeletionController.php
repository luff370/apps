<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\User\AccountDeletionService;
use Illuminate\Http\Request;

class AccountDeletionController extends Controller
{
    public function show(string $app, AccountDeletionService $service)
    {
        $appInfo = $service->resolveApp($app);
        if (!$appInfo) {
            return abort(404);
        }

        return view('account_deletion.show', $service->pageData($appInfo));
    }

    public function store(string $app, Request $request, AccountDeletionService $service)
    {
        $appInfo = $service->resolveApp($app);
        if (!$appInfo) {
            return abort(404);
        }

        $validated = $request->validate([
            'identifier' => 'required|string|max:191',
            'email' => 'nullable|email|max:100',
            'reason' => 'nullable|string|max:500',
        ], [
            'identifier.required' => 'Please enter your account, email, or user ID. / 请填写账号、邮箱或用户ID',
            'email.email' => 'Please enter a valid email address. / 请填写正确的邮箱',
        ]);

        $service->submit(
            $appInfo,
            $validated,
            (string) $request->ip(),
            (string) $request->userAgent()
        );

        return redirect()
            ->route('account-deletion.show', ['app' => $app])
            ->with('deletion_submitted', true);
    }
}
