@include('about');
<div>
    <h1>Admin Login</h1>
    <!-- The whole future lies in uncertainty: live immediately. - Seneca -->
     @for($i=1; $i<=10; $i++)
        <p>{{ $i }}</p>
     @endfor

     @if($namee == "John Doe")
        <p>Welcome, {{ $namee }}!</p>
     @elseif($namee == "John Dor")
        <p>Welcome, {{ $namee }}!</p>
     @else
        <p>Welcome, Guest!</p>
     @endif

     @foreach ($users as $user)
        <p>{{ $user }}</p> 
     @endforeach
</div>
