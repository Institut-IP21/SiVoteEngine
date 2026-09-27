<?php

declare(strict_types=1);

namespace App\Livewire;

/**
 * OrderedList's voter form reuses the RankedChoice ranker verbatim: an
 * approval-then-rank submission (a rank of a subset of the roster) is
 * exactly what RankedChoiceLivewire already collects. All mount/select/up/
 * down/remove/setOrder/render behaviour is inherited unchanged; render()
 * resolves the template via $this->component->form_template_livewire,
 * which is type-derived ("OrderedList/v1/form_livewire"), so no override
 * is needed here.
 */
final class OrderedListLivewire extends RankedChoiceLivewire {}
