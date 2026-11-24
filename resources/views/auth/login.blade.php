<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1D102C">
    <title>Staff Sign In &middot; PhilCST IDMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F6F7F9] text-[#111827] antialiased">
    <main class="grid min-h-screen grid-cols-1 lg:grid-cols-12">
        {{-- Architectural institutional campus panel --}}
        <section aria-label="Philippine College of Science and Technology campus"
            class="relative hidden flex-col justify-between overflow-hidden bg-[#1D102C] p-10 text-white lg:col-span-5 lg:flex">
            <div class="absolute inset-0 bg-cover bg-center opacity-25" style="background-image: url('{{ asset('images/philcst-campus.jpg') }}')" aria-hidden="true"></div>
            <div class="absolute inset-0 bg-gradient-to-b from-[#1D102C]/90 via-[#1D102C]/75 to-[#140A20]/95" aria-hidden="true"></div>

            <div class="relative z-10 flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-md bg-white p-1">
                    <img src="{{ asset('images/philcst-seal.png') }}" alt="Philippine College of Science and Technology seal" class="h-full w-full object-contain">
                </span>
                <div>
                    <p class="text-sm font-semibold tracking-tight text-white">PHILCST IDMS</p>
                    <p class="text-xs text-white/60">Registrar &amp; ID Operations</p>
                </div>
            </div>

            <div class="relative z-10 max-w-md space-y-2.5">
                <div class="h-0.5 w-8 bg-[#D4A853]" aria-hidden="true"></div>
                <h2 class="text-2xl font-semibold leading-snug tracking-tight text-white">
                    Student ID Monitoring, Production, and Releasing
                </h2>
                <p class="text-sm leading-relaxed text-white/65">
                    Active-term enrollment rosters, physical card production queues, verification, and release accountability.
                </p>
            </div>

            <div class="relative z-10 border-t border-white/12 pt-5 text-xs text-white/60">
                <p class="font-medium text-white/90">Philippine College of Science and Technology</p>
                <p class="mt-0.5">Calasiao, Pangasinan, Philippines 2418</p>
            </div>
        </section>

        {{-- Sign-in workspace --}}
        <section class="flex flex-col justify-between px-5 py-10 sm:px-10 lg:col-span-7 lg:px-16">
            <div class="my-auto mx-auto w-full max-w-[400px]">
                <div class="surface p-7 sm:p-8">
                    <header class="mb-6">
                        <div class="mb-4 flex items-center gap-2.5 lg:hidden">
                            <img src="{{ asset('images/philcst-seal.png') }}" alt="Philippine College of Science and Technology seal" class="h-8 w-8 object-contain">
                            <span class="text-xs font-semibold uppercase tracking-wider text-[#3F1B68]">PHILCST IDMS</span>
                        </div>
                        <p class="hidden text-xs font-semibold uppercase tracking-wider text-[#6B7280] lg:block">PHILCST IDMS</p>
                        <h1 id="login-title" class="mt-1 text-2xl font-semibold tracking-tight text-[#111827]">Staff Sign In</h1>
                        <p class="mt-1 text-sm text-[#6B7280]">Enter your institutional credentials to continue.</p>
                    </header>

                    @if ($errors->any())
                        <div class="alert-banner error" role="alert" aria-live="polite">
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" id="loginForm" class="space-y-4" novalidate>
                        @csrf

                        <div>
                            <label for="email" class="mb-1.5 block text-[13px] font-medium text-[#374151]">Email Address</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="name@philcst.edu.ph"
                                autocomplete="email" inputmode="email" maxlength="255" required autofocus
                                class="h-10"
                                @class(['border-red-400' => $errors->has('email')])
                                @if($errors->has('email')) aria-invalid="true" @endif>
                        </div>

                        <div>
                            <label for="password" class="mb-1.5 block text-[13px] font-medium text-[#374151]">Password</label>
                            <div class="relative">
                                <input id="password" name="password" type="password" autocomplete="current-password" required class="h-10 pr-16">
                                <button type="button" id="passwordToggle" aria-label="Show password" aria-pressed="false"
                                    class="absolute inset-y-0 right-0 px-3 text-xs font-medium text-[#6B7280] hover:text-[#111827]">
                                    Show
                                </button>
                            </div>
                        </div>

                        <label for="remember" class="flex cursor-pointer items-center gap-2 text-[13px] text-[#4B5563]">
                            <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                            <span>Remember me on this device</span>
                        </label>

                        <button type="submit" id="loginSubmit" class="btn primary h-10 w-full text-sm font-medium">
                            <span class="submit-label">Sign In</span>
                        </button>
                    </form>
                </div>

                <footer class="mt-5 text-center text-xs text-[#6B7280]">
                    <p class="font-medium text-[#4B5563]">Authorized PhilCST Personnel Only</p>
                    <p class="mt-0.5">&copy; {{ now()->year }} Philippine College of Science and Technology</p>
                </footer>
            </div>
        </section>
    </main>

    <script>
    (() => {
        const form = document.getElementById('loginForm');
        const submit = document.getElementById('loginSubmit');
        const password = document.getElementById('password');
        const toggle = document.getElementById('passwordToggle');

        toggle.addEventListener('click', () => {
            const reveal = password.type === 'password';
            password.type = reveal ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(reveal));
            toggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
            toggle.textContent = reveal ? 'Hide' : 'Show';
            password.focus({ preventScroll: true });
        });

        form.addEventListener('submit', (event) => {
            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
                return;
            }
            submit.disabled = true;
            submit.querySelector('.submit-label').textContent = 'Signing In...';
        });
    })();
    </script>
</body>
</html>
