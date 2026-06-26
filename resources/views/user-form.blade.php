<div class="form-container">

    <form action="/addUser" method="post">
        @csrf

        <h2>User Registration</h2>
        <!-- 
        @if($errors->any())
            <div style="color: red;">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif --> 
        <input type="text" name="name" placeholder="Enter your name"
        value="{{ old('name') }}" class="{{ $errors->has('name') ? 'error' : ''    }}">
        <span style="color: red;">@error('name') {{ $message }} @enderror</span>

        <input type="email" name="email" placeholder="Enter your email" 
        value="{{ old('email') }}" class="{{ $errors->has('email') ? 'error' : ''}}">
        <span style="color: red;">@error('email') {{ $message }} @enderror</span>

        <input type="number" name="age" placeholder="Enter your age" 
         value="{{ old('age') }}" class="{{ $errors->has('age') ? 'error' : ''}}">
        <span style="color: red;">@error('age') {{ $message }} @enderror</span>

        <input type="password" name="password" placeholder="Enter your password"
         value="{{ old('password') }}" class="{{ $errors->has('password') ? 'error' : ''}}">
        <span style="color: red;">@error('password') {{ $message }} @enderror</span>

        <button type="submit">Submit</button>
    </form>

</div>

<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f4;
    }

    .form-container {
        width: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        margin-top: 50px;
    }

    form {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        width: 350px;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    h2 {
        text-align: center;
        color: #333;
    }

    input {
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 16px;
        outline: none;
        transition: 0.3s;
    }

    input:focus {
        border-color: #4CAF50;
        box-shadow: 0 0 5px rgba(76, 175, 80, 0.5);
    }

    button {
        padding: 12px;
        border: none;
        background-color: #4CAF50;
        color: white;
        font-size: 16px;
        border-radius: 5px;
        cursor: pointer;
        transition: 0.3s;
    }

    button:hover {
        background-color: #45a049;
    }

    .error {
        border: 2px solid red;
        background-color: #ffe6e6;
        color: #d8000c;
        outline: none;
    }

    
</style>