{{-- Shared masthead; data from App\View\Composers\SiteHeader. --}}
<header class="masthead">
  <a class="masthead__title" href="{{ home_url('/') }}">{{ $title }}</a>
  @if ($tagline)
    <p class="masthead__tagline">{{ $tagline }}</p>
  @endif
  <nav>
    @foreach ($links as $label => $url)
      <a href="{{ $url }}">{{ $label }}</a>
    @endforeach
  </nav>
</header>
