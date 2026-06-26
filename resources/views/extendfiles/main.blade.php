<div class="main">

    <!-- Flight Search Section -->
    <!-- Premium Flight Search -->
    <section class="search-wrapper">
        <div class="container">
            <form action="{{ route('prebook') }}" method="POST">
                @csrf
                <div class="search-box">

                    <!-- Trip Type -->
                    <!-- <ul class="nav nav-pills justify-content-center mb-4"> -->
                       <ul class="nav nav-pills justify-content-center mb-4" style="margin-top:60px;">
                        <li class="nav-item">
                            <button type="button" class="nav-link active">Round Trip</button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link">One Way</button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link">Multi City</button>
                        </li>
                    </ul>

                    <div class="row g-3 align-items-end">

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label fw-semibold">
                                Flying From
                            </label>
                            <input type="text" class="form-control custom-input" placeholder="✈ New Delhi" name="from">
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label fw-semibold">
                                Flying To
                            </label>
                            <input type="text" class="form-control custom-input" placeholder="📍 Dubai" name="to">
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <label class="form-label fw-semibold">
                                Departure
                            </label>
                            <input type="date" class="form-control custom-input" name="departure_date">
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <label class="form-label fw-semibold">
                                Return
                            </label>
                            <input type="date" class="form-control custom-input" name="return_date">
                        </div>

                        <div class="col-lg-2 col-md-6">
                            <label class="form-label fw-semibold">
                                Travelers
                            </label>
                            <select class="form-select custom-input" name="passengers">
                                <option>1 Adult</option>
                                <option>2 Adults</option>
                                <option>3 Adults</option>
                                <option>Family</option>
                            </select>
                        </div>

                        <div class="col-12 text-center mt-4">
                            <button type="submit" class="btn search-btn">
                                🔍 Search Flights
                            </button>
                        </div>
            </form>
        </div>
</div>

</div>
</section>


<!-- Hero Section -->
<section class="hero-section">
    <div class="container text-center hero-content">
        <h1>Discover Your Next Adventure</h1>
        <p class="lead my-4">
            Explore breathtaking destinations around the world with
            affordable travel packages.
        </p>
        <a href="#" class="btn btn-warning btn-lg">
            Book Your Trip
        </a>
    </div>
</section>

<!-- Popular Destinations -->
<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-5">Popular Destinations</h2>

        <div class="row g-4">

            <div class="col-md-4">
                <div class="card destination-card h-100">
                    <img src="https://images.unsplash.com/photo-1537996194471-e657df975ab4" class="card-img-top"
                        alt="Bali">
                    <div class="card-body">
                        <h5 class="card-title">Bali</h5>
                        <p class="card-text">
                            Tropical beaches, luxury resorts and amazing sunsets.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card destination-card h-100">
                    <img src="https://images.unsplash.com/photo-1431274172761-fca41d930114" class="card-img-top"
                        alt="Paris">
                    <div class="card-body">
                        <h5 class="card-title">Paris</h5>
                        <p class="card-text">
                            Experience romance, culture and iconic landmarks.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card destination-card h-100">
                    <img src="https://images.unsplash.com/photo-1512453979798-5ea266f8880c" class="card-img-top"
                        alt="Dubai">
                    <div class="card-body">
                        <h5 class="card-title">Dubai</h5>
                        <p class="card-text">
                            Modern luxury, shopping and desert adventures.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="bg-light py-5">
    <div class="container">
        <h2 class="text-center mb-5">Why Choose Us?</h2>

        <div class="row text-center">

            <div class="col-md-3 mb-4">
                <div class="feature-box">
                    <h2>💰</h2>
                    <h5>Best Prices</h5>
                    <p>Affordable travel packages for every budget.</p>
                </div>
            </div>

            <div class="col-md-3 mb-4">
                <div class="feature-box">
                    <h2>🌎</h2>
                    <h5>Global Tours</h5>
                    <p>Explore top destinations across the world.</p>
                </div>
            </div>

            <div class="col-md-3 mb-4">
                <div class="feature-box">
                    <h2>🛡️</h2>
                    <h5>Safe Travel</h5>
                    <p>Your comfort and security are our priority.</p>
                </div>
            </div>

            <div class="col-md-3 mb-4">
                <div class="feature-box">
                    <h2>📞</h2>
                    <h5>24/7 Support</h5>
                    <p>Always available to assist your travel needs.</p>
                </div>
            </div>

        </div>
    </div>
</section>

</div>