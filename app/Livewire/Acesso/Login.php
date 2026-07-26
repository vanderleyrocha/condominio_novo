<?php

declare(strict_types=1);

namespace App\Livewire\Acesso;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Login por nome (BR-HUMANA-001 / ADR-001 do legado), reforçado com rate limiting.
 */
#[Layout('layouts.guest')]
#[Title('Entrar')]
class Login extends Component
{
    #[Validate('required|string')]
    public string $name = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function entrar(): void
    {
        $this->validate();

        $chave = Str::transliterate(Str::lower($this->name).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($chave, 5)) {
            throw ValidationException::withMessages([
                'name' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($chave)]),
            ]);
        }

        if (! Auth::attempt(['name' => $this->name, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($chave);

            throw ValidationException::withMessages(['name' => __('auth.failed')]);
        }

        RateLimiter::clear($chave);
        session()->regenerate();

        $this->redirectIntended(route('painel'), navigate: true);
    }

    public function render()
    {
        return view('livewire.acesso.login');
    }
}
