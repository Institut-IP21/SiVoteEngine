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
        $version = $this->option('version');
        $options = $this->option('options');

        while (!$ballotId || !Ballot::exists($ballotId)) {
            $ballotId = $this->ask('Please enter the ID of an existing ballot');
            if (!$ballotId || !Ballot::exists($ballotId)) {
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
            $type = $this->choice('Please choose a ballot type:', $ballotTypes, 0);
            if (!$type || !in_array($type, $ballotTypes)) {
                $this->info('Not a valid ballot type');
            }
        }

        $ballotTypeVersions = $this->ballotService->getBallotVersions($type);

        while (!$version || !$version === -1 || !in_array($version, $ballotTypeVersions)) {
            $version = $this->choice("Please choose a valid version of the {$type} ballot type:", $ballotTypeVersions, count($ballotTypeVersions) - 1);
            if (!$version || !$version === -1 || !in_array($version, $ballotTypeVersions)) {
                $this->info("Not a valid version of {$type} ballot type");
            }
        }

        if ($version === -1) {
            $version = array_key_last($ballotTypeVersions);
        }

        $ballotComponentClass = $this->ballotService->getBallotComponentClass($type, $version);

        $options = $ballotComponentClass::$needsOptions ? BallotComponent::parseOptionsString($options) : $ballotComponentClass::$presetOptions;

        while (!count($options) || !$ballotComponentClass::validateOptions($options)) {
            $options = BallotComponent::parseOptionsString($this->ask('Please enter options for the ballot'));
            if (!$options || !$ballotComponentClass::validateOptions($options)) {
                $this->info("Not valid options for {$type} ballot type");
            }
        }

        $args = [
            'Ballot ID' => $ballotId,
            'Title' => $title,
            'Description' => $description,
            'Component Type' => $type,
            'Version' => $version,
            'Options' => $options,
        ];

        $argsStr = print_r($args, true);
        $confirm = $this->confirm("Please confirm the component: $argsStr");

        if ($confirm) {
            $ballotComponent = BallotComponent::create([
                'ballot_id' => $ballotId,
                'title' => $title,
                'description' => $description,
                'type' => $type,
                'version' => $version,
                'options' => $options,
            ]);
            $this->info("Created new {$type}:{$version} component titled {$ballotComponent->title} with ID {$ballotComponent->id}");
        } else {
            $this->warn('Cancelled');
        }
    }
}
