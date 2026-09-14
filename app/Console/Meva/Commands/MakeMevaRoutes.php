<?php

namespace App\Console\Meva\Commands;

use App\Console\Meva\Generators\GenerateMevaContract;
use App\Console\Meva\Generators\GenerateMevaController;
use App\Console\Meva\Generators\GenerateMevaModel;
use App\Console\Meva\Generators\GenerateMevaRepository;
use App\Console\Meva\Generators\GenerateMevaRoutes;
use App\Console\Meva\Generators\GenerateMevaServiceProvider;
use App\Console\Meva\Generators\GenerateMevaTestFile;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

class MakeMevaRoutes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:l-routes {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a routes file';

    protected $type = 'Routes';

    /**
     * Create a new command instance.
     *
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the command.
     *
     * @return void
     * @see fire()
     */
    public function handle()
    {
        $this->laravel->call([$this, 'fire'], func_get_args());
    }

    public function fire()
    {

        try {
            (new GenerateMevaRoutes([
                'name' => $this->argument('name')
            ]))->run();

            $this->info("Meva routes file successfully created.");
            $this->info("Please do not forget to register new routes file in Route Service Provider");

        } catch (\Exception $e) {
            $this->error($this->type . ' already exists!');
            $this->error($e->getMessage());

            return false;
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the entity.'],
        ];
    }
}
