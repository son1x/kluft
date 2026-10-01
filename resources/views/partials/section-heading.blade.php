{{-- Erwartet: $title, optional $id (für aria-labelledby) --}}
<h2 @isset($id) id="{{ $id }}" @endisset class="mb-8 inline-block border-b-2 border-dpsg-rot pb-2 font-serif text-2xl font-bold md:text-3xl">{{ $title }}</h2>
