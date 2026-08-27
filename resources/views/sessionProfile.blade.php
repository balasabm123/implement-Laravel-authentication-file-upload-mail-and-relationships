<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body{
            background: linear-gradient(135deg, #0d6efd, #6f42c1);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: Arial, Helvetica, sans-serif;
        }

        .profile-card{
            width:100%;
            max-width:500px;
            border:none;
            border-radius:15px;
            box-shadow:0 10px 30px rgba(0,0,0,.2);
        }

        .profile-header{
            background:#0d6efd;
            color:#fff;
            border-radius:15px 15px 0 0;
            padding:20px;
            text-align:center;
        }

        .info-item{
            padding:10px 0;
            border-bottom:1px solid #eee;
        }

        .info-item:last-child{
            border-bottom:none;
        }

        .btn-logout{
            width:100%;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card profile-card mx-auto">

        <div class="profile-header">
            <h2 class="mb-0">👤 User Profile</h2>
        </div>

        <div class="card-body p-4">

            @if(session('message'))
                <div class="alert alert-success">
                    {{ session('message') }}
                </div>
            @endif

            @if(session()->has('user'))

                <div class="info-item">
                    <strong>Name:</strong>
                    <span class="float-end">{{ session('user')['name'] }}</span>
                </div>

                <div class="info-item">
                    <strong>Email:</strong>
                    <span class="float-end">{{ session('user')['email'] }}</span>
                </div>

                <!-- <div class="info-item mb-4">
                    <strong>Mobile:</strong>
                    <span class="float-end">{{ session('user')['mobile'] }}</span>
                </div> -->

                <form action="logout" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-lg btn-logout">
                        Logout
                    </button>
                </form>

            @else

                <div class="alert alert-warning text-center">
                    Session expired or user not logged in.
                </div>

                <div class="d-grid">
                    <a href="{{ route('lg-in') }}" class="btn btn-primary">
                        Login Again
                    </a>
                </div>

            @endif

        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>