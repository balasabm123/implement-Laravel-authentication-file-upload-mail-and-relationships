<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Display File</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body{
            min-height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;
            background:linear-gradient(135deg,#0d6efd,#6f42c1);
            font-family:Arial, Helvetica, sans-serif;
            padding:30px 0;
        }

        .file-card{
            width:100%;
            max-width:900px;
            border:none;
            border-radius:18px;
            overflow:hidden;
            box-shadow:0 12px 30px rgba(0,0,0,.25);
        }

        .card-header{
            background:#0d6efd;
            color:#fff;
            text-align:center;
            padding:20px;
        }

        .preview-image{
            max-width:100%;
            max-height:600px;
            display:block;
            margin:auto;
            border-radius:10px;
            box-shadow:0 5px 15px rgba(0,0,0,.2);
        }

        iframe{
            width:100%;
            height:600px;
            border:none;
            border-radius:10px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card file-card mx-auto">

        <div class="card-header">
            <h2 class="mb-0">📁 Display File</h2>
        </div>

        <div class="card-body text-center p-4">

            @if($filename)

                @php
                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    $fileUrl = asset('storage/public/' . $filename);
                @endphp

                {{-- Images --}}
                @if(in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                    <img src="{{ $fileUrl }}"
                         alt="Uploaded Image"
                         class="preview-image">

                    <div class="mt-4">
                        <a href="{{ $fileUrl }}" target="_blank" class="btn btn-primary">
                            View Full Image
                        </a>
                    </div>

                {{-- PDF --}}
                @elseif($extension === 'pdf')

                    <iframe src="{{ $fileUrl }}"></iframe>

                    <div class="mt-4">
                        <a href="{{ $fileUrl }}" target="_blank" class="btn btn-danger">
                            Open PDF
                        </a>
                    </div>

                {{-- Other Files --}}
                @else

                    <div class="alert alert-info">
                        This file type cannot be previewed.
                    </div>

                    <a href="{{ $fileUrl }}" target="_blank" class="btn btn-success">
                        Download File
                    </a>

                @endif

            @else

                <div class="alert alert-warning">
                    No file available to display.
                </div>

            @endif

        </div>

    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>