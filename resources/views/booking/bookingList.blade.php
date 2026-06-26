<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <h1>Booking List</h1>
    <table border="1">
        <thead>
            <tr>
                <th>ID</th>
                <th>App ref id</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Created At</th>
                <th>Booked by</th>
                <!-- <th>Booking Date</th> -->
            </tr>
        </thead>
        <tbody>     
            @foreach($booking as $book)
            <tr>
                <td>{{ $book->id }}</td>
                <td>{{ $book->app_ref_id }}</td>
                <td>{{ $book->name }}</td>
                <td>{{ $book->email }}</td>
                <td>{{ $book->phone }}</td>
                <td>{{ $book->created_at }}</td>
                <td>{{ $book->booking_user->name }}</td>
                <!-- <td>{{ $book->booking_date }}</td> -->
            </tr>
            @endforeach
        </tbody>
    </table>                
</body>
</html>