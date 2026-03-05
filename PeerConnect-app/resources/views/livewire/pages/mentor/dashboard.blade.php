<?php

use function Livewire\Volt\{layout, state, mount};

layout('layouts.app');

mount(function () {
    abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');
});

?>

<div>
    <h1>Mentor Dashboard yell heah</h1>
    {{-- Insert UI here --}}
</div>