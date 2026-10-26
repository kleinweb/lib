{{-- Overrides the shared partials/masthead on the news site only. --}}
<header class="masthead masthead--news">
  <p class="masthead__date">{{ $tagline }}</p>
  <a class="masthead__title" href="{{ home_url('/') }}">{{ $title }}</a>
  <nav>
    @foreach ($links as $label => $url)
      <a href="{{ $url }}">{{ $label }}</a>
    @endforeach
  </nav>
</header>
