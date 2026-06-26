<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
     <link rel="stylesheet" href="{{ asset('css/style.css') }}">
     <script src="{{ asset('js/custom.js') }}"></script>

    <title>{{ $title ?? 'Home page' }}</title>

    <style>
        .login-container {
            width: 400px;
            margin: 20px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: left;
        }

        .login-container h1 {
            text-align: center;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .login-container label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .login-container input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .login-container button {
            width: 100%;
            padding: 12px;
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
        }

        .login-container button:hover {
            background: #34495e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f4f6f9;
            color: #333;
        }

        /* Header */
        .header {
            background: #2c3e50;
            padding: 15px 0;
        }

        .header ul {
            display: flex;
            justify-content: center;
            list-style: none;
        }

        .header ul li {
            margin: 0 20px;
        }

        .header ul li a {
            color: white;
            text-decoration: none;
            font-size: 18px;
            transition: 0.3s;
        }

        .header ul li a:hover {
            color: #f1c40f;
        }

        /* Content */
        .content {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .content h1 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .content h3 {
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .content p {
            line-height: 1.8;
        }

        /* Footer */
        .footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 15px;
            margin-top: 50px;
        }

        .login-container {
            width: 400px;
            margin: 50px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>

    <div class="header">
        <ul>
            <li><a href="{{ route('layoutWithComponents2') }}">Home</a></li>
            <li><a href="{{ route('layoutWithComponents') }}">About</a></li>
            <li><a href="{{ route('loginn') }}">Login</a></li>
            <li><a href="{{ route('contactus') }}">Contact</a></li>
        </ul>
    </div>
    <div class="content">
        {{ $main ?? '' }}
        {{ $login ?? '' }}
         {{ $contactus ?? '' }}
    </div>
   


    <div class="footer">
        <p>© 2025 My Website | All Rights Reserved</p>
    </div>

</body>

</html>