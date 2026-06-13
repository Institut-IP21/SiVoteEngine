<?php

namespace App\Console\Commands;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Services\BallotService;
use Illuminate\Console\Command;

class BallotComponentCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evote:make:ballot:component
                            {--B|ballot= : The ballot ID}
                            {--N|title= : The title of the ballot component}
                            {--D|description= : The description of the ballot component}
                            {--T|type= : The ballot component type}
                            {--R|variant=-1 : The version of the ballot. Defaults to latest}
                            {--P|pass-threshold= : Optional YesNo pass threshold (e.g. 50, 70, two_thirds, three_quarters)}
                            {--O|options=} | The options to put on the ballot';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new ballot for a specified election';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(BallotService $ballotService)
    {
        parent::__construct();
        $this->ballotService = $ballotService;
    }

    private BallotService $ballotService;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $ballotTypes = $this->ballotService->getBallotTypes();

        $ballotId = $this->option('ballot');
        $title = $this->option('title');
        $description = $this->option('description');
        $type = $this->option('type');
        $version = $this->option('variant');
        $options = $this->option('options');
        $passThreshold = $this->option('pass-threshold');

        while (!$ballotId || !Ballot::where('id', $ballotId)->exists()) {
            $ballotId = $this->ask('Please enter the ID of an existing ballot');
            if (!$ballotId || !Ballot::where('id', $ballotId)->exists()) {
                $this->info("Could not find ballot with ID {$ballotId}");
            }
        }

        while (!$title) {
            $title = $this->ask('Please enter a title for the ballot component');
        }

        while (!$description) {
            $description = $this->ask('Please enter a description for the ballot');
        }

        while (!$type || !in_array($type, $ballotTypes)) {
            $type = $this->choice('Please choose a ballot type:', $ballotTypes, "YesNo");
            if (!$type || !in_array($type, $ballotTypes)) {
                $this->info('Not a valid ballot type');
            }
        }

        /** @var string $type */
        $type = $type;

        $ballotTypeVersions = $this->ballotService->getBallotVersions($type);

        // "-1" is a sentinel meaning "use the latest available version".
        if (is_string($version) && $version === '-1') {
            $version = array_key_last($ballotTypeVersions);
        }

        while (!$version || !in_array($version, $ballotTypeVersions)) {
            $version = $this->choice("Please choose a valid version of the {$type} ballot type:", $ballotTypeVersions, "v1");
            if (!in_array($version, $ballotTypeVersions)) {
                $this->info("Not a valid version of {$type} ballot type");
            }
        }

        /** @var string $version */
        $version = $version;

        $ballotComponentClass = $this->ballotService->getBallotComponentClass($type, $version);

        $options = $ballotComponentClass::$needsOptions ? BallotComponent::parseOptionsString((string) $options) : $ballotComponentClass::$presetOptions;

        while (!count($options) || !$ballotComponentClass::validateOptions($options)) {
            $options = BallotComponent::parseOptionsString((string) $this->ask('Please enter options for the ballot'));
            if (!$options || !$ballotComponentClass::validateOptions($options)) {
                $this->info("Not valid options for {$type} ballot type");
            }
        }

        // Optional pass threshold persists into settings['pass_threshold'].
        // Backward-compatible: absent -> no settings -> component-type default (50).
        // A numeric value is normalised to int/float so it round-trips as a number,
        // not a string (preset strings like 'two_thirds' pass through untouched).
        $settings = null;
        if (is_string($passThreshold) && $passThreshold !== '') {
            if (is_numeric($passThreshold)) {
                $threshold = $passThreshold + 0; // int|float
            } else {
                $threshold = $passThreshold;
            }
            $settings = ['pass_threshold' => $threshold];
        }

        $args = [
            'Ballot ID' => $ballotId,
            'Title' => $title,
            'Description' => $description,
            'Component Type' => $type,
            'Version' => $version,
            'Options' => $options,
            'Settings' => $settings,
        ];

        $argsStr = print_r($args, true);
        $confirm = $this->confirm("Please confirm the component: $argsStr");

        if ($confirm) {
            $attributes = [
                'ballot_id' => $ballotId,
                'title' => $title,
                'description' => $description,
                'type' => $type,
                'version' => $version,
                'options' => $options,
            ];
            if ($settings !== null) {
                $attributes['settings'] = $settings;
            }
            $ballotComponent = BallotComponent::create($attributes);
            $this->info("Created new {$type}:{$version} component titled {$ballotComponent->title} with ID {$ballotComponent->id}");
        } else {
            $this->warn('Cancelled');
        }
        return 0;
    }
}
