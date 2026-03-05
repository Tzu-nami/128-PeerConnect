<?php

use function Livewire\Volt\{layout, state, mount};

layout('layouts.app');

mount(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');
});

?>

<div>
    <h1>Student Dashboard hell yeah</h1>
    {{-- Insert UI here --}}
</div>