<!DOCTYPE html>
<html>

<head>
    <title>Add Employee</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .container {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            width: 400px;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
            color: #555;
        }

        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }

        input:focus {
            outline: none;
            border-color: #007bff;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #0056b3;
        }
    </style>
</head>

<body>

    <div class="container">

        @if($page == "updateEmployee")
            <h1>Edit Employee</h1>
        @else
            <h1>Add Employee</h1>
        @endif

        <form action="{{ $page == 'updateEmployee' ? url('employee/updateEmployee/' . $employee->id) : url('addEmployee') }}" method="POST">
            @csrf

            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required value="{{ isset($employee) ? $employee->name : '' }}">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required
                    value="{{ isset($employee) ? $employee->email : '' }}">
            </div>

            <div class="form-group">
                <label for="mobile">Mobile</label>
                <input type="text" id="mobile" name="mobile" required
                    value="{{ isset($employee) ? $employee->mobile : '' }}">
            </div>
            @if($page == "updateEmployee")
                <input type="hidden" name="id" value="{{ $employee->id }}">
            @endif
            @if($page == "updateEmployee")
                <button type="submit">Update Employee</button>
            @else
                <button type="submit">Add Employee</button>
            @endif

        </form>
    </div>

</body>

</html>