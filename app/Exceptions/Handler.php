<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    protected $dontReport = [
        AdminException::class,
        ApiException::class,
        AuthException::class,
        InternalException::class,
        NotificationException::class,
        RequestException::class,
        UploadException::class,
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (ValidationException $e, $request) {
            $msg = Arr::first(Arr::first($e->errors()));
            if (is_numeric($msg)) {
                $msg = trans('admin.' . $msg);
            }

            return response()->json(['status' => 400, 'msg' => $msg]);
        });
    }
}
