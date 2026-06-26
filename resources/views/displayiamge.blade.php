<div>
    <h1>Display File</h1>

    @if($filename)

        @php
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $fileUrl = asset('storage/public/' . $filename);
        @endphp

        {{-- Images --}}
        @if(in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
            <img src="{{ $fileUrl }}"
                 alt="Uploaded Image"
                 style="max-width:800px;">

        {{-- PDF --}}
        @elseif($extension === 'pdf')
            <iframe src="{{ $fileUrl }}"
                    width="100%"
                    height="800px">
            </iframe>

            <br>
            <a href="{{ $fileUrl }}" target="_blank">
                Open PDF
            </a>

        @else
            <a href="{{ $fileUrl }}" target="_blank">
                Download File
            </a>
        @endif

    @endif
</div>