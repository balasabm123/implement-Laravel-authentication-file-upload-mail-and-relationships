<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enquiry Form</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .form-container {
            width: 100%;
            max-width: 550px;
            background: #fff;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .form-container h2 {
            text-align: center;
            color: #1e293b;
            margin-bottom: 10px;
        }

        .form-container p {
            text-align: center;
            color: #64748b;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #334155;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        textarea {
            resize: vertical;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-submit:hover {
            background: #1d4ed8;
        }
    </style>
</head>

<body>

    <div class="form-container">
        <h2>Send Enquiry</h2>
        <p>Fill in the details below and submit your enquiry.</p>

        <form action="{{ route('send_email') }}" method="POST">
            @csrf

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="to" placeholder="Enter your email address" required>
            </div>

            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" placeholder="Enter enquiry subject" required>
            </div>

            <div class="form-group">
                <label>Message</label>
                <textarea name="message" rows="5" placeholder="Type your enquiry here..." required></textarea>
            </div>

            <button type="submit" class="btn-submit">
                Submit Enquiry
            </button>
            </br>
            @if(session('message'))
                <div style="color:green; text-align: center;">
                    {{ session('message') }}
                </div>
            @endif
        </form>
    </div>

</body>

</html>