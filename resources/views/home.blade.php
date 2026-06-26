<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Home</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background: #f4f6f9;
            padding: 40px;
        }

        .container {
            max-width: 900px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,.1);
        }

        h1 {
            margin-bottom: 20px;
            color: #333;
        }

        .messages {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .success-message,
        .error-message {
            padding: 8px 15px;
            border-radius: 5px;
            color: white;
            font-weight: bold;
            display: inline-block;
        }

        .success-message {
            background: linear-gradient(135deg, #38c172, #2d995b);
        }

        .error-message {
            background: linear-gradient(135deg, #dc3545, #a71d2a);
        }

        .info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            line-height: 1.8;
        }

        .info p {
            margin: 5px 0;
        }

        .links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .links a {
            display: block;
            text-decoration: none;
            background: #0d6efd;
            color: white;
            padding: 14px;
            border-radius: 8px;
            text-align: center;
            transition: .3s;
        }

        .links a:hover {
            background: #084298;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>

<div class="container">

    <div class="messages">
        <x-message-banner msg="User login successfully" class="success-message"/>
        <x-message-banner msg="User register successfully" class="success-message"/>
        <x-message-banner msg="An error occurred" class="error-message"/>
    </div>

    <h1>Welcome to Laravel Home Page</h1>

    <div class="info">
        <p><strong>Application:</strong> {{ config('app.name') }}</p>
        <p><strong>Current URL:</strong> {{ URL::current() }}</p>
        <p><strong>Full URL:</strong> {{ URL::full() }}</p>
    </div>

    <div class="links">
        <a href="{{ route('hm') }}">🏠 Home Page</a>

        <a href="{{ route('employeeList') }}">👨‍💼 CRUD Application</a>

        <a href="{{ route('fileuplaodandLangaugae') }}">
            📁 File Upload & Language
        </a>

        <a href="{{ route('loginsession') }}">
            🔐 Login & Session
        </a>

        <a href="{{ route('layoutWithComponents') }}">
            🧩 Layout Components 1
        </a>

        <a href="{{ route('layoutWithComponents2') }}">
            🧩 Layout Components 2
        </a>

        <a href="{{ route('ExploreWorld') }}">
            🌍 Extend Files
        </a>

        <a href="{{ route('email') }}">
            📧 Send Email
        </a>

        <a href="{{ route('bookingList') }}">
            🔗 Eloquent Relationships
        </a>
    </div>

</div>

</body>
</html>