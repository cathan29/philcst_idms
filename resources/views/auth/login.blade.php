<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F7F5FA">
    <title>Staff Sign In · PhilCST IDMS</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="auth-page auth-page-minimal">
<main class="auth-layout auth-layout-minimal" style="--auth-campus-image: url('{{ asset('images/philcst-campus.jpg') }}')">
    <section class="auth-campus-minimal" aria-label="Philippine College of Science and Technology campus">
        <p>Philippine College of Science and Technology<br><span>Calasiao, Pangasinan</span></p>
    </section>

    <section class="auth-surface">
        <div class="auth-content">
            <header class="auth-header-minimal">
                <img class="auth-logo-minimal" src="{{ asset('images/philcst-seal.png') }}" alt="Philippine College of Science and Technology seal">
                <p class="auth-product-minimal">PHILCST IDMS</p>
                <h1 id="login-title">Staff Sign In</h1>
                <p>Sign in with your authorized staff account.</p>
            </header>

            @if ($errors->any())
                <div class="auth-alert" role="alert" aria-live="polite">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5m0 3.5v.01M10.3 3.7 2.6 17a2 2 0 0 0 1.73 3h15.34a2 2 0 0 0 1.73-3L13.7 3.7a2 2 0 0 0-3.4 0Z"/></svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="auth-form auth-form-minimal" id="loginForm" novalidate>
                @csrf

                <div class="auth-field">
                    <label for="email">Email Address</label>
                    <div class="auth-input-wrap">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 6 9 6 9-6M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z"/></svg>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="name@philcst.edu.ph" autocomplete="email" inputmode="email" maxlength="255" required autofocus @class(['has-error' => $errors->has('email')]) @if($errors->has('email')) aria-invalid="true" @endif>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password">Password</label>
                    <div class="auth-input-wrap auth-password-wrap">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10V8a5 5 0 0 1 10 0v2m-9 10h8a3 3 0 0 0 3-3v-4a3 3 0 0 0-3-3H8a3 3 0 0 0-3 3v4a3 3 0 0 0 3 3Z"/></svg>
                        <input id="password" name="password" type="password" autocomplete="current-password" required>
                        <button class="password-toggle" type="button" id="passwordToggle" aria-label="Show password" aria-pressed="false">
                            <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m4 4 16 16M10.6 6.1A10.2 10.2 0 0 1 12 6c6 0 9.5 6 9.5 6a16 16 0 0 1-2.1 2.8M7.2 7.2C4.2 9 2.5 12 2.5 12s3.5 6 9.5 6a9.7 9.7 0 0 0 4.8-1.2M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                        </button>
                    </div>
                </div>

                <label class="auth-remember" for="remember">
                    <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    <span>Remember me on this device</span>
                </label>

                <button class="auth-submit" type="submit" id="loginSubmit">
                    <span class="auth-spinner" aria-hidden="true"></span>
                    <span class="submit-label">Sign In</span>
                </button>
            </form>
        </div>

        <footer class="auth-footer-minimal">
            <strong>Authorized PhilCST Personnel Only</strong>
            <span>© {{ now()->year }} Philippine College of Science and Technology</span>
        </footer>
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
        password.focus({ preventScroll: true });
    });

    form.addEventListener('submit', (event) => {
        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
            return;
        }
        submit.disabled = true;
        submit.classList.add('is-loading');
        submit.querySelector('.submit-label').textContent = 'Signing In…';
    });
})();
</script>
</body>
</html>