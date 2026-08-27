<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('upload.fileupload') }}</title>

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
        }

        .upload-card{
            max-width:550px;
            width:100%;
            border:none;
            border-radius:18px;
            box-shadow:0 12px 30px rgba(0,0,0,.25);
            overflow:hidden;
        }

        .card-header{
            background:#0d6efd;
            color:#fff;
            text-align:center;
            padding:20px;
        }

        .language-btns a{
            margin:5px;
        }

        .form-control{
            padding:10px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card upload-card mx-auto">

        <div class="card-header">
            <h2 class="mb-0">{{ __('upload.fileupload') }}</h2>
        </div>

        <div class="card-body p-4">

            <h5 class="text-center mb-3">
                {{ __('upload.changelangauge') }}
            </h5>

            <div class="d-flex justify-content-center flex-wrap language-btns mb-4">
                <a href="language/en" class="btn btn-primary">🇺🇸 English</a>
                <a href="language/hi" class="btn btn-success">🇮🇳 Hindi</a>
                <a href="language/kan" class="btn btn-warning text-dark">Kannada</a>
            </div>

            <form action="/upload" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label class="form-label fw-bold">
                        {{ __('upload.fileupload') }}
                    </label>

                    <input
                        type="file"
                        name="file"
                        class="form-control"
                        required
                    >
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        {{ __('upload.upload') }}
                    </button>
                </div>
            </form>

        </div>

    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>