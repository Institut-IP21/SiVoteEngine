@props(['component', 'election', 'ballot', 'componentTree'])
{{-- One question's card: title · description · form. Shared by the live ballot and the
     preview so the two can't drift. --}}
<div class="bg-white border border-line rounded-2xl shadow-[0_1px_2px_rgba(16,30,40,.05)] p-5 sm:p-6">
    <x-ballot-component.title :component="$component" />
    <x-ballot-component.desc :component="$component" />
    <div class="mt-4">
        <x-ballot-component.form :component="$component" :componentTree="$componentTree"
            :election="$election" :ballot="$ballot" />
    </div>
</div>
