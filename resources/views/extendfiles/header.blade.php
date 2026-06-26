<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ExploreWorld Travel</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.5),
                    rgba(0, 0, 0, 0.5)),
                url('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1600&q=80');
            background-size: cover;
            background-position: center;
            height: 90vh;
            color: white;
            display: flex;
            align-items: center;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            font-weight: bold;
        }

        .destination-card {
            transition: 0.3s;
        }

        .destination-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .feature-box {
            padding: 30px;
            border-radius: 10px;
            background: #f8f9fa;
            transition: 0.3s;
        }

        .feature-box:hover {
            background: #0d6efd;
            color: white;
        }

        footer {
            background: #212529;
            color: white;
            padding: 20px 0;
        }

        /* Flight Search */
        .flight-search-section {
            position: relative;
            z-index: 100;
            margin-top: 30px;
        }

        .search-card {
            border-radius: 15px;
            background: #fff;
        }

        .search-card h3 {
            color: #0d6efd;
            font-weight: 700;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.5),
                    rgba(0, 0, 0, 0.5)),
                url('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1600&q=80');
            background-size: cover;
            background-position: center;
            min-height: 80vh;
            display: flex;
            align-items: center;
            color: #fff;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            font-weight: 700;
        }

        /* Destination Cards */
        .destination-card {
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .destination-card img {
            height: 250px;
            object-fit: cover;
        }

        .destination-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        /* Features */
        .feature-box {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            transition: 0.3s;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }

        .feature-box:hover {
            background: #0d6efd;
            color: #fff;
            transform: translateY(-5px);
        }

        /* Responsive */
        @media (max-width: 768px) {

            .hero-content h1 {
                font-size: 2rem;
            }

            .search-card {
                margin: 0 10px;
            }
        }

        /* Search Section */
        .search-wrapper {
            position: relative;
            margin-top: -80px;
            z-index: 999;
        }

        /* Glass Search Box */
        .search-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 25px;
            padding: 35px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Tabs */
        .nav-pills .nav-link {
            border-radius: 30px;
            padding: 10px 25px;
            color: #333;
            font-weight: 600;
            margin: 0 5px;
        }

        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #0d6efd, #00c6ff);
            color: white;
        }

        /* Inputs */
        .custom-input {
            height: 55px;
            border-radius: 12px;
            border: 1px solid #ddd;
            padding-left: 15px;
            transition: .3s;
        }

        .custom-input:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 15px rgba(13, 110, 253, .2);
        }

        /* Search Button */
        .search-btn {
            background: linear-gradient(135deg,
                    #0d6efd,
                    #00c6ff);
            color: white;
            border: none;
            padding: 14px 50px;
            border-radius: 50px;
            font-size: 18px;
            font-weight: 600;
            transition: .3s;
        }

        .search-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(13, 110, 253, .3);
            color: white;
        }

        /* Responsive */
        @media(max-width:768px) {

            .search-wrapper {
                margin-top: 20px;
            }

            .search-box {
                padding: 20px;
                border-radius: 15px;
            }

        }
    </style>
</head>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="{{ url('ExploreWorld') }}">✈ ExploreWorld</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="#">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Destinations</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Packages</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Gallery</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Contact</a></li>
            </ul>
        </div>
    </div>
</nav>