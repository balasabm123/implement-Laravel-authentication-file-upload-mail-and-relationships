<div>
    <h1>Students Data</h1>
    <table border="1">
        <tr>
            <th>Name</th>
            <th>Email</th>              
            <th>Age</th>
            <th>Batch</th>
        </tr>
        @foreach($data as $student)
        <?php //print_r($data); die; ?>
        <tr>
            <td>{{ $student->name }}</td>
            <td>{{ $student->email }}</td>              
            <td>{{ $student->age }}</td>
            <td>{{ $student->batch }}</td>      
        </tr>
        @endforeach
    </table> 
</div>
