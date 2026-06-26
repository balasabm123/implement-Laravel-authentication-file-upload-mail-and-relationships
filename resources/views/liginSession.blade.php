<div>
    <h1>User details</h1>
    <form action="login" method="post">
        @csrf
        <label>Name</label> 
        <input type="text" name="name">
        <br><br>
        <label>Email</label>
        <input type="email" name="email">
        <br><br>
        <label>Mobile</label>
        <input type="text" name="mobile">
        <br><br>
        <label>Password</label>
        <input type="password" name="password">
        <br><br>
        <button type="submit">submit</button>
    </form>
</div>