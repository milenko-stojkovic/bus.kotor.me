{{-- Works without re-running Vite; Tailwind grid classes alone are not enough if public/build is stale. --}}
<style>
    [data-pw-wrapper] {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        width: 100%;
    }
    [data-pw-wrapper] > input {
        grid-column: 1;
        grid-row: 1;
        min-width: 0;
        width: 100%;
        box-sizing: border-box;
        padding-right: 2.75rem;
    }
    [data-pw-wrapper] > button[data-pw-toggle] {
        grid-column: 1;
        grid-row: 1;
        justify-self: end;
        align-self: center;
        z-index: 2;
        margin-right: 0.125rem;
    }
</style>
