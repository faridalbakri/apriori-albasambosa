@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border border-foreground/30 focus:border-ring focus:ring-2 focus:ring-ring/20 rounded-md shadow-sm px-3 py-2 text-sm text-foreground']) }}>
