@props([
    'text' => null,
    'href' => null,
])

<{{ isset($href) ? 'a' : 'button' }} {{ $attributes->class(["vh-button focus:outline-none text-white bg-orange-700 hover:bg-orange-800 focus:ring-4 focus:ring-orange-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-orange-600 dark:hover:bg-orange-700 dark:focus:ring-orange-800"])->merge(['href' => $href]) }}>{{ $text ?? $slot }}</{{ isset($href) ? 'a' : 'button' }}>
