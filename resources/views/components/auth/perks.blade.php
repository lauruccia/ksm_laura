@props(['eyebrow', 'title', 'items'])

<p class="ksm-auth__eyebrow">{{ $eyebrow }}</p>
<h2 class="ksm-auth__aside-title">{{ $title }}</h2>
<ul class="ksm-auth__perks">
    @foreach ($items as $item)
        <li><x-icon name="check" :size="18" /> <span>{{ $item }}</span></li>
    @endforeach
</ul>
{{ $slot }}
