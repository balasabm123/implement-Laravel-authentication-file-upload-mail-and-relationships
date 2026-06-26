<x-layout>
    <x-slot name="login">
        <div class="login-container">
            <h1>User Login</h1>
            

            <form action="" method="post">
                @csrf

                <label>Email</label>
                <input type="email" name="email" placeholder="Enter your email" required>

                <label>Mobile</label>
                <input type="text" name="mobile" placeholder="Enter your mobile" required>

                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>

                <button type="submit">Login</button>
            </form>
        </div>
    </x-slot>
</x-layout>