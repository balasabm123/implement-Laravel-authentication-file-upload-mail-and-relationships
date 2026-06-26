<div>
    <h1>User Profile</h1>
@if(session('message'))
    <div style="color:green;">
            {{ session('message') }}
        </div>
@endif
    @if(session()->has('user'))
        <div class="card">
            <div class="card-body">
                <p><strong>Name:</strong> {{ session('user')['name'] }}</p>
                <p><strong>Email:</strong> {{ session('user')['email'] }}</p>
                <p><strong>Mobile:</strong> {{ session('user')['mobile'] }}</p>
            </div>
        </div>

        <form action="logout" method="POST">

            @csrf
            <button type="submit">Logout</button>
        </form>
    @else
        <div class="alert alert-warning">
            Session expired or user not logged in.
        </div>

        <a href="/login">Login Again</a>
    @endif
</div>