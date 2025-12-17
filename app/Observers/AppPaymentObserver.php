<?php

namespace App\Observers;

use App\Models\AppPayment;

class AppPaymentObserver
{
    /**
     * Handle the AppPayment "created" event.
     */
    public function created(AppPayment $appPayment): void
    {
        $appPayment->notify_url = url("/api/payment/{$appPayment->pay_channel}/{$appPayment->id}/notify");
        $appPayment->saveQuietly();
    }

    /**
     * Handle the AppPayment "updated" event.
     */
    public function updated(AppPayment $appPayment): void
    {
        $appPayment->notify_url = url("/api/payment/{$appPayment->pay_channel}/{$appPayment->id}/notify");
        $appPayment->saveQuietly();
    }

    /**
     * Handle the AppPayment "deleted" event.
     */
    public function deleted(AppPayment $appPayment): void
    {
        //
    }

    /**
     * Handle the AppPayment "restored" event.
     */
    public function restored(AppPayment $appPayment): void
    {
        //
    }

    /**
     * Handle the AppPayment "force deleted" event.
     */
    public function forceDeleted(AppPayment $appPayment): void
    {
        //
    }
}
