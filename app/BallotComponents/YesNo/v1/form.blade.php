<p class="mb-3.5 text-[13px] text-muted">{{ __('components.yesno.hint') }}</p>
<x-ballot-option-list :component="$component" :election="$election" type="radio" :localize="true" />
