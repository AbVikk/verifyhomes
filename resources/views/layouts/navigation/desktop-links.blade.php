<div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
    @foreach ($navigationLinks as $link)
        <x-nav-link :href="$link['href']" :active="$link['active']">
            {{ __($link['label']) }}
        </x-nav-link>
    @endforeach
</div>
