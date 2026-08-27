<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body{
            background: #f5f7fa;
        }
        .login-card{
            max-width: 420px;
            margin: 80px auto;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,.1);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card login-card">
        <div class="card-body p-4">

            <h2 class="text-center mb-4">Login</h2>

            @if(session('message'))
                <div class="alert alert-success">
                    {{ session('message') }}
                </div>
            @endif

            <form action="{{ url('/login') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        placeholder="Enter your email"
                        required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter your password"
                        required>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary">
                        Login
                    </button>
                </div>
            </form>

            <div class="text-center">
                <a href="{{ url('/forgot-password') }}" class="text-decoration-none">
                    Forgot Password?
                </a>
            </div>

            <hr>

            <div class="d-grid">
                <a href="{{ url('/register') }}" class="btn btn-outline-success">
                    Register
                </a>
            </div>

        </div>
    </div>
</div>

</body>
</html>