<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Employee List</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #eef2f7;
            padding: 40px 20px;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            background: #fff;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
        }

        /* Header */

        .header {
            background: linear-gradient(135deg, #0d6efd, #4f8cff);
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #fff;
            font-size: 28px;
            font-weight: 600;
        }

        .btn {
            text-decoration: none;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: .3s;
            display: inline-block;
        }

        .btn-primary {
            background: #fff;
            color: #0d6efd;
        }

        .btn-primary:hover {
            background: #f1f1f1;
        }

        /* Search Section */

        .search-box {
            padding: 25px 30px;
            border-bottom: 1px solid #eee;
        }

        .search-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-form input {
            flex: 1;
            min-width: 250px;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
        }

        .search-form input:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, .15);
        }

        .search-btn {
            background: #0d6efd;
            color: white;
        }

        .search-btn:hover {
            background: #0b5ed7;
        }

        .reset-btn {
            background: #6c757d;
            color: white;
        }

        .reset-btn:hover {
            background: #5c636a;
        }

        /* Table */

        .table-wrapper {
            overflow-x: auto;
            padding: 20px 30px 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #0d6efd;
            color: white;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        td {
            padding: 14px 15px;
        }

        tbody tr {
            border-bottom: 1px solid #ececec;
            transition: .2s;
        }

        tbody tr:nth-child(even) {
            background: #fafafa;
        }

        tbody tr:hover {
            background: #f8fbff;
        }

        .empty {
            text-align: center;
            color: #777;
            padding: 25px;
        }

        /* Action Buttons */

        .action-btn {
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 6px;
            color: white;
            font-size: 14px;
            font-weight: 500;
            margin-right: 5px;
            display: inline-block;
        }

        .edit {
            background: #198754;
        }

        .edit:hover {
            background: #157347;
        }

        .delete {
            background: #dc3545;
        }

        .delete:hover {
            background: #bb2d3b;
        }

        @media(max-width:768px) {

            body {
                padding: 15px;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .search-form {
                flex-direction: column;
            }

            .search-form input,
            .search-form button,
            .search-form a {
                width: 100%;
            }

            table {
                min-width: 900px;
            }
        }

        /* .w-5.h-5{
            width:1.25rem;
            height:1.25rem;
        } */
        /* Pagination */
        /* Pagination Fix */
        .pagination-wrapper {
            padding: 20px 30px 30px;
            display: flex;
            justify-content: center;
        }

        .pagination-wrapper nav {
            display: flex;
            justify-content: center;
        }

        /* Bootstrap pagination override */
        .pagination {
            gap: 6px;
            margin: 0;
        }

        .pagination .page-item {
            list-style: none;
        }

        .pagination .page-link {
            border-radius: 8px !important;
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #ddd;
            color: #333;
            font-weight: 600;
        }

        /* hover */
        .pagination .page-link:hover {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }

        /* active page */
        .pagination .active .page-link {
            background: #0d6efd !important;
            border-color: #0d6efd !important;
            color: #fff !important;
        }

        /* remove ugly spacing issues */
        .pagination .page-item {
            margin: 0;
        }
    </style>
</head>

<body>

    <div class="container">

        <!-- Header -->
        <div class="header">
            <h1>Employee List</h1>

            <a href="/employee" class="btn btn-primary">
                + Add Employee
            </a>
        </div>

        <!-- Search Section -->
        <div class="search-box">
            <form action="/search" method="GET" class="search-form">

                <input type="text" name="search" placeholder="🔍 Search employee by name..." value="{{ @$search }}">

                <button type="submit" class="btn search-btn">
                    Search
                </button>

                <a href="/employeeList" class="btn reset-btn">
                    Reset
                </a>

            </form>
        </div>

        <!-- Table -->
@if(session('success'))
    <div style="
        margin:20px 30px;
        padding:12px 15px;
        background:#d1e7dd;
        color:#0f5132;
        border:1px solid #badbcc;
        border-radius:8px;
        font-weight:500;
    ">
        {{ session('success') }}
    </div>
@endif
        <div class="table-wrapper">
            <form action="/employee/bulkDelete" method="POST">
                <button type="submit" class="btn delete"
                    onclick="return confirm('Are you sure you want to delete selected employees?')">
                    Delete Selected
                </button>
                @csrf
                <br>
                <br>
                <table>

                    <thead>
                        <tr>
                            <th>Select</th>
                            <th width="80">#Sl no</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Created At</th>
                            <th>Updated At</th>
                            <th width="180">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($employees as $employee)

                            <tr>
                                <td>
                                    <input type="checkbox" name="ids[]" value="{{ $employee->id }}">
                                </td>
                                <td>{{ $employees->firstItem() + $loop->index }}</td>
                                <td>{{ $employee->name }}</td>
                                <td>{{ $employee->email }}</td>
                                <td>{{ $employee->mobile }}</td>
                                <td>{{ $employee->created_at }}</td>
                                <td>{{ $employee->updated_at }}</td>

                                <td>
                                    <a href="/employee/edit/{{ $employee->id }}" class="action-btn edit">
                                        Edit
                                    </a>

                                    <a href="/employee/delete/{{ $employee->id }}" class="action-btn delete"
                                        onclick="return confirm('Are you sure you want to delete this employee?')">
                                        Delete
                                    </a>
                                </td>
                            </tr>

                        @empty

                            <tr>
                                <td colspan="7" class="empty">
                                    No employees found.
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>
            </form>
        </div>

    </div>

    <div class="pagination-wrapper">
        {{ @$employees->links() }}
    </div>

</body>

</html>