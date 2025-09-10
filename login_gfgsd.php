<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login • Speed Skating</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --sky: #52b6ff;
        }

        body {
            background: linear-gradient(180deg, var(--sky) 0%, #fff 50%);
            min-height: 100svh;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
        }

        .card-mobile {
            max-width: 420px;
            margin: clamp(12px, 5vw, 32px) auto;
            border: none;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
        }

        .brand {
            font-weight: 700;
            color: #fff;
            text-align: center;
            padding: 22px 16px 8px;
        }

        .btn-sky {
            background: var(--sky);
            color: #fff;
            border: none;
            border-radius: 12px;
        }

        .btn-sky:active {
            transform: scale(.98);
        }

        .form-control {
            border-radius: 12px;
        }

        .muted {
            color: #6b7280;
        }
    </style>
</head>

<body>
    <div class="brand h3">Speed Skating Stopwatch</div>

    <div class="card card-mobile">
        <div class="card-body p-4">
            <h5 class="mb-3 text-center">Login</h5>

            <form method="post" class="needs-validation" action="script/login_action.php" novalidate>
                <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
                <div class="mb-3">
                    <label class="form-label">Mobile Number</label>
                    <input type="text" class="form-control" name="mobile"
                        pattern="\d{10}" maxlength="10" required autofocus>
                    <div class="invalid-feedback">Please enter a 10-digit mobile number.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" required>
                    <div class="invalid-feedback">Enter password.</div>
                </div>
                <div class="d-grid">
                    <button class="btn btn-sky btn-lg" type="submit">Sign in</button>
                </div>
            </form>

            <div class="small text-center mt-3 muted">
                Tip: Enter 10 digit mobile number.
            </div>
        </div>
    </div>
    <script>
        (() => {
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', e => {
                    if (!form.checkValidity()) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>

</body>

</html>