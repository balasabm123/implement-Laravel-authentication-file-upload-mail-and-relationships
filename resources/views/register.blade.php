<div>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>HTML Form Example</title>

        <style>
            body {
                font-family: Arial, sans-serif;
                background: #f4f4f4;
                padding: 30px;
            }

            .form-box {
                max-width: 500px;
                background: white;
                padding: 20px;
                border-radius: 10px;
                margin: auto;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }

            h2 {
                text-align: center;
            }

            label {
                display: block;
                margin-top: 15px;
                font-weight: bold;
            }

            input,
            select {
                width: 100%;
                padding: 8px;
                margin-top: 5px;
            }

            .inline {
                width: auto;
                margin-right: 10px;
            }

            .btn {
                margin-top: 20px;
                background: #007bff;
                color: white;
                border: none;
                padding: 10px;
                cursor: pointer;
                border-radius: 5px;
            }

            .btn:hover {
                background: #0056b3;
            }
        </style>
    </head>

    <body>

        <div class="form-box">
            <h2>Registration Form</h2>

            <form method="post" action="/addUser">
                @csrf

                <!-- Name -->
                <label for="name">Name</label>
                <input type="text" id="name" name="name" placeholder="Enter your name">

                <!-- City Dropdown -->
                <label for="city">Select City</label>
                <select id="city" name="city">
                    <option value="">-- Choose City --</option>
                    <option value="Delhi">Delhi</option>
                    <option value="Mumbai">Mumbai</option>
                    <option value="Chandigarh">Chandigarh</option>
                    <option value="Punjab">Punjab</option>
                </select>

                <!-- Radio Buttons -->
                <label>Gender</label>

                <input type="radio" name="gender" value="Male" class="inline"> Male

                <input type="radio" name="gender" value="Female" class="inline"> Female

                <input type="radio" name="gender" value="Other" class="inline"> Other

                <!-- Checkboxes -->
                <label>Skills</label>

                <input type="checkbox" name="skills[]" value="HTML" class="inline"> HTML

                <input type="checkbox" name="skills[]" value="CSS" class="inline"> CSS

                <input type="checkbox" name="skills[]" value="JavaScript" class="inline"> JavaScript

                <!-- Range Slider -->
                <label for="range">Experience Level</label>
                <input type="range" id="range" name="experience" min="0" max="10">

                <!-- Submit -->
                <button type="submit" class="btn">Submit</button>

            </form>
        </div>

    </body>

    </html>
</div>