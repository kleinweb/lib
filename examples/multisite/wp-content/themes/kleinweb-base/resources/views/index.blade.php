@inject('layout', 'Kleinweb\Themes\Base\Layout')
<!doctype html>
<html {!! get_language_attributes() !!}>
<head>
  <meta charset="utf-8">
  @php(wp_head())
</head>
<body @php(body_class($layout->bodyClass()))>
  @php(wp_body_open())
  @include('partials.masthead')

  <main>
    @while (have_posts())
      @php(the_post())
      <article @php(post_class())>
        <h2><a href="{{ get_permalink() }}">{!! get_the_title() !!}</a></h2>
        {!! get_the_excerpt() !!}
      </article>
    @endwhile
  </main>

  @if ($layout->sidebar())
    <aside>@php(dynamic_sidebar('sidebar-primary'))</aside>
  @endif

  @php(wp_footer())
</body>
</html>
