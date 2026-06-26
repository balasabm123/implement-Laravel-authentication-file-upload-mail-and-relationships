@include('extendfiles.header')

<div class="container py-5">

    <h2 class="mb-4">Flight Search Results</h2>

    <!-- Show submitted data -->
    <div class="card mb-4">
        <div class="card-body">
            <p><strong>From:</strong> {{ $from }}</p>
            <p><strong>To:</strong> {{ $to }}</p>
            <p><strong>Departure:</strong> {{ $departure_date }}</p>
            <p><strong>Return:</strong> {{ $return_date }}</p>
            <p><strong>Passengers:</strong> {{ $passengers }}</p>
        </div>
    </div>

    <!-- Dummy Results -->
    <h4 class="mb-3">Available Flights (Demo Data)</h4>

    <div class="row g-3">

        <div class="col-md-4">
            <div class="card p-3">
                <h5>IndiGo</h5>
                <p>₹12,500</p>
                <small>Non-stop • 3h 40m</small>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-3">
                <h5>Air India</h5>
                <p>₹14,200</p>
                <small>1 Stop • 5h 10m</small>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-3">
                <h5>Emirates</h5>
                <p>₹28,900</p>
                <small>Non-stop • 3h 20m</small>
            </div>
        </div>

    </div>
</div>

@include('extendfiles.footer')