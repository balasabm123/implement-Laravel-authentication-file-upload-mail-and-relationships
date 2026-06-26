<div>
<h2>{{ __('upload.changelangauge') }}</h2>

<a href="language/en">English</a>
<br/>
<a href="language/hi">Hindi</a>
<br/>
<a href="language/kan">Kannada</a>

 <h1>{{ __('upload.fileupload') }}</h1>
    @php
    // dd(app()->getLocale());
    @endphp
    <!-- Act only according to that maxim whereby you can, at the same time, will that it should become a universal law. - Immanuel Kant -->
     <form action="/upload" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="file" name="file" required>
        <button type="submit">{{ __('upload.upload') }}</button>
    </form>
</div>
