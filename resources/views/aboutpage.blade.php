<!-- <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <div class="header">
        <ul>
            <li>
                <a href="">Home</a>
            </li>
                 <li>
                <a href="">About</a>
            </li>
                 <li>
                <a href="">Login</a>
            </li>
                 <li>
                <a href="">Contact</a>
            </li>
        </ul>

    </div>
    <div>
        <h1>About page heading</h1>
        <h3>Sub heading for about page</h3>
        <p>Dummy paragraph text for about page</p>
    </div>
    <div>
    <div class"footer">
            <p>Footer</p>
    </div>

    </div>
</body>
</html> -->

<x-layout>
    <x-slot name="title">About</x-slot>
    <x-slot name="main">
        <h1>Who We Are</h1>

        <h3>Your Trusted Partner for Flight Bookings</h3>
<p id="loginForm">Test common js from ....!!!!!!!!</p>
        <p>
            Our mission is to make air travel easier and more affordable for everyone.
            We connect travelers with the best flight options, competitive prices, and
            a seamless booking experience. From domestic flights to international
            adventures, we help you plan and book your journey with confidence.
        </p>
         
        <button type="button" class="btn btn-warning" onclick="testLogin()">Test JS</button>
    </x-slot>
</x-layout>