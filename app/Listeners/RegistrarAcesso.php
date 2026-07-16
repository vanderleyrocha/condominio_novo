<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Access;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Auditoria de login (INV-03): todo login registra um Access — paridade com o
 * UserEventSubscriber do legado.
 */
class RegistrarAcesso
{
    public function handle(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        Access::query()->create([
            'user_id' => $user->id,
            'datetime' => now(),
        ]);
    }
}
